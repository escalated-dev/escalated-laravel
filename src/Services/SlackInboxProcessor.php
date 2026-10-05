<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketChannel;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SlackInboundEvent;
use Escalated\Laravel\Models\SlackThread;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class SlackInboxProcessor
{
    /** Portable ceiling of the ticket description / reply body TEXT columns (bytes on MySQL). */
    public const MAX_BODY_BYTES = 65535;

    public const ATTACHMENT_NAME = 'slack-message.txt';

    public const TRUNCATION_NOTE = '<br />'."\n".'<br />'."\n".'[Message truncated; full text attached as slack-message.txt]';

    public function process(int $id): string
    {
        if (Escalated::db()->transactionLevel() !== 0) {
            throw new \LogicException('Process the Slack inbox outside an enclosing transaction.');
        }
        $stored = [];
        try {
            return Escalated::db()->transaction(function () use ($id, &$stored) {
                $event = SlackInboundEvent::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($event->status !== 'pending' || $event->available_at?->isFuture()) {
                    return $event->status;
                }
                $inbox = app(SlackInbox::class);
                $destination = $inbox->destination($event->app_key, $event->workspace_id, $event->channel_id);
                $context = app(TenantContext::class);
                if ($context->enabled() && ($destination['tenant_id'] ?? null) !== $context->id()) {
                    throw new SlackProcessingException('routing_changed');
                }
                $message = $event->payload['event'];
                $text = self::decodeEntities($message['text']);
                $body = nl2br(e($text));
                // Text too large for the body columns is kept whole as a private
                // attachment; the body holds a leading slice and a note.
                $oversized = strlen($body) > self::MAX_BODY_BYTES;
                if ($oversized) {
                    $body = self::truncatedBody($text);
                }
                $root = $message['ts'] === $event->thread_ts;
                if ($root) {
                    SlackThread::firstOrCreate(['thread_key' => $event->thread_key], [
                        'app_key' => $event->app_key, 'workspace_id' => $event->workspace_id,
                        'channel_id' => $event->channel_id, 'thread_ts' => $event->thread_ts,
                    ]);
                }
                $thread = SlackThread::where('thread_key', $event->thread_key)->lockForUpdate()->first();
                if (! $thread || (! $root && $thread->ticket_id === null)) {
                    $expired = $event->created_at->lt(now()->subDay());
                    $event->update(['status' => $expired ? 'failed' : 'pending', 'last_error' => 'awaiting_root_message',
                        'available_at' => now()->addMinute()]);

                    return $expired ? 'failed' : 'deferred';
                }
                if ($thread->app_key !== $event->app_key) {
                    throw new SlackProcessingException('different_app_binding');
                }
                $config = $inbox->appConfig($event->app_key);
                $requester = $config['users'][$event->workspace_id][$message['user']] ?? null;
                if (! is_array($requester)) {
                    throw new SlackProcessingException('identity_not_mapped');
                }
                $rules = array_filter(AgentTicketCreator::rules(), fn ($key) => str_starts_with($key, 'requester'), ARRAY_FILTER_USE_KEY);
                $requester = Validator::make(['requester' => $requester], $rules)->validate()['requester'];
                $metadata = ['source' => 'slack', 'slack' => ['app' => $event->app_key, 'workspace' => $event->workspace_id,
                    'channel' => $event->channel_id, 'thread_ts' => $event->thread_ts, 'event_id' => $event->event_id, 'user' => $message['user']]];
                $reply = null;
                if ($root && $thread->ticket_id === null) {
                    $actor = $this->hostUser($destination['actor_id'] ?? null);
                    if (! StaffAccess::isStaff($actor)) {
                        throw new SlackProcessingException('service_agent_denied');
                    }
                    Gate::forUser($actor)->authorize('create', Ticket::class);
                    $ticket = app(AgentTicketCreator::class)->create($actor, [
                        'subject' => Str::limit(preg_replace('/\s+/u', ' ', trim($text)), 240),
                        'description' => $body, 'requester' => $requester,
                        'department_id' => $destination['department_id'] ?? null, 'metadata' => $metadata,
                    ]);
                    $ticket->updateQuietly(['channel' => TicketChannel::Slack]);
                    $thread->update(['ticket_id' => $ticket->id]);
                    if ($oversized) {
                        $stored[] = $this->attachFullText($ticket, $text);
                    }
                } else {
                    $ticket = Ticket::whereKey($thread->ticket_id)->lockForUpdate()->first();
                    if (! $ticket) {
                        // Deleted correspondence is never recreated or retried.
                        throw new SlackProcessingException('ticket_missing', terminal: true);
                    }
                    if (! $root) {
                        $author = isset($requester['id']) ? $this->hostUser($requester['id'])
                            : Contact::findOrCreateByEmail($requester['email'], $requester['name']);
                        if ($author instanceof Contact) {
                            if ((string) $ticket->contact_id !== (string) $author->id) {
                                throw new SlackProcessingException('contact_does_not_own_ticket');
                            }
                        } else {
                            Gate::forUser($author)->authorize('reply', $ticket);
                        }
                        $reply = new Reply(['ticket_id' => $ticket->id, 'body' => $body,
                            'type' => 'reply', 'is_internal_note' => false, 'metadata' => $metadata]);
                        $reply->author()->associate($author);
                        $reply->deferCreatedEvent = true;
                        $reply->save();
                        if ($oversized) {
                            $stored[] = $this->attachFullText($reply, $text);
                        }
                        $ticket->activities()->create(['type' => ActivityType::Replied,
                            'causer_type' => $author->getMorphClass(), 'causer_id' => $author->getKey(),
                            'properties' => ['source' => 'slack', 'event_id' => $event->event_id]]);
                        $reply->dispatchCreatedAfterCommit();
                    }
                }
                $event->update(['ticket_id' => $ticket->id, 'reply_id' => $reply?->id, 'status' => 'processed',
                    'processed_at' => now(), 'attempts' => $event->attempts + 1, 'last_error' => null]);

                return 'processed';
            });
        } catch (Throwable $error) {
            // Ticket/reply writes rolled back. Keep a durable retry record, but
            // never re-create an aggregate after an after-commit listener fails.
            return Escalated::db()->transaction(function () use ($id, $error, $stored) {
                $event = SlackInboundEvent::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($event->status === 'processed') {
                    Log::warning('Slack inbox after-commit listener failed', ['event_id' => $event->event_id, 'exception' => $error::class]);

                    return 'processed';
                }
                // The attachment rows rolled back; remove their files so a retry
                // leaves exactly one stored copy.
                foreach ($stored as [$disk, $path]) {
                    rescue(fn () => Storage::disk($disk)->delete($path), report: false);
                }
                $attempts = $event->attempts + 1;
                $status = ($error instanceof SlackProcessingException && $error->terminal)
                    || $attempts >= max(1, (int) config('escalated.slack.max_attempts', 8)) ? 'failed' : 'pending';
                $event->update(['attempts' => $attempts, 'status' => $status,
                    'last_error' => $error instanceof SlackProcessingException ? $error->getMessage() : class_basename($error),
                    'available_at' => now()->addSeconds(min(3600, 30 * 2 ** min($attempts, 7)))]);
                Log::warning('Slack inbox processing failed', ['event_id' => $event->event_id, 'exception' => $error::class]);

                return $status;
            });
        }
    }

    /** The longest leading slice, cut between characters, whose escaped HTML and note fit the column. */
    public static function truncatedBody(string $text): string
    {
        $budget = self::MAX_BODY_BYTES - strlen(self::TRUNCATION_NOTE);
        [$low, $high] = [0, mb_strlen($text, 'UTF-8')];
        while ($low < $high) {
            $length = intdiv($low + $high + 1, 2);
            if (strlen(nl2br(e(mb_substr($text, 0, $length, 'UTF-8')))) <= $budget) {
                $low = $length;
            } else {
                $high = $length - 1;
            }
        }

        return nl2br(e(mb_substr($text, 0, $low, 'UTF-8'))).self::TRUNCATION_NOTE;
    }

    /** @return array{0: string, 1: string} the stored disk and path */
    private function attachFullText(Model $attachable, string $text): array
    {
        try {
            $attachment = app(AttachmentService::class)->storeContent($attachable, $text, self::ATTACHMENT_NAME, 'text/plain');
        } catch (Throwable) {
            // Retried with backoff, then dead-lettered; the inbox keeps the text.
            throw new SlackProcessingException('attachment_storage_failed');
        }

        return [$attachment->disk, $attachment->path];
    }

    /** Slack escapes only &, < and > in message text; decode them in one pass. */
    public static function decodeEntities(string $text): string
    {
        return strtr($text, ['&lt;' => '<', '&gt;' => '>', '&amp;' => '&']);
    }

    private function hostUser(mixed $id): Model&Ticketable
    {
        $model = Escalated::newUserModel();
        app(TicketSubjectResolver::class)->validateKey($id, 'slack.user', $model);
        $context = app(TenantContext::class);
        $user = $context->scopeHost($model->newQuery())->whereKey($id)->first();
        if (! $user instanceof Ticketable || ($context->enabled() && (! $context->canAccess($user)
            || ! $context->resolver()->canReference($user, $context->id())))) {
            throw new AuthorizationException('Slack host identity is unavailable.');
        }

        return $user;
    }
}

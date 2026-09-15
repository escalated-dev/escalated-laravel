<?php

namespace Escalated\Laravel\Http\Controllers\Cloud;

use Escalated\Laravel\Drivers\LocalDriver;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\CloudVocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies cloud-side changes to the local ticket in Synced mode.
 *
 * cloud.escalated.dev posts `ticket.updated` / `ticket.status_changed`
 * with the projected ticket. The projection carries this site's own
 * reference as `external_id`; tickets without one never came from here
 * and are ignored. Changes go through LocalDriver so listeners,
 * notifications and workflows fire exactly as for a local edit, while the
 * SyncedDriver is bypassed so nothing is echoed back to the cloud.
 */
class CloudWebhookController extends Controller
{
    private const APPLIED_EVENTS = ['ticket.updated', 'ticket.status_changed'];

    public function __invoke(Request $request, LocalDriver $driver): JsonResponse
    {
        $secret = (string) config('escalated.hosted.signing_secret', '');

        if ($secret === '') {
            Log::warning('Escalated cloud webhook received but escalated.hosted.signing_secret is not configured.');

            return response()->json(['error' => 'Cloud webhook signing secret is not configured.'], 503);
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        $signature = (string) $request->header('X-Escalated-Signature', '');

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $body = $request->json()->all();
        $event = (string) ($body['event'] ?? '');
        $eventId = $body['event_id'] ?? null;
        $cloudTicket = is_array($body['ticket'] ?? null) ? $body['ticket'] : [];

        if (is_string($eventId) && $eventId !== '' && ! Cache::add("escalated:cloud-event:{$eventId}", 1, now()->addDay())) {
            return response()->json(['received' => true, 'applied' => false, 'replay' => true]);
        }

        if (! in_array($event, self::APPLIED_EVENTS, true)) {
            return $this->ignored("Event {$event} carries no changes for the site.");
        }

        $reference = $cloudTicket['external_id'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return $this->ignored('Ticket did not originate from this site.');
        }

        $ticket = Ticket::query()->where('reference', $reference)->first();

        if ($ticket === null) {
            return $this->ignored("No local ticket with reference {$reference}.");
        }

        $applied = [];

        try {
            $applied = $this->apply($driver, $ticket, $cloudTicket);
        } catch (Throwable $e) {
            Log::warning("Escalated cloud webhook could not apply {$event} to {$reference}: {$e->getMessage()}");

            return response()->json(['received' => true, 'applied' => false, 'reason' => $e->getMessage()], 200);
        }

        return response()->json(['received' => true, 'applied' => $applied !== [], 'changes' => $applied]);
    }

    /**
     * @param  array<string, mixed>  $cloudTicket
     * @return array<int, string> attribute names that changed
     */
    private function apply(LocalDriver $driver, Ticket $ticket, array $cloudTicket): array
    {
        $applied = [];

        $content = [];
        foreach (['subject', 'description'] as $field) {
            if (isset($cloudTicket[$field]) && (string) $cloudTicket[$field] !== (string) $ticket->{$field}) {
                $content[$field] = (string) $cloudTicket[$field];
            }
        }

        if ($content !== []) {
            $ticket = $driver->updateTicket($ticket, $content);
            $applied = array_merge($applied, array_keys($content));
        }

        if (isset($cloudTicket['priority'])) {
            $priority = TicketPriority::tryFrom(CloudVocabulary::priorityFromCloud((string) $cloudTicket['priority']));

            if ($priority !== null && $priority !== $ticket->priority) {
                $ticket = $driver->changePriority($ticket, $priority);
                $applied[] = 'priority';
            }
        }

        if (isset($cloudTicket['status'])) {
            $status = TicketStatus::tryFrom(CloudVocabulary::statusFromCloud((string) $cloudTicket['status']));

            if ($status !== null && $status !== $ticket->status) {
                $driver->transitionStatus($ticket, $status);
                $applied[] = 'status';
            }
        }

        return $applied;
    }

    private function ignored(string $reason): JsonResponse
    {
        return response()->json(['received' => true, 'applied' => false, 'reason' => $reason], 202);
    }
}

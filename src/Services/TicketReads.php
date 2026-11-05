<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketRead;
use Escalated\Laravel\Support\ImportContext;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-agent unread tracking (ADR 2026-11-05-agent-panel-data, section 1).
 *
 * A ticket is unread for an agent when the agent marked it unread, or when it
 * has customer activity (`last_customer_activity_at`) newer than the agent's
 * read marker, or no marker at all.
 */
class TicketReads
{
    /** Reply metadata keys that mark a reply written by the system, not a person. */
    private const SYSTEM_MARKERS = ['workflow_id', 'automation_id', 'system_note'];

    public function markRead(Authenticatable $agent, Ticket $ticket): void
    {
        $this->write($agent, $ticket, Carbon::now());
    }

    public function markUnread(Authenticatable $agent, Ticket $ticket): void
    {
        $this->write($agent, $ticket, null);
    }

    public function isUnread(Authenticatable $agent, Ticket $ticket): bool
    {
        return $this->flags($agent, collect([$ticket]))[$ticket->getKey()];
    }

    /**
     * Whether each ticket is unread for the agent, in one query.
     *
     * @param  Collection<int, Ticket>  $tickets
     * @return array<int|string, bool> ticket id => is_unread
     */
    public function flags(Authenticatable $agent, Collection $tickets): array
    {
        $reads = $tickets->isEmpty() ? collect() : TicketRead::query()
            ->where('user_id', $agent->getAuthIdentifier())
            ->whereIn('ticket_id', $tickets->map->getKey()->all())
            ->get(['ticket_id', 'last_read_at'])
            ->keyBy('ticket_id');

        return $tickets->mapWithKeys(function (Ticket $ticket) use ($reads) {
            $read = $reads->get($ticket->getKey());
            $activity = $ticket->last_customer_activity_at;

            $unread = match (true) {
                $read !== null && $read->last_read_at === null => true,
                $activity === null => false,
                $read === null => true,
                default => $activity->greaterThan($read->last_read_at),
            };

            return [$ticket->getKey() => $unread];
        })->all();
    }

    /** Limit a ticket query to the tickets unread for the agent (the rule flags() applies). */
    public function onlyUnread(Builder $query, Authenticatable $agent): Builder
    {
        $reads = Escalated::table('ticket_reads');
        $tickets = $query->getModel()->getTable();
        $user = $agent->getAuthIdentifier();

        return $query->where(function (Builder $q) use ($reads, $tickets, $user) {
            $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from($reads)
                ->whereColumn("{$reads}.ticket_id", "{$tickets}.id")
                ->where("{$reads}.user_id", $user)
                ->whereNull("{$reads}.last_read_at"))
                ->orWhere(fn (Builder $active) => $active->whereNotNull("{$tickets}.last_customer_activity_at")
                    ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from($reads)
                        ->whereColumn("{$reads}.ticket_id", "{$tickets}.id")
                        ->where("{$reads}.user_id", $user)
                        ->whereColumn("{$reads}.last_read_at", '>=', "{$tickets}.last_customer_activity_at")));
        });
    }

    /** A new ticket is customer activity, except while importing. */
    public static function ticketCreating(Ticket $ticket): void
    {
        if (! ImportContext::isImporting() && $ticket->last_customer_activity_at === null) {
            $ticket->last_customer_activity_at = $ticket->freshTimestamp();
        }
    }

    /** A customer-side public reply or chat message is customer activity. */
    public static function replyCreated(Reply $reply): void
    {
        if (ImportContext::isImporting() || ! self::isCustomerSide($reply)) {
            return;
        }

        Ticket::query()->withoutGlobalScopes()->whereKey($reply->ticket_id)->toBase()
            ->update(['last_customer_activity_at' => $reply->created_at ?? Carbon::now()]);
    }

    /**
     * A public reply written by the requester (or the guest's contact), any
     * contact, a guest (no author), or a host user who is not staff. Agents'
     * replies, notes, and replies a workflow, automation or the system wrote
     * are not.
     */
    public static function isCustomerSide(Reply $reply): bool
    {
        if ($reply->is_internal_note) {
            return false;
        }

        if ($reply->author_type === null || $reply->author_id === null) {
            $metadata = is_array($reply->metadata) ? $reply->metadata : [];

            return array_intersect_key($metadata, array_flip(self::SYSTEM_MARKERS)) === [];
        }

        if ($reply->author_type === (new Contact)->getMorphClass()) {
            return true;
        }

        $ticket = Ticket::query()->withoutGlobalScopes()->find($reply->ticket_id);
        if ($ticket && $ticket->isRequesterIdentity($reply->author_type, $reply->author_id)) {
            return true;
        }

        if ($reply->author_type === Escalated::newUserModel()->getMorphClass()) {
            $author = Escalated::findUser($reply->author_id);

            return $author !== null && ! StaffAccess::isStaff($author);
        }

        return false;
    }

    private function write(Authenticatable $agent, Ticket $ticket, ?Carbon $at): void
    {
        $match = ['user_id' => $agent->getAuthIdentifier(), 'ticket_id' => $ticket->getKey()];

        try {
            TicketRead::query()->updateOrCreate($match, ['last_read_at' => $at]);
        } catch (UniqueConstraintViolationException) {
            // Another request created the marker between the lookup and the insert.
            TicketRead::query()->where($match)->update(['last_read_at' => $at]);
        }
    }
}

<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\CustomerPresence;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Customer online status (ADR 2026-11-05-agent-panel-data, section 2).
 *
 * Requests the customer already makes (the customer portal, widget chat, the
 * widget's ticket status) record when they were last seen, at most once per
 * subject every THROTTLE_SECONDS. A customer is online while that is within
 * `escalated.presence.online_window_seconds`.
 */
class CustomerPresenceTracker
{
    public const THROTTLE_SECONDS = 30;

    /** A customer-portal user. */
    public function seeUser(Model $user): void
    {
        $this->see($user->getMorphClass(), $user->getKey());
    }

    /** The customer of a ticket the widget just served. */
    public function seeTicketCustomer(Ticket $ticket): void
    {
        if ($subject = $this->subject($ticket)) {
            $this->see(...$subject);
        }
    }

    /**
     * Set `requester_online` and `requester_last_seen_at` on each ticket, in
     * one query. For staff-facing pages only.
     *
     * @param  iterable<Ticket>  $tickets
     */
    public function decorate(iterable $tickets): void
    {
        $tickets = Collection::make($tickets);
        $subjects = $tickets->map(fn (Ticket $ticket) => $this->subject($ticket))->filter();

        $seen = $subjects->isEmpty() ? collect() : CustomerPresence::query()
            ->where(function ($query) use ($subjects) {
                foreach ($subjects->unique(fn (array $s) => $s[0].'|'.$s[1]) as [$type, $id]) {
                    $query->orWhere(fn ($q) => $q->where('subject_type', $type)->where('subject_id', (string) $id));
                }
            })
            ->get(['subject_type', 'subject_id', 'last_seen_at'])
            ->keyBy(fn (CustomerPresence $row) => $row->subject_type.'|'.$row->subject_id);

        $since = Carbon::now()->subSeconds($this->window());

        foreach ($tickets as $ticket) {
            $subject = $this->subject($ticket);
            $lastSeen = $subject ? $seen->get($subject[0].'|'.$subject[1])?->last_seen_at : null;

            $ticket->setAttribute('requester_online', $lastSeen !== null && $lastSeen->greaterThanOrEqualTo($since));
            $ticket->setAttribute('requester_last_seen_at', $lastSeen?->toIso8601String());
        }
    }

    public function window(): int
    {
        return max(1, (int) config('escalated.presence.online_window_seconds', 120));
    }

    /** @return array{0: string, 1: int|string}|null */
    private function subject(Ticket $ticket): ?array
    {
        if ($ticket->requester_type !== null && $ticket->requester_id !== null) {
            return [$ticket->requester_type, $ticket->requester_id];
        }

        if ($ticket->contact_id !== null) {
            return [(new Contact)->getMorphClass(), $ticket->contact_id];
        }

        return null;
    }

    private function see(string $type, int|string $id): void
    {
        $key = app(TenantContext::class)->cacheKey('escalated.presence.'.sha1($type.'|'.$id));

        // Cache::add() is atomic: only the first request in the window writes.
        if (! Cache::add($key, true, self::THROTTLE_SECONDS)) {
            return;
        }

        $match = ['subject_type' => $type, 'subject_id' => (string) $id];

        try {
            CustomerPresence::query()->updateOrCreate($match, ['last_seen_at' => Carbon::now()]);
        } catch (UniqueConstraintViolationException) {
            CustomerPresence::query()->where($match)->update(['last_seen_at' => Carbon::now()]);
        }
    }
}

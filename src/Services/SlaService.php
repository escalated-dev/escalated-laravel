<?php

namespace Escalated\Laravel\Services;

use Carbon\Carbon;
use Escalated\Laravel\Events\SlaBreached;
use Escalated\Laravel\Events\SlaWarning;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;

class SlaService
{
    public function attachDefaultPolicy(Ticket $ticket): void
    {
        $query = SlaPolicy::active();
        if (config('escalated.sla.targeted', false)) {
            $query->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $ticket->department_id))
                ->where(fn ($q) => $q->whereNull('ticket_type')->orWhere('ticket_type', $ticket->ticket_type))
                ->where(fn ($q) => $q->whereNotNull('department_id')->orWhereNotNull('ticket_type')->orWhere('is_default', true))
                ->orderByRaw('CASE WHEN department_id IS NOT NULL AND ticket_type IS NOT NULL THEN 0 WHEN department_id IS NOT NULL THEN 1 WHEN ticket_type IS NOT NULL THEN 2 ELSE 3 END');
        } else {
            $query->default()->whereNull('department_id')->whereNull('ticket_type');
        }
        $policy = $query->orderBy('id')->first();

        if (! $policy) {
            return;
        }

        $this->attachPolicy($ticket, $policy);
    }

    public function attachPolicy(Ticket $ticket, SlaPolicy $policy): void
    {
        $ticket->sla_policy_id = $policy->id;

        $firstResponseHours = $policy->getFirstResponseHoursFor($ticket->priority);
        $resolutionHours = $policy->getResolutionHoursFor($ticket->priority);

        if ($firstResponseHours) {
            $ticket->first_response_due_at = $this->calculateDueDate(
                $ticket->created_at, $firstResponseHours, $policy->business_hours_only
            );
        }

        if ($resolutionHours) {
            $ticket->resolution_due_at = $this->calculateDueDate(
                $ticket->created_at, $resolutionHours, $policy->business_hours_only
            );
        }

        // Deadline bookkeeping, not a change to the ticket's details: do not
        // announce it as ticket.updated to webhooks and workflows.
        Ticket::withoutUpdatedEvent(fn () => $ticket->save());
    }

    public function checkBreaches(): int
    {
        $breached = 0;
        $now = now();

        $tickets = Ticket::open()
            ->whereNotNull('first_response_due_at')
            ->whereNull('first_response_at')
            ->where('sla_first_response_breached', false)
            ->where('first_response_due_at', '<', $now)
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['sla_first_response_breached' => true]);
            SlaBreached::dispatch($ticket, 'first_response');
            $breached++;
        }

        $tickets = Ticket::open()
            ->whereNotNull('resolution_due_at')
            ->where('sla_resolution_breached', false)
            ->where('resolution_due_at', '<', $now)
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['sla_resolution_breached' => true]);
            SlaBreached::dispatch($ticket, 'resolution');
            $breached++;
        }

        return $breached;
    }

    public function checkWarnings(int $warningMinutes = 30): int
    {
        $warned = 0;
        $now = now();
        $threshold = $now->copy()->addMinutes($warningMinutes);

        $tickets = Ticket::open()
            ->whereNotNull('first_response_due_at')
            ->whereNull('first_response_at')
            ->where('sla_first_response_breached', false)
            ->whereBetween('first_response_due_at', [$now, $threshold])
            ->get();

        foreach ($tickets as $ticket) {
            $minutes = (int) $now->diffInMinutes($ticket->first_response_due_at);
            SlaWarning::dispatch($ticket, 'first_response', $minutes);
            $warned++;
        }

        $tickets = Ticket::open()
            ->whereNotNull('resolution_due_at')
            ->where('sla_resolution_breached', false)
            ->whereBetween('resolution_due_at', [$now, $threshold])
            ->get();

        foreach ($tickets as $ticket) {
            $minutes = (int) $now->diffInMinutes($ticket->resolution_due_at);
            SlaWarning::dispatch($ticket, 'resolution', $minutes);
            $warned++;
        }

        return $warned;
    }

    protected function calculateDueDate(Carbon $from, float $hours, bool $businessHoursOnly): Carbon
    {
        if (! $businessHoursOnly) {
            return $from->copy()->addHours($hours);
        }

        $config = config('escalated.sla.business_hours');
        $start = $config['start'] ?? '09:00';
        $end = $config['end'] ?? '17:00';
        $timezone = $config['timezone'] ?? 'UTC';
        $days = $config['days'] ?? [1, 2, 3, 4, 5];

        $current = $from->copy()->timezone($timezone);
        $remainingMinutes = $hours * 60;

        while ($remainingMinutes > 0) {
            if (in_array($current->dayOfWeek, $days)) {
                $dayStart = $current->copy()->setTimeFromTimeString($start);
                $dayEnd = $current->copy()->setTimeFromTimeString($end);

                if ($current->lt($dayStart)) {
                    $current = $dayStart;
                }

                if ($current->lt($dayEnd)) {
                    $availableMinutes = $current->diffInMinutes($dayEnd);
                    if ($availableMinutes >= $remainingMinutes) {
                        return $current->addMinutes($remainingMinutes);
                    }
                    $remainingMinutes -= $availableMinutes;
                }
            }

            $current = $current->addDay()->setTimeFromTimeString($start);
        }

        return $current;
    }
}

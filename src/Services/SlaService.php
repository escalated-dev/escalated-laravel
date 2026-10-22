<?php

namespace Escalated\Laravel\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\SlaBreached;
use Escalated\Laravel\Events\SlaWarning;
use Escalated\Laravel\Models\BusinessSchedule;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;

class SlaService
{
    public function attachDefaultPolicy(Ticket $ticket): void
    {
        $policy = $this->selectPolicy($ticket);

        if (! $policy) {
            return;
        }

        $this->attachPolicy($ticket, $policy);
    }

    /**
     * The policy a ticket would get at creation: the most specific active
     * targeted match when escalated.sla.targeted is on, otherwise the
     * untargeted default.
     */
    public function selectPolicy(Ticket $ticket): ?SlaPolicy
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

        return $query->orderBy('id')->first();
    }

    /**
     * Select the policy again after the ticket's routing changed, anchoring
     * the new deadlines on $at (the time of the change) rather than on
     * created_at, so a ticket moved late in its life is not born breached.
     *
     * - Resolved and closed tickets keep the SLA they finished under.
     * - When the same policy is selected, the deadlines are left alone.
     * - A deadline the new policy has no figure for is cleared; when no
     *   policy matches, the policy and both deadlines are cleared.
     * - A first response that already happened keeps its deadline, so the
     *   record of whether it was met is not rewritten.
     * - Recorded breaches are kept.
     */
    public function reselectPolicy(Ticket $ticket, ?CarbonInterface $at = null): void
    {
        if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            return;
        }

        $policy = $this->selectPolicy($ticket);

        if ($policy?->getKey() === $ticket->sla_policy_id) {
            return;
        }

        $at = Carbon::instance($at ?? now());
        $ticket->sla_policy_id = $policy?->getKey();
        $schedule = $policy?->business_hours_only ? $this->scheduleFor($policy, $ticket) : null;

        if ($ticket->first_response_at === null) {
            $firstResponseHours = $policy?->getFirstResponseHoursFor($ticket->priority);
            $ticket->first_response_due_at = $firstResponseHours
                ? $this->calculateDueDate($at, $firstResponseHours, $policy->business_hours_only, $schedule)
                : null;
        }

        $resolutionHours = $policy?->getResolutionHoursFor($ticket->priority);
        $ticket->resolution_due_at = $resolutionHours
            ? $this->calculateDueDate($at, $resolutionHours, $policy->business_hours_only, $schedule)
            : null;

        $ticket->save();
    }

    public function attachPolicy(Ticket $ticket, SlaPolicy $policy): void
    {
        $ticket->sla_policy_id = $policy->id;

        $firstResponseHours = $policy->getFirstResponseHoursFor($ticket->priority);
        $resolutionHours = $policy->getResolutionHoursFor($ticket->priority);
        $schedule = $policy->business_hours_only ? $this->scheduleFor($policy, $ticket) : null;

        if ($firstResponseHours) {
            $ticket->first_response_due_at = $this->calculateDueDate(
                $ticket->created_at, $firstResponseHours, $policy->business_hours_only, $schedule
            );
        }

        if ($resolutionHours) {
            $ticket->resolution_due_at = $this->calculateDueDate(
                $ticket->created_at, $resolutionHours, $policy->business_hours_only, $schedule
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

    /**
     * The business schedule a business-hours policy counts against: the
     * policy's own, else the ticket department's, else the default schedule.
     * Null when none applies, which keeps the configured
     * escalated.sla.business_hours.
     */
    public function scheduleFor(SlaPolicy $policy, Ticket $ticket): ?BusinessSchedule
    {
        $ids = [];
        if ($policy->business_schedule_id) {
            $ids[] = $policy->business_schedule_id;
        }
        if ($ticket->department_id) {
            $departmentScheduleId = Department::whereKey($ticket->department_id)->value('business_schedule_id');
            if ($departmentScheduleId) {
                $ids[] = $departmentScheduleId;
            }
        }

        foreach ($ids as $id) {
            $schedule = BusinessSchedule::with('holidays')->find($id);
            if ($schedule) {
                return $schedule;
            }
        }

        return BusinessSchedule::with('holidays')->default()->orderBy('id')->first();
    }

    protected function calculateDueDate(Carbon $from, float $hours, bool $businessHoursOnly, ?BusinessSchedule $schedule = null): Carbon
    {
        if (! $businessHoursOnly) {
            return $from->copy()->addHours($hours);
        }

        if ($schedule) {
            return app(BusinessHoursCalculator::class)->addBusinessHours($from, $hours, $schedule);
        }

        // Back in the zone $from came in: the datetime cast stores a Carbon's
        // wall time, so a deadline left in the configured zone was saved as
        // that zone's clock reading rather than the instant.
        return $this->calculateConfiguredDueDate($from, $hours)->setTimezone($from->getTimezone());
    }

    /**
     * Business hours from escalated.sla.business_hours, for installs with no
     * business schedule.
     */
    protected function calculateConfiguredDueDate(Carbon $from, float $hours): Carbon
    {
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

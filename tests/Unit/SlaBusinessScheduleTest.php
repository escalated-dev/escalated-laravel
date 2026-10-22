<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\BusinessSchedule;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\SlaService;
use Illuminate\Support\Carbon;

/*
 * A business-hours policy counts in the policy's schedule, else the ticket
 * department's, else the default schedule. With none, the configured
 * escalated.sla.business_hours apply as before.
 */

beforeEach(function () {
    config(['escalated.sla.business_hours' => [
        'start' => '09:00', 'end' => '17:00', 'timezone' => 'UTC', 'days' => [1, 2, 3, 4, 5],
    ]]);
    // Monday 06:00 UTC.
    $this->createdAt = Carbon::parse('2026-10-26 06:00:00', 'UTC');
    $this->travelTo($this->createdAt);
    $this->department = Department::factory()->create();
});

function slaSchedule(string $start, string $end, bool $default = false): BusinessSchedule
{
    $hours = ['start' => $start, 'end' => $end];

    return BusinessSchedule::create([
        'name' => "{$start}-{$end}",
        'timezone' => 'UTC',
        'is_default' => $default,
        'schedule' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], $hours),
    ]);
}

function slaPolicy(array $attributes = []): SlaPolicy
{
    return SlaPolicy::factory()->create(array_merge([
        'business_hours_only' => true,
        'first_response_hours' => ['medium' => 1],
        'resolution_hours' => ['medium' => 3],
    ], $attributes));
}

function slaAttached(SlaPolicy $policy, ?Department $department): Ticket
{
    $ticket = Ticket::factory()->create([
        'priority' => 'medium',
        'status' => TicketStatus::Open,
        'department_id' => $department?->id,
    ])->fresh();

    (new SlaService)->attachPolicy($ticket, $policy);

    return $ticket->fresh();
}

function slaAt(?Carbon $at): ?string
{
    return $at?->copy()->setTimezone('UTC')->format('Y-m-d H:i');
}

it('counts in the policy schedule first', function () {
    slaSchedule('07:00', '08:00', default: true);
    $this->department->update(['business_schedule_id' => slaSchedule('13:00', '15:00')->id]);
    $policy = slaPolicy(['business_schedule_id' => slaSchedule('10:00', '12:00')->id]);

    $ticket = slaAttached($policy, $this->department);

    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-26 11:00')
        ->and(slaAt($ticket->resolution_due_at))->toBe('2026-10-27 11:00');
});

it('counts in the department schedule when the policy has none', function () {
    slaSchedule('07:00', '08:00', default: true);
    $this->department->update(['business_schedule_id' => slaSchedule('13:00', '15:00')->id]);

    $ticket = slaAttached(slaPolicy(), $this->department);

    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-26 14:00');
});

it('counts in the default schedule when neither the policy nor the department has one', function () {
    slaSchedule('13:00', '15:00');
    slaSchedule('07:00', '08:00', default: true);

    $ticket = slaAttached(slaPolicy(), $this->department);

    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-26 08:00');
});

it('uses the default schedule for a ticket with no department', function () {
    slaSchedule('07:00', '08:00', default: true);

    $ticket = slaAttached(slaPolicy(), null);

    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-26 08:00');
});

it('keeps the configured business hours when no schedule applies', function () {
    slaSchedule('07:00', '08:00'); // exists, but is not the default and nothing points at it

    $ticket = slaAttached(slaPolicy(), $this->department);

    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-26 10:00')
        ->and(slaAt($ticket->resolution_due_at))->toBe('2026-10-26 12:00');
});

it('ignores schedules for a policy that counts calendar time', function () {
    slaSchedule('07:00', '08:00', default: true);
    $policy = slaPolicy(['business_hours_only' => false, 'business_schedule_id' => slaSchedule('10:00', '12:00')->id]);

    $ticket = slaAttached($policy, $this->department);

    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-26 07:00');
});

it('skips holidays of the chosen schedule', function () {
    $schedule = slaSchedule('09:00', '17:00', default: true);
    $schedule->holidays()->create(['name' => 'Closed', 'date' => '2026-10-26', 'recurring' => false]);

    $ticket = slaAttached(slaPolicy(), $this->department);

    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-27 10:00');
});

it('uses the new department schedule when a move reselects the policy', function () {
    config(['escalated.sla.targeted' => true]);
    $other = Department::factory()->create(['business_schedule_id' => slaSchedule('13:00', '15:00')->id]);
    slaPolicy(['department_id' => $other->id]);
    $ticket = Ticket::factory()->create([
        'priority' => 'medium', 'status' => TicketStatus::Open, 'department_id' => $this->department->id,
    ])->fresh();

    $moved = $ticket->changeDepartment($other, null);

    expect(slaAt($moved->first_response_due_at))->toBe('2026-10-26 14:00');
});

it('stores a configured-hours deadline at the right instant when their zone differs from the app zone', function () {
    config(['escalated.sla.business_hours.timezone' => 'America/New_York']);
    $this->travelTo(Carbon::parse('2026-10-26 12:00:00', 'UTC')); // 08:00 in New York

    $ticket = slaAttached(slaPolicy(), $this->department);

    // 10:00 in New York (EDT) is 14:00 UTC.
    expect(slaAt($ticket->first_response_due_at))->toBe('2026-10-26 14:00');
});

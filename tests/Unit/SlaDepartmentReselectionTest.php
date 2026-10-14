<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Carbon;

/*
 * With escalated.sla.targeted on, moving a ticket to another department
 * selects the policy that department would have got at creation, and
 * re-anchors the deadlines on the time of the move.
 */

beforeEach(function () {
    config(['escalated.sla.targeted' => true]);
    $this->createdAt = Carbon::parse('2026-10-14 08:00:00');
    $this->travelTo($this->createdAt);

    $this->fulfillment = Department::factory()->create(['name' => 'Fulfillment', 'slug' => 'fulfillment']);
    $this->delivery = Department::factory()->create(['name' => 'Delivery', 'slug' => 'delivery']);
    $this->fulfillmentPolicy = SlaPolicy::factory()->create([
        'department_id' => $this->fulfillment->id,
        'first_response_hours' => ['medium' => 8],
        'resolution_hours' => ['medium' => 48],
    ]);
});

function slaTicket(Department $department, array $attributes = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'department_id' => $department->id, 'priority' => 'medium', 'status' => TicketStatus::Open,
    ], $attributes))->fresh();
}

it('selects the new department policy and anchors deadlines on the change time', function () {
    $delivery = SlaPolicy::factory()->create([
        'department_id' => $this->delivery->id,
        'first_response_hours' => ['medium' => 2],
        'resolution_hours' => ['medium' => 10],
    ]);
    $ticket = slaTicket($this->fulfillment);
    expect($ticket->sla_policy_id)->toBe($this->fulfillmentPolicy->id);

    $this->travel(3)->hours();
    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBe($delivery->id)
        ->and($moved->first_response_due_at->equalTo($this->createdAt->copy()->addHours(5)))->toBeTrue()
        ->and($moved->resolution_due_at->equalTo($this->createdAt->copy()->addHours(13)))->toBeTrue();
});

it('clears a due date the new policy does not set for the priority', function () {
    $delivery = SlaPolicy::factory()->create([
        'department_id' => $this->delivery->id,
        'first_response_hours' => ['medium' => 2],
        'resolution_hours' => ['high' => 10],
    ]);
    $ticket = slaTicket($this->fulfillment);

    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBe($delivery->id)
        ->and($moved->first_response_due_at)->not->toBeNull()
        ->and($moved->resolution_due_at)->toBeNull();
});

it('leaves the deadlines alone when the policy does not change', function () {
    $this->fulfillmentPolicy->delete();
    $default = SlaPolicy::factory()->default()->create([
        'first_response_hours' => ['medium' => 4], 'resolution_hours' => ['medium' => 24],
    ]);
    $ticket = slaTicket($this->fulfillment);
    expect($ticket->sla_policy_id)->toBe($default->id);

    $this->travel(3)->hours();
    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBe($default->id)
        ->and($moved->first_response_due_at->equalTo($this->createdAt->copy()->addHours(4)))->toBeTrue()
        ->and($moved->resolution_due_at->equalTo($this->createdAt->copy()->addHours(24)))->toBeTrue();
});

it('detaches the policy and clears both deadlines when nothing matches', function () {
    $ticket = slaTicket($this->fulfillment);

    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBeNull()
        ->and($moved->first_response_due_at)->toBeNull()
        ->and($moved->resolution_due_at)->toBeNull();
});

it('falls back to the untargeted default policy', function () {
    $default = SlaPolicy::factory()->default()->create([
        'first_response_hours' => ['medium' => 1], 'resolution_hours' => ['medium' => 6],
    ]);
    $ticket = slaTicket($this->fulfillment);
    expect($ticket->sla_policy_id)->toBe($this->fulfillmentPolicy->id);

    $this->travel(2)->hours();
    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBe($default->id)
        ->and($moved->first_response_due_at->equalTo($this->createdAt->copy()->addHours(3)))->toBeTrue()
        ->and($moved->resolution_due_at->equalTo($this->createdAt->copy()->addHours(8)))->toBeTrue();
});

it('does not touch the SLA of a closed or resolved ticket', function (TicketStatus $status) {
    SlaPolicy::factory()->create(['department_id' => $this->delivery->id]);
    $ticket = slaTicket($this->fulfillment);
    $ticket->updateQuietly(['status' => $status]);
    $before = $ticket->fresh();

    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBe($this->fulfillmentPolicy->id)
        ->and($moved->first_response_due_at->equalTo($before->first_response_due_at))->toBeTrue()
        ->and($moved->resolution_due_at->equalTo($before->resolution_due_at))->toBeTrue();
})->with([TicketStatus::Closed, TicketStatus::Resolved]);

it('keeps a first response that already happened and its deadline', function () {
    SlaPolicy::factory()->create([
        'department_id' => $this->delivery->id,
        'first_response_hours' => ['medium' => 1],
        'resolution_hours' => ['medium' => 10],
    ]);
    $ticket = slaTicket($this->fulfillment);
    $this->travel(1)->hours();
    $respondedAt = now()->toImmutable();
    $ticket->updateQuietly(['first_response_at' => $respondedAt]);
    $originalDue = $ticket->fresh()->first_response_due_at;

    $this->travel(1)->hours();
    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->first_response_at->equalTo($respondedAt))->toBeTrue()
        ->and($moved->first_response_due_at->equalTo($originalDue))->toBeTrue()
        ->and($moved->resolution_due_at->equalTo($this->createdAt->copy()->addHours(12)))->toBeTrue();
});

it('keeps a breach that was already recorded', function () {
    SlaPolicy::factory()->create(['department_id' => $this->delivery->id]);
    $ticket = slaTicket($this->fulfillment);
    $ticket->updateQuietly(['sla_first_response_breached' => true]);

    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_first_response_breached)->toBeTrue();
});

it('changes nothing while targeted SLAs are off', function () {
    config(['escalated.sla.targeted' => false]);
    $default = SlaPolicy::factory()->default()->create();
    SlaPolicy::factory()->create(['department_id' => $this->delivery->id]);
    $ticket = slaTicket($this->fulfillment);
    expect($ticket->sla_policy_id)->toBe($default->id);
    $before = $ticket->fresh();

    $this->travel(3)->hours();
    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBe($default->id)
        ->and($moved->first_response_due_at->equalTo($before->first_response_due_at))->toBeTrue();
});

it('changes nothing while SLAs are disabled', function () {
    $ticket = slaTicket($this->fulfillment);
    config(['escalated.sla.enabled' => false]);
    SlaPolicy::factory()->create(['department_id' => $this->delivery->id]);

    $moved = $ticket->changeDepartment($this->delivery, null);

    expect($moved->sla_policy_id)->toBe($this->fulfillmentPolicy->id);
});

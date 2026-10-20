<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\DepartmentChanged;
use Escalated\Laravel\Events\TicketAssigned;
use Escalated\Laravel\Events\TicketResolved;
use Escalated\Laravel\Events\TicketUpdated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Escalated\Laravel\Models\WorkflowLog;
use Illuminate\Support\Facades\Event;

function reentrantWorkflow(string $event, array $actions): Workflow
{
    return Workflow::create([
        'name' => 'Re-entrant '.$event,
        'trigger_event' => $event,
        'conditions' => [],
        'actions' => $actions,
        'is_active' => true,
        'position' => 0,
    ]);
}

it('does not re-run a ticket.updated workflow for its own writes', function () {
    $workflow = reentrantWorkflow('ticket.updated', [['type' => 'snooze_ticket', 'value' => 1]]);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $ticket->update(['subject' => 'Changed by an agent']);

    expect(WorkflowLog::where('workflow_id', $workflow->id)->count())->toBe(1)
        ->and($ticket->fresh()->snoozed_until)->not->toBeNull();
});

it('still lets one workflow event run workflows for another', function () {
    $updated = reentrantWorkflow('ticket.updated', [['type' => 'change_status', 'value' => 'in_progress']]);
    $statusChanged = reentrantWorkflow('ticket.status_changed', [['type' => 'add_tag', 'value' => 'touched']]);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $ticket->update(['subject' => 'Changed by an agent']);

    expect(WorkflowLog::where('workflow_id', $updated->id)->count())->toBe(1)
        ->and(WorkflowLog::where('workflow_id', $statusChanged->id)->count())->toBe(1)
        ->and($ticket->fresh()->tags->pluck('name')->all())->toBe(['touched']);
});

it('does not announce SLA deadline bookkeeping as ticket.updated', function () {
    SlaPolicy::factory()->create(['is_default' => true, 'is_active' => true]);
    Event::fake([TicketUpdated::class]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    expect($ticket->fresh()->sla_policy_id)->not->toBeNull();
    Event::assertNotDispatched(TicketUpdated::class);
});

it('resolves through the domain when a workflow changes status', function () {
    Event::fake([TicketResolved::class]);
    reentrantWorkflow('ticket.created', [['type' => 'change_status', 'value' => 'resolved']]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull();
    Event::assertDispatched(TicketResolved::class);
});

it('assigns and moves department through the domain from a workflow', function () {
    Event::fake([TicketAssigned::class, DepartmentChanged::class]);
    $agent = $this->createAgent();
    $department = Department::factory()->create();
    reentrantWorkflow('ticket.created', [
        ['type' => 'assign_agent', 'value' => (string) $agent->id],
        ['type' => 'set_department', 'value' => (string) $department->id],
    ]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'department_id' => null]);

    $ticket->refresh();
    expect((string) $ticket->assigned_to)->toBe((string) $agent->id)
        ->and($ticket->department_id)->toBe($department->id);
    Event::assertDispatched(TicketAssigned::class);
    Event::assertDispatched(DepartmentChanged::class);
});

it('skips a workflow status change the transitions do not allow', function () {
    reentrantWorkflow('ticket.created', [
        ['type' => 'change_status', 'value' => 'reopened'],
        ['type' => 'add_tag', 'value' => 'after'],
    ]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->tags->pluck('name')->all())->toBe(['after']);
});

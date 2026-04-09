<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\TicketStatusChanged;
use Escalated\Laravel\Listeners\CancelDelayedActionsOnClose;
use Escalated\Laravel\Models\DelayedAction;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;

it('finds pending delayed actions', function () {
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    DelayedAction::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'action' => ['type' => 'delay', 'value' => ['minutes' => 60]],
        'remaining_actions' => [['type' => 'change_priority', 'value' => 'urgent']],
        'execute_at' => now()->addHour(),
        'executed' => false,
        'cancelled' => false,
    ]);

    DelayedAction::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'action' => ['type' => 'delay', 'value' => ['minutes' => 60]],
        'remaining_actions' => [],
        'execute_at' => now()->addHour(),
        'executed' => true,
        'cancelled' => false,
    ]);

    expect(DelayedAction::pending()->count())->toBe(1);
});

it('finds due delayed actions', function () {
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    // Due
    DelayedAction::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'action' => ['type' => 'delay', 'value' => ['minutes' => 60]],
        'remaining_actions' => [['type' => 'change_priority', 'value' => 'urgent']],
        'execute_at' => now()->subMinute(),
        'executed' => false,
        'cancelled' => false,
    ]);

    // Not due yet
    DelayedAction::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'action' => ['type' => 'delay', 'value' => ['minutes' => 60]],
        'remaining_actions' => [],
        'execute_at' => now()->addHour(),
        'executed' => false,
        'cancelled' => false,
    ]);

    expect(DelayedAction::due()->count())->toBe(1);
});

it('cancels pending delayed actions when ticket is closed', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [
            ['type' => 'delay', 'value' => ['minutes' => 60]],
            ['type' => 'change_priority', 'value' => 'urgent'],
        ],
        'is_active' => true,
        'position' => 0,
    ]);

    DelayedAction::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'action' => ['type' => 'delay', 'value' => ['minutes' => 60]],
        'remaining_actions' => [['type' => 'change_priority', 'value' => 'urgent']],
        'execute_at' => now()->addHour(),
        'executed' => false,
        'cancelled' => false,
    ]);

    // Manually trigger the listener
    $ticket->update(['status' => TicketStatus::Closed, 'closed_at' => now()]);

    $listener = app(CancelDelayedActionsOnClose::class);
    $listener->handle(new TicketStatusChanged(
        $ticket,
        TicketStatus::Open,
        TicketStatus::Closed,
    ));

    expect(DelayedAction::first()->cancelled)->toBeTrue();
});

it('processes delayed actions via artisan command', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Low]);
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    DelayedAction::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'action' => ['type' => 'delay', 'value' => ['minutes' => 60]],
        'remaining_actions' => [['type' => 'change_priority', 'value' => 'urgent']],
        'execute_at' => now()->subMinute(),
        'executed' => false,
        'cancelled' => false,
    ]);

    $this->artisan('escalated:process-delayed-actions')
        ->assertExitCode(0);

    $delayed = DelayedAction::first();
    expect($delayed->executed)->toBeTrue();
    expect($ticket->fresh()->priority)->toBe(TicketPriority::Urgent);
});

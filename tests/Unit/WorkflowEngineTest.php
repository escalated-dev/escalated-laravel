<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\DelayedAction;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Escalated\Laravel\Models\WorkflowLog;
use Escalated\Laravel\Services\WorkflowEngine;

beforeEach(function () {
    $this->engine = app(WorkflowEngine::class);
});

// --- Condition Evaluation ---

it('evaluates empty conditions as true', function () {
    $ticket = Ticket::factory()->create();

    expect($this->engine->evaluateConditions([], $ticket))->toBeTrue();
});

it('evaluates conditions with match all', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
    ]);

    $conditions = [
        'match' => 'all',
        'rules' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'open'],
            ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
        ],
    ];

    expect($this->engine->evaluateConditions($conditions, $ticket))->toBeTrue();
});

it('fails match all when one condition fails', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Low,
    ]);

    $conditions = [
        'match' => 'all',
        'rules' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'open'],
            ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
        ],
    ];

    expect($this->engine->evaluateConditions($conditions, $ticket))->toBeFalse();
});

it('evaluates conditions with match any', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Low,
    ]);

    $conditions = [
        'match' => 'any',
        'rules' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'open'],
            ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
        ],
    ];

    expect($this->engine->evaluateConditions($conditions, $ticket))->toBeTrue();
});

// --- Operators ---

it('supports equals operator', function () {
    expect($this->engine->compareValues('open', 'equals', 'open'))->toBeTrue();
    expect($this->engine->compareValues('open', 'equals', 'closed'))->toBeFalse();
});

it('supports not_equals operator', function () {
    expect($this->engine->compareValues('open', 'not_equals', 'closed'))->toBeTrue();
    expect($this->engine->compareValues('open', 'not_equals', 'open'))->toBeFalse();
});

it('supports contains operator for strings', function () {
    expect($this->engine->compareValues('billing issue', 'contains', 'billing'))->toBeTrue();
    expect($this->engine->compareValues('billing issue', 'contains', 'refund'))->toBeFalse();
});

it('supports contains operator for arrays', function () {
    expect($this->engine->compareValues(['billing', 'vip'], 'contains', 'billing'))->toBeTrue();
    expect($this->engine->compareValues(['billing', 'vip'], 'contains', 'refund'))->toBeFalse();
});

it('supports not_contains operator', function () {
    expect($this->engine->compareValues('billing issue', 'not_contains', 'refund'))->toBeTrue();
    expect($this->engine->compareValues('billing issue', 'not_contains', 'billing'))->toBeFalse();
});

it('supports in operator', function () {
    expect($this->engine->compareValues('high', 'in', ['high', 'urgent']))->toBeTrue();
    expect($this->engine->compareValues('low', 'in', ['high', 'urgent']))->toBeFalse();
});

it('supports not_in operator', function () {
    expect($this->engine->compareValues('low', 'not_in', ['high', 'urgent']))->toBeTrue();
    expect($this->engine->compareValues('high', 'not_in', ['high', 'urgent']))->toBeFalse();
});

it('supports greater_than operator', function () {
    expect($this->engine->compareValues(5, 'greater_than', 3))->toBeTrue();
    expect($this->engine->compareValues(2, 'greater_than', 3))->toBeFalse();
});

it('supports less_than operator', function () {
    expect($this->engine->compareValues(2, 'less_than', 3))->toBeTrue();
    expect($this->engine->compareValues(5, 'less_than', 3))->toBeFalse();
});

it('supports greater_than_or_equal operator', function () {
    expect($this->engine->compareValues(3, 'greater_than_or_equal', 3))->toBeTrue();
    expect($this->engine->compareValues(4, 'greater_than_or_equal', 3))->toBeTrue();
    expect($this->engine->compareValues(2, 'greater_than_or_equal', 3))->toBeFalse();
});

it('supports less_than_or_equal operator', function () {
    expect($this->engine->compareValues(3, 'less_than_or_equal', 3))->toBeTrue();
    expect($this->engine->compareValues(2, 'less_than_or_equal', 3))->toBeTrue();
    expect($this->engine->compareValues(4, 'less_than_or_equal', 3))->toBeFalse();
});

it('supports is_empty operator', function () {
    expect($this->engine->compareValues(null, 'is_empty', null))->toBeTrue();
    expect($this->engine->compareValues('', 'is_empty', null))->toBeTrue();
    expect($this->engine->compareValues('something', 'is_empty', null))->toBeFalse();
});

it('supports is_not_empty operator', function () {
    expect($this->engine->compareValues('something', 'is_not_empty', null))->toBeTrue();
    expect($this->engine->compareValues(null, 'is_not_empty', null))->toBeFalse();
});

it('supports matches (regex) operator', function () {
    expect($this->engine->compareValues('BILLING-123', 'matches', '/^BILLING-\d+$/'))->toBeTrue();
    expect($this->engine->compareValues('SUPPORT-123', 'matches', '/^BILLING-\d+$/'))->toBeFalse();
});

// --- Field Resolution ---

it('resolves status field', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    expect($this->engine->resolveFieldValue('status', $ticket))->toBe('open');
});

it('resolves priority field', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Urgent]);

    expect($this->engine->resolveFieldValue('priority', $ticket))->toBe('urgent');
});

it('resolves subject field', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Test subject']);

    expect($this->engine->resolveFieldValue('subject', $ticket))->toBe('Test subject');
});

it('resolves tags field', function () {
    $ticket = Ticket::factory()->create();
    $tag = Tag::create(['name' => 'billing', 'color' => '#000']);
    $ticket->tags()->attach($tag);

    expect($this->engine->resolveFieldValue('tags', $ticket))->toContain('billing');
});

it('resolves hours_since_created field', function () {
    $ticket = Ticket::factory()->create([
        'created_at' => now()->subHours(5),
    ]);

    $value = $this->engine->resolveFieldValue('hours_since_created', $ticket);
    expect($value)->toBeGreaterThanOrEqual(4);
});

it('resolves assigned_to field', function () {
    $ticket = Ticket::factory()->create(['assigned_to' => 42]);

    expect($this->engine->resolveFieldValue('assigned_to', $ticket))->toBe(42);
});

it('resolves reply_count field', function () {
    $ticket = Ticket::factory()->create();
    $ticket->replies()->create([
        'body' => 'test reply',
        'is_internal_note' => false,
    ]);

    expect($this->engine->resolveFieldValue('reply_count', $ticket))->toBe(1);
});

it('resolves department.name field', function () {
    $department = Department::create(['name' => 'Support', 'description' => 'Support department']);
    $ticket = Ticket::factory()->create(['department_id' => $department->id]);

    expect($this->engine->resolveFieldValue('department.name', $ticket))->toBe('Support');
});

it('resolves sla.breached field', function () {
    $ticket = Ticket::factory()->breachedSla()->create();

    expect($this->engine->resolveFieldValue('sla.breached', $ticket))->toBeTrue();
});

// --- Action Execution ---

it('executes change_status action', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'change_status',
        'value' => 'in_progress',
    ]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
});

it('executes change_priority action', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Low]);
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'change_priority',
        'value' => 'urgent',
    ]);

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Urgent);
});

it('executes add_tag action', function () {
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'add_tag',
        'value' => 'escalated-billing',
    ]);

    expect($ticket->fresh()->tags->pluck('name')->toArray())->toContain('escalated-billing');
});

it('executes remove_tag action', function () {
    $ticket = Ticket::factory()->create();
    $tag = Tag::create(['name' => 'remove-me', 'color' => '#000']);
    $ticket->tags()->attach($tag);

    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'remove_tag',
        'value' => 'remove-me',
    ]);

    expect($ticket->fresh()->tags->pluck('name')->toArray())->not->toContain('remove-me');
});

it('executes assign_agent action with agent_id', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'assign_agent',
        'value' => ['agent_id' => $agent->id],
    ]);

    expect($ticket->fresh()->assigned_to)->toBe($agent->id);
});

it('executes move_department action', function () {
    $dept = Department::create(['name' => 'Billing', 'description' => '']);
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'move_department',
        'value' => ['department_id' => $dept->id],
    ]);

    expect($ticket->fresh()->department_id)->toBe($dept->id);
});

it('executes add_internal_note action', function () {
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'add_internal_note',
        'value' => 'Auto-escalated by workflow',
    ]);

    expect($ticket->replies()->where('is_internal_note', true)->count())->toBe(1);
    expect($ticket->replies()->first()->body)->toBe('Auto-escalated by workflow');
});

it('executes close_ticket action', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'close_ticket',
        'value' => null,
    ]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Closed);
});

it('executes snooze_ticket action', function () {
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->executeAction($workflow, $ticket, [
        'type' => 'snooze_ticket',
        'value' => ['hours' => 4],
    ]);

    expect($ticket->fresh()->snoozed_until)->not->toBeNull();
});

// --- Delayed Actions ---

it('creates a delayed action on delay type', function () {
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Delayed',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $actions = [
        ['type' => 'change_status', 'value' => 'in_progress'],
        ['type' => 'delay', 'value' => ['minutes' => 120]],
        ['type' => 'change_priority', 'value' => 'urgent'],
    ];

    $this->engine->executeActions($workflow, $ticket, $actions);

    // First action should have executed
    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);

    // Delayed action should be created
    expect(DelayedAction::count())->toBe(1);
    $delayed = DelayedAction::first();
    expect($delayed->remaining_actions)->toHaveCount(1);
    expect($delayed->remaining_actions[0]['type'])->toBe('change_priority');
    expect($delayed->executed)->toBeFalse();
    expect($delayed->cancelled)->toBeFalse();
});

// --- Event Processing ---

it('processes an event and creates workflow log', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
    ]);

    $workflow = Workflow::create([
        'name' => 'Auto-escalate',
        'trigger_event' => 'ticket.created',
        'conditions' => [
            'match' => 'all',
            'rules' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
            ],
        ],
        'actions' => [
            ['type' => 'add_tag', 'value' => 'high-priority'],
        ],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->processEvent('ticket.created', $ticket);

    expect(WorkflowLog::count())->toBe(1);
    $log = WorkflowLog::first();
    expect($log->conditions_matched)->toBeTrue();
    expect($log->workflow_id)->toBe($workflow->id);
    expect($log->ticket_id)->toBe($ticket->id);
    expect($log->error)->toBeNull();

    // Workflow trigger count should have incremented
    expect($workflow->fresh()->trigger_count)->toBe(1);
});

it('does not execute actions for disabled workflows', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    Workflow::create([
        'name' => 'Disabled',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'urgent'],
        ],
        'is_active' => false,
        'position' => 0,
    ]);

    $this->engine->processEvent('ticket.created', $ticket);

    expect(WorkflowLog::count())->toBe(0);
});

it('does not execute actions when conditions do not match', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Low,
    ]);

    Workflow::create([
        'name' => 'High only',
        'trigger_event' => 'ticket.created',
        'conditions' => [
            'match' => 'all',
            'rules' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
            ],
        ],
        'actions' => [
            ['type' => 'add_tag', 'value' => 'should-not-appear'],
        ],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->processEvent('ticket.created', $ticket);

    $log = WorkflowLog::first();
    expect($log->conditions_matched)->toBeFalse();
    expect($ticket->fresh()->tags)->toHaveCount(0);
});

it('logs errors when action execution fails', function () {
    $ticket = Ticket::factory()->create();

    Workflow::create([
        'name' => 'Bad workflow',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [
            ['type' => 'send_webhook', 'value' => ['url' => 'http://invalid.test/hook', 'payload' => []]],
        ],
        'is_active' => true,
        'position' => 0,
    ]);

    // Should not throw
    $this->engine->processEvent('ticket.created', $ticket);

    expect(WorkflowLog::count())->toBe(1);
});

// --- Variable Interpolation ---

it('interpolates ticket variables in webhook payload', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'Billing Issue',
        'reference' => 'ESC-00042',
    ]);

    $result = $this->engine->interpolateVariables(
        'Ticket {{ticket.reference}}: {{ticket.subject}}',
        $ticket
    );

    expect($result)->toBe('Ticket ESC-00042: Billing Issue');
});

it('interpolates agent variables', function () {
    $agent = $this->createAgent(['name' => 'John Smith']);
    $ticket = Ticket::factory()->create(['assigned_to' => $agent->id]);

    $result = $this->engine->interpolateVariables(
        'Assigned to {{agent.name}}',
        $ticket
    );

    expect($result)->toBe('Assigned to John Smith');
});

it('interpolates nested arrays in payload', function () {
    $ticket = Ticket::factory()->create([
        'reference' => 'ESC-00099',
        'subject' => 'Test',
    ]);

    $result = $this->engine->interpolateVariables(
        ['text' => 'Ticket: {{ticket.reference}}', 'subject' => '{{ticket.subject}}'],
        $ticket
    );

    expect($result['text'])->toBe('Ticket: ESC-00099');
    expect($result['subject'])->toBe('Test');
});

// --- Dry Run ---

it('performs dry run without executing actions', function () {
    $ticket = Ticket::factory()->create([
        'priority' => TicketPriority::High,
    ]);

    $workflow = Workflow::create([
        'name' => 'Dry run test',
        'trigger_event' => 'ticket.created',
        'conditions' => [
            'match' => 'all',
            'rules' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
            ],
        ],
        'actions' => [
            ['type' => 'add_tag', 'value' => 'test-tag'],
        ],
        'is_active' => true,
        'position' => 0,
    ]);

    $result = $this->engine->dryRun($workflow, $ticket);

    expect($result['conditions_matched'])->toBeTrue();
    expect($result['condition_details'])->toHaveCount(1);
    expect($result['condition_details'][0]['passed'])->toBeTrue();
    expect($result['actions'])->toHaveCount(1);

    // Tag should NOT have been applied (dry run)
    expect($ticket->fresh()->tags)->toHaveCount(0);
});

// --- Edge Cases ---

it('handles workflow with no actions gracefully', function () {
    $ticket = Ticket::factory()->create();

    Workflow::create([
        'name' => 'No actions',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $this->engine->processEvent('ticket.created', $ticket);

    expect(WorkflowLog::count())->toBe(1);
});

it('handles unknown action types gracefully', function () {
    $ticket = Ticket::factory()->create();
    $workflow = Workflow::create([
        'name' => 'Unknown action',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [
            ['type' => 'nonexistent_action', 'value' => 'whatever'],
        ],
        'is_active' => true,
        'position' => 0,
    ]);

    // Should not throw
    $this->engine->executeActions($workflow, $ticket, $workflow->actions);
});

it('processes workflows in position order', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Low]);

    Workflow::create([
        'name' => 'Second',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'urgent'],
        ],
        'is_active' => true,
        'position' => 2,
    ]);

    Workflow::create([
        'name' => 'First',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'high'],
        ],
        'is_active' => true,
        'position' => 1,
    ]);

    $this->engine->processEvent('ticket.created', $ticket);

    // Last workflow to run wins (position 2)
    expect($ticket->fresh()->priority)->toBe(TicketPriority::Urgent);
});

<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Escalated\Laravel\Models\WorkflowLog;
use Illuminate\Support\Facades\Gate;

/**
 * The create/update body from escalated-developer-context
 * domain-model/workflow-admin-contract.md, exactly as the shared builder sends it.
 */
function workflowContractBody(array $overrides = []): array
{
    return array_merge([
        'name' => 'Route refunds to billing',
        'description' => null,
        'trigger_event' => 'ticket.created',
        'conditions' => [
            'all' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'refund']],
        ],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'high'],
            ['type' => 'set_department', 'value' => '4'],
        ],
        'is_active' => true,
    ], $overrides);
}

/**
 * Headers of an Inertia visit: a JSON body, but not a request that wants JSON back.
 */
function inertiaVisitHeaders(): array
{
    return [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ];
}

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    $this->admin = $this->createAdmin();
    $this->actingAs($this->admin);
});

it('lists all workflows', function () {
    Workflow::create([
        'name' => 'Test Workflow',
        'trigger_event' => 'ticket.created',
        'conditions' => ['match' => 'all', 'rules' => []],
        'actions' => [['type' => 'add_tag', 'value' => 'test']],
        'is_active' => true,
        'position' => 0,
    ]);

    $response = $this->get(route('escalated.admin.workflows.index'));

    $response->assertStatus(200);
});

it('stores a new workflow', function () {
    $response = $this->post(route('escalated.admin.workflows.store'), [
        'name' => 'New Workflow',
        'trigger_event' => 'ticket.created',
        'conditions' => ['match' => 'all', 'rules' => [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'open'],
        ]],
        'actions' => [
            ['type' => 'add_tag', 'value' => 'auto'],
        ],
        'is_active' => true,
    ]);

    $response->assertRedirect(route('escalated.admin.workflows.index'));

    expect(Workflow::count())->toBe(1);
    $workflow = Workflow::first();
    expect($workflow->name)->toBe('New Workflow');
    expect($workflow->trigger_event)->toBe('ticket.created');
    expect($workflow->created_by)->toBe($this->admin->id);
});

it('validates required fields on store', function () {
    $response = $this->post(route('escalated.admin.workflows.store'), []);

    // The contract lets conditions be omitted (treated as {"all": []}).
    $response->assertSessionHasErrors(['name', 'trigger_event', 'actions']);
    $response->assertSessionDoesntHaveErrors('conditions');
});

it('updates a workflow', function () {
    $workflow = Workflow::create([
        'name' => 'Old Name',
        'trigger_event' => 'ticket.created',
        'conditions' => ['match' => 'all', 'rules' => []],
        'actions' => [['type' => 'add_tag', 'value' => 'old']],
        'is_active' => true,
        'position' => 0,
    ]);

    $response = $this->put(route('escalated.admin.workflows.update', $workflow), [
        'name' => 'Updated Name',
        'trigger_event' => 'ticket.updated',
        'conditions' => ['match' => 'any', 'rules' => []],
        'actions' => [['type' => 'add_tag', 'value' => 'new']],
        'is_active' => false,
    ]);

    $response->assertRedirect(route('escalated.admin.workflows.index'));

    $workflow->refresh();
    expect($workflow->name)->toBe('Updated Name');
    expect($workflow->trigger_event)->toBe('ticket.updated');
    expect($workflow->is_active)->toBeFalse();
});

it('deletes a workflow', function () {
    $workflow = Workflow::create([
        'name' => 'To Delete',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $response = $this->delete(route('escalated.admin.workflows.destroy', $workflow));

    $response->assertRedirect(route('escalated.admin.workflows.index'));
    expect(Workflow::count())->toBe(0);
});

it('toggles a workflow', function () {
    $workflow = Workflow::create([
        'name' => 'Toggle Me',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $response = $this->post(route('escalated.admin.workflows.toggle', $workflow));

    $response->assertRedirect(route('escalated.admin.workflows.index'));
    expect($workflow->fresh()->is_active)->toBeFalse();

    $this->post(route('escalated.admin.workflows.toggle', $workflow));
    expect($workflow->fresh()->is_active)->toBeTrue();
});

it('reorders workflows', function () {
    $w1 = Workflow::create([
        'name' => 'First',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $w2 = Workflow::create([
        'name' => 'Second',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 1,
    ]);

    $response = $this->postJson(route('escalated.admin.workflows.reorder'), [
        'ids' => [$w2->id, $w1->id],
    ]);

    $response->assertJson(['success' => true]);
    expect($w1->fresh()->position)->toBe(1);
    expect($w2->fresh()->position)->toBe(0);
});

/**
 * Inactive, so creating the ticket for a log row does not run it and add rows
 * of its own.
 */
function loggedWorkflow(string $name, int $position = 0): Workflow
{
    return Workflow::create([
        'name' => $name,
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => false,
        'position' => $position,
    ]);
}

function logRun(Workflow $workflow, Ticket $ticket, ?string $error = null): WorkflowLog
{
    return WorkflowLog::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'trigger_event' => 'ticket.created',
        'conditions_matched' => true,
        'actions_executed' => [['type' => 'add_tag', 'value' => 'vip']],
        'error' => $error,
        'started_at' => now(),
        'completed_at' => now()->addMilliseconds(40),
    ]);
}

/**
 * The shared Logs page lists recent runs across every workflow, and the
 * Workflows index links to it with no workflow in the URL. The route used to
 * need one, so building that link threw and the index never rendered.
 */
it('lists recent runs across every workflow as the shared Logs page reads them', function () {
    $refunds = loggedWorkflow('Refunds', 0);
    $vip = loggedWorkflow('VIP', 1);
    $ticket = Ticket::factory()->create();

    logRun($refunds, $ticket);
    logRun($vip, $ticket, 'webhook timed out');

    $response = $this->withHeaders(inertiaVisitHeaders())
        ->get(route('escalated.admin.workflows.logs'));

    $response->assertOk();
    $page = $response->json();

    expect($page['component'])->toBe('Escalated/Admin/Workflows/Logs')
        ->and($page['props']['logs'])->toBeList()->toHaveCount(2)
        ->and($page['props']['workflows'])->toBe([
            ['id' => $refunds->id, 'name' => 'Refunds'],
            ['id' => $vip->id, 'name' => 'VIP'],
        ])
        ->and(array_keys($page['props']))->not->toContain('workflow_id', 'workflow');

    $failed = collect($page['props']['logs'])->firstWhere('workflow_name', 'VIP');

    expect($failed)
        ->toMatchArray([
            'ticket_reference' => $ticket->reference,
            'event' => 'ticket.created',
            'matched' => true,
            'status' => 'failed',
            'action_details' => [['type' => 'add_tag', 'value' => 'vip']],
        ])
        ->not->toHaveKeys(['workflow', 'ticket']);
});

it('narrows the Logs page to one workflow', function () {
    $refunds = loggedWorkflow('Refunds', 0);
    $vip = loggedWorkflow('VIP', 1);
    $ticket = Ticket::factory()->create();

    logRun($refunds, $ticket);
    logRun($vip, $ticket);

    $page = $this->withHeaders(inertiaVisitHeaders())
        ->get(route('escalated.admin.workflows.logs', ['workflow' => $vip->id]))
        ->assertOk()
        ->json();

    expect($page['props']['logs'])->toHaveCount(1)
        ->and($page['props']['logs'][0]['workflow_name'])->toBe('VIP');
});

it('sends the per-workflow logs URL of earlier releases to the filtered Logs page', function () {
    $workflow = loggedWorkflow('Logged');

    $this->get(route('escalated.admin.workflows.workflow-logs', $workflow))
        ->assertRedirect(route('escalated.admin.workflows.logs', ['workflow' => $workflow->id]));
});

it('performs dry-run test against a ticket', function () {
    $ticket = Ticket::factory()->create([
        'status' => 'open',
        'priority' => 'high',
    ]);

    $workflow = Workflow::create([
        'name' => 'Test Run',
        'trigger_event' => 'ticket.created',
        'conditions' => [
            'match' => 'all',
            'rules' => [
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
            ],
        ],
        'actions' => [
            ['type' => 'add_tag', 'value' => 'dry-run-tag'],
        ],
        'is_active' => true,
        'position' => 0,
    ]);

    $response = $this->postJson(route('escalated.admin.workflows.test', $workflow), [
        'ticket_id' => $ticket->id,
    ]);

    $response->assertOk();
    $response->assertJsonPath('conditions_matched', true);
    $response->assertJsonCount(1, 'actions');

    // No actual tag should have been added
    expect($ticket->fresh()->tags)->toHaveCount(0);
});

// --- workflow-admin-contract: what the shared builder sends and reads ---

it('stores the contract request body sent by an Inertia form visit', function () {
    $body = workflowContractBody();

    $response = $this->json('POST', route('escalated.admin.workflows.store'), $body, inertiaVisitHeaders());

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('escalated.admin.workflows.index'));
    $response->assertSessionHas('success');

    $workflow = Workflow::sole();
    expect($workflow->trigger_event)->toBe('ticket.created')
        ->and($workflow->conditions)->toEqual($body['conditions'])
        ->and($workflow->actions)->toEqual($body['actions'])
        ->and($workflow->description)->toBeNull()
        ->and($workflow->is_active)->toBeTrue();
});

it('runs a workflow saved from the contract body when its trigger fires', function () {
    $billing = Department::create(['name' => 'Billing', 'description' => '']);
    $body = workflowContractBody();
    $body['actions'][1]['value'] = (string) $billing->id;

    $this->json('POST', route('escalated.admin.workflows.store'), $body, inertiaVisitHeaders())
        ->assertSessionHasNoErrors();

    // Creating the ticket fires TicketCreated, which runs ticket.created workflows.
    $ticket = Ticket::factory()->create([
        'subject' => 'Please process my refund',
        'priority' => TicketPriority::Low,
        'department_id' => null,
    ]);

    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::High)
        ->and($ticket->department_id)->toEqual($billing->id);
});

it('does not run a canonical all condition against a ticket that does not match', function () {
    $body = workflowContractBody([
        'actions' => [['type' => 'change_priority', 'value' => 'high']],
    ]);

    $this->json('POST', route('escalated.admin.workflows.store'), $body, inertiaVisitHeaders())
        ->assertSessionHasNoErrors();

    $ticket = Ticket::factory()->create([
        'subject' => 'Cannot log in',
        'priority' => TicketPriority::Low,
    ]);

    expect($ticket->fresh()->priority)->toBe(TicketPriority::Low);
});

it('stores omitted conditions as an empty all group', function () {
    $body = workflowContractBody();
    unset($body['conditions']);

    $this->json('POST', route('escalated.admin.workflows.store'), $body, inertiaVisitHeaders())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('escalated.admin.workflows.index'));

    expect(Workflow::sole()->conditions)->toEqual(['all' => []]);
});

it('accepts every core and optional action type in the contract', function () {
    $actions = [
        ['type' => 'change_status', 'value' => 'open'],
        ['type' => 'change_priority', 'value' => 'high'],
        ['type' => 'add_tag', 'value' => 'vip'],
        ['type' => 'remove_tag', 'value' => 'spam'],
        ['type' => 'set_department', 'value' => '4'],
        ['type' => 'assign_agent', 'value' => '1'],
        ['type' => 'add_note', 'value' => 'Routed {{subject}}'],
        ['type' => 'insert_canned_reply', 'value' => 'Thanks, we are on it.'],
        ['type' => 'add_follower', 'value' => '1'],
        ['type' => 'delay', 'value' => '30'],
        ['type' => 'send_webhook', 'value' => 'https://hooks.example.com/escalated'],
    ];

    $this->json('POST', route('escalated.admin.workflows.store'), workflowContractBody(['actions' => $actions]), inertiaVisitHeaders())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('escalated.admin.workflows.index'));

    expect(Workflow::sole()->actions)->toEqual($actions);
});

it('still accepts the legacy condition shape and action names', function () {
    $body = workflowContractBody([
        'conditions' => ['match' => 'any', 'rules' => [
            ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
        ]],
        'actions' => [
            ['type' => 'move_department', 'value' => '4'],
            ['type' => 'add_internal_note', 'value' => 'Escalated'],
            ['type' => 'snooze_ticket', 'value' => '2'],
        ],
    ]);

    $this->json('POST', route('escalated.admin.workflows.store'), $body, inertiaVisitHeaders())
        ->assertSessionHasNoErrors();

    expect(Workflow::sole()->conditions)->toEqual($body['conditions']);
});

it('rejects a contract body with no actions', function () {
    $this->json('POST', route('escalated.admin.workflows.store'), workflowContractBody(['actions' => []]), inertiaVisitHeaders())
        ->assertSessionHasErrors('actions');

    expect(Workflow::count())->toBe(0);
});

it('updates a workflow from the contract request body', function () {
    $workflow = Workflow::create([
        'name' => 'Old',
        'trigger_event' => 'ticket.updated',
        'conditions' => ['match' => 'all', 'rules' => []],
        'actions' => [['type' => 'add_tag', 'value' => 'old']],
        'is_active' => false,
        'position' => 0,
    ]);
    $body = workflowContractBody(['description' => 'Refunds belong to billing']);

    $this->json('PUT', route('escalated.admin.workflows.update', $workflow), $body, inertiaVisitHeaders())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('escalated.admin.workflows.index'));

    $workflow->refresh();
    expect($workflow->name)->toBe('Route refunds to billing')
        ->and($workflow->description)->toBe('Refunds belong to billing')
        ->and($workflow->trigger_event)->toBe('ticket.created')
        ->and($workflow->conditions)->toEqual($body['conditions'])
        ->and($workflow->actions)->toEqual($body['actions'])
        ->and($workflow->is_active)->toBeTrue();
});

it('renders the create form with the option lists this backend supports', function () {
    $props = $this->get(route('escalated.admin.workflows.create'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props)->toHaveKey('workflow')
        ->and($props['workflow'])->toBeNull()
        ->and($props)->not->toHaveKey('triggerEvents');

    foreach (['trigger_events', 'action_types', 'operators'] as $list) {
        expect($props)->toHaveKey($list);
        foreach ($props[$list] as $option) {
            expect($option)->toHaveKeys(['value', 'label'])
                ->and($option['value'])->toBeString()
                ->and($option['label'])->toBeString();
        }
    }

    // Exactly the events ProcessWorkflows fires.
    expect(array_column($props['trigger_events'], 'value'))->toEqualCanonicalizing([
        'ticket.created', 'ticket.updated', 'ticket.replied', 'ticket.status_changed', 'ticket.assigned',
        'ticket.escalated', 'sla.breached', 'sla.warning', 'chat.started', 'chat.ended',
    ]);

    // The core catalog plus the optional actions this executor handles.
    expect(array_column($props['action_types'], 'value'))->toEqualCanonicalizing([
        'change_status', 'change_priority', 'add_tag', 'remove_tag', 'set_department', 'assign_agent',
        'add_note', 'insert_canned_reply', 'add_follower', 'delay', 'send_webhook',
    ]);

    expect(array_column($props['operators'], 'value'))->toContain(
        'equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with',
        'greater_than', 'less_than', 'greater_or_equal', 'less_or_equal', 'is_empty', 'is_not_empty',
    );
});

it('renders the edit form with the workflow and the option lists', function () {
    $workflow = Workflow::create([
        'name' => 'Existing',
        'trigger_event' => 'ticket.created',
        'conditions' => ['all' => []],
        'actions' => [['type' => 'add_tag', 'value' => 'x']],
        'is_active' => true,
        'position' => 0,
    ]);

    $props = $this->get(route('escalated.admin.workflows.edit', $workflow))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['workflow']['id'])->toBe($workflow->id)
        ->and($props['workflow']['trigger_event'])->toBe('ticket.created')
        ->and($props['trigger_events'])->not->toBeEmpty()
        ->and($props['action_types'])->not->toBeEmpty()
        ->and($props)->not->toHaveKey('triggerEvents');
});

it('serializes workflows on the index page with the contract keys', function () {
    Workflow::create([
        'name' => 'Canonical',
        'trigger_event' => 'ticket.created',
        'conditions' => ['all' => [['field' => 'priority', 'operator' => 'equals', 'value' => 'high']]],
        'actions' => [['type' => 'add_note', 'value' => 'hi']],
        'is_active' => true,
        'position' => 3,
    ]);

    $workflows = $this->get(route('escalated.admin.workflows.index'))
        ->assertOk()
        ->viewData('page')['props']['workflows'];

    expect($workflows)->toHaveCount(1)
        ->and($workflows[0])->toHaveKeys(['id', 'name', 'description', 'trigger_event', 'conditions', 'actions', 'is_active', 'position'])
        ->and($workflows[0]['is_active'])->toBeTrue()
        ->and($workflows[0]['position'])->toBe(3)
        ->and($workflows[0]['conditions'])->toEqual(['all' => [['field' => 'priority', 'operator' => 'equals', 'value' => 'high']]]);
});

it('reorders workflows from the contract workflow_ids body', function () {
    $make = fn (string $name, int $position) => Workflow::create([
        'name' => $name,
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => $position,
    ]);
    $w1 = $make('One', 0);
    $w2 = $make('Two', 1);
    $w3 = $make('Three', 2);

    $this->json('POST', route('escalated.admin.workflows.reorder'), ['workflow_ids' => [$w3->id, $w1->id, $w2->id]], inertiaVisitHeaders())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('escalated.admin.workflows.index'));

    expect($w3->fresh()->position)->toBe(0)
        ->and($w1->fresh()->position)->toBe(1)
        ->and($w2->fresh()->position)->toBe(2);
});

it('requires ticket_id for dry-run test', function () {
    $workflow = Workflow::create([
        'name' => 'Test',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $response = $this->postJson(route('escalated.admin.workflows.test', $workflow), []);

    $response->assertStatus(422);
});

/*
 * The Logs table's "Actions" column prints `actions_executed` as a count, as
 * the NestJS reference sends it; the list itself is `action_details`. The
 * page was handed the stored array there and rendered it raw.
 */
it('sends the Logs page an action count, with the actions as details', function () {
    $workflow = loggedWorkflow('Refunds', 0);
    $ticket = Ticket::factory()->create();
    WorkflowLog::create([
        'workflow_id' => $workflow->id,
        'ticket_id' => $ticket->id,
        'trigger_event' => 'ticket.created',
        'conditions_matched' => true,
        'actions_executed' => [['type' => 'add_tag', 'value' => 'vip'], ['type' => 'assign', 'value' => 3]],
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $log = $this->withHeaders(inertiaVisitHeaders())
        ->get(route('escalated.admin.workflows.logs'))
        ->json('props.logs.0');

    expect($log['actions_executed'])->toBe(2)
        ->and($log['action_details'])->toHaveCount(2);
});

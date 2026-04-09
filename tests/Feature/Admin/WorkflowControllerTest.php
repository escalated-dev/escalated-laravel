<?php

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Illuminate\Support\Facades\Gate;

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

    $response->assertSessionHasErrors(['name', 'trigger_event', 'conditions', 'actions']);
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

it('shows workflow logs', function () {
    $workflow = Workflow::create([
        'name' => 'Logged',
        'trigger_event' => 'ticket.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
        'position' => 0,
    ]);

    $response = $this->get(route('escalated.admin.workflows.logs', $workflow));

    $response->assertStatus(200);
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

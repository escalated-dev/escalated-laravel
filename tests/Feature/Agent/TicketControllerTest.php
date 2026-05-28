<?php

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\InternalNoteAdded;
use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Events\TicketCustomActionTriggered;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketActionRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('shows agent dashboard', function () {
    $agent = $this->createAgent();

    $this->actingAs($agent)
        ->get(route('escalated.agent.dashboard'))
        ->assertOk();
});

it('lists tickets for agent', function () {
    $agent = $this->createAgent();
    Ticket::factory()->count(5)->create();

    $this->actingAs($agent)
        ->get(route('escalated.agent.tickets.index'))
        ->assertOk();
});

it('shows a ticket for agent', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->get(route('escalated.agent.tickets.show', $ticket->reference))
        ->assertOk();
});

it('exposes configured custom actions on agent ticket show', function () {
    $this->app->bind(EscalatedUiRenderer::class, fn () => new class implements EscalatedUiRenderer
    {
        public function render(string $page, array $props = []): mixed
        {
            return response()->json($props);
        }
    });

    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    app(TicketActionRegistry::class)->register([
        'key' => 'sync-crm',
        'label' => 'Sync CRM',
        'variant' => 'primary',
        'confirmation' => 'Sync this ticket to the CRM?',
        'metadata' => ['icon' => 'refresh-cw'],
    ]);

    $this->actingAs($agent)
        ->get(route('escalated.agent.tickets.show', $ticket->reference))
        ->assertOk()
        ->assertJsonPath('customActions.0.key', 'sync-crm')
        ->assertJsonPath('customActions.0.label', 'Sync CRM')
        ->assertJsonPath('customActions.0.variant', 'primary')
        ->assertJsonPath('customActions.0.confirmation', 'Sync this ticket to the CRM?')
        ->assertJsonPath('customActions.0.metadata.icon', 'refresh-cw')
        ->assertJsonPath('customActions.0.method', 'post');
});

it('agent can reply to ticket', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.reply', $ticket->reference), [
            'body' => 'Agent reply here.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_replies', [
        'ticket_id' => $ticket->id,
        'body' => 'Agent reply here.',
        'is_internal_note' => false,
    ]);
});

it('agent can add internal note', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.note', $ticket->reference), [
            'body' => 'Internal note for agents.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_replies', [
        'ticket_id' => $ticket->id,
        'is_internal_note' => true,
    ]);
});

it('internal note does not fire reply created event', function () {

    Event::fake([
        ReplyCreated::class,
        InternalNoteAdded::class,
    ]);

    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.note', $ticket->reference), [
            'body' => 'Internal note for agents.',
        ]);

    Event::assertNotDispatched(ReplyCreated::class);
    Event::assertDispatched(InternalNoteAdded::class);

});

it('agent can assign ticket', function () {
    $agent = $this->createAgent();
    $otherAgent = $this->createAgent(['email' => 'agent2@example.com']);
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.assign', $ticket->reference), [
            'agent_id' => $otherAgent->id,
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($otherAgent->id);
});

it('agent can change status', function () {
    config(['escalated.transitions' => [
        'open' => ['in_progress', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
    ]]);

    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.status', $ticket->reference), [
            'status' => 'in_progress',
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::InProgress);
});

it('agent can change priority', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Low]);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.priority', $ticket->reference), [
            'priority' => 'high',
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->priority)->toBe(TicketPriority::High);
});

it('dispatches custom ticket action events for configured actions', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    app(TicketActionRegistry::class)->register([
        'key' => 'sync-crm',
        'label' => 'Sync CRM',
        'metadata' => fn (Ticket $ticket) => ['reference' => $ticket->reference],
    ]);

    Event::fake([TicketCustomActionTriggered::class]);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.custom-action', [$ticket->reference, 'sync-crm']), [
            'payload' => ['force' => true],
        ])
        ->assertRedirect();

    Event::assertDispatched(TicketCustomActionTriggered::class, function (TicketCustomActionTriggered $event) use ($ticket, $agent) {
        return $event->ticket->is($ticket)
            && $event->action === 'sync-crm'
            && $event->user->getKey() === $agent->getKey()
            && $event->payload === ['force' => true]
            && $event->metadata === ['reference' => $ticket->reference];
    });
});

it('records an internal note when a custom ticket action is triggered', function () {
    $agent = $this->createAgent(['name' => 'Action Agent']);
    $ticket = Ticket::factory()->create();

    app(TicketActionRegistry::class)->register([
        'key' => 'sync-crm',
        'label' => 'Sync CRM',
    ]);

    Event::fake([InternalNoteAdded::class]);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.custom-action', [$ticket->reference, 'sync-crm']))
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_replies', [
        'ticket_id' => $ticket->id,
        'author_type' => $agent->getMorphClass(),
        'author_id' => $agent->getKey(),
        'body' => 'Custom action "sync-crm" was triggered by Action Agent.',
        'is_internal_note' => true,
        'type' => 'note',
    ]);

    Event::assertDispatched(InternalNoteAdded::class);
});

it('does not dispatch disabled custom ticket actions', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    app(TicketActionRegistry::class)->register([
        'key' => 'sync-crm',
        'label' => 'Sync CRM',
        'enabled' => false,
    ]);

    Event::fake([TicketCustomActionTriggered::class]);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.custom-action', [$ticket->reference, 'sync-crm']))
        ->assertForbidden();

    Event::assertNotDispatched(TicketCustomActionTriggered::class);
});

it('denies non-agent access to agent routes', function () {
    $user = $this->createTestUser();

    $this->actingAs($user)
        ->get(route('escalated.agent.dashboard'))
        ->assertForbidden();
});

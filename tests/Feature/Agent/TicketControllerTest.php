<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\InternalNoteAdded;
use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Models\Ticket;
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

it('denies non-agent access to agent routes', function () {
    $user = $this->createTestUser();

    $this->actingAs($user)
        ->get(route('escalated.agent.dashboard'))
        ->assertForbidden();
});

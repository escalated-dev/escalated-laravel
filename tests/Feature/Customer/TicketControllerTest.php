<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    Route::get('/login', fn () => 'login')->name('login');
});

it('lists customer tickets', function () {
    $user = $this->createTestUser();
    Ticket::factory()->count(3)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('escalated.customer.tickets.index'))
        ->assertOk();
});

it('shows create ticket form', function () {
    $user = $this->createTestUser();

    $this->actingAs($user)
        ->get(route('escalated.customer.tickets.create'))
        ->assertOk();
});

it('creates a new ticket', function () {
    $user = $this->createTestUser();

    $this->actingAs($user)
        ->post(route('escalated.customer.tickets.store'), [
            'subject' => 'Test ticket',
            'description' => 'This is a test ticket description.',
            'priority' => 'medium',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_tickets', [
        'subject' => 'Test ticket',
        'requester_id' => $user->id,
    ]);
});

it('shows a ticket', function () {
    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('escalated.customer.tickets.show', $ticket->reference))
        ->assertOk();
});

it('replies to a ticket', function () {
    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('escalated.customer.tickets.reply', $ticket->reference), [
            'body' => 'This is my reply.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_replies', [
        'ticket_id' => $ticket->id,
        'body' => 'This is my reply.',
    ]);
});

it('closes a ticket', function () {
    config(['escalated.transitions' => [
        'open' => ['in_progress', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
    ]]);

    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->id,
        'status' => TicketStatus::Open,
    ]);

    $this->actingAs($user)
        ->post(route('escalated.customer.tickets.close', $ticket->reference))
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Closed);
});

it('requires authentication', function () {
    $this->get(route('escalated.customer.tickets.index'))
        ->assertRedirect();
});

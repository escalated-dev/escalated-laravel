<?php

use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    Route::get('/login', fn () => 'login')->name('login');
});

it('lets the requester rate their resolved ticket', function () {
    $owner = $this->createTestUser(['email' => 'owner@example.com']);
    $ticket = Ticket::factory()->resolved()->create([
        'requester_type' => $owner->getMorphClass(),
        'requester_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('escalated.customer.tickets.rate', $ticket), ['rating' => 5])
        ->assertRedirect();

    expect(SatisfactionRating::where('ticket_id', $ticket->id)->value('rating'))->toBe(5);
});

it('forbids a customer from rating another customer\'s ticket', function () {
    $owner = $this->createTestUser(['email' => 'owner@example.com']);
    $other = $this->createTestUser(['email' => 'other@example.com']);
    $ticket = Ticket::factory()->resolved()->create([
        'requester_type' => $owner->getMorphClass(),
        'requester_id' => $owner->id,
    ]);

    $this->actingAs($other)
        ->post(route('escalated.customer.tickets.rate', $ticket), ['rating' => 1, 'comment' => 'Not mine'])
        ->assertForbidden();

    expect(SatisfactionRating::where('ticket_id', $ticket->id)->exists())->toBeFalse();
});

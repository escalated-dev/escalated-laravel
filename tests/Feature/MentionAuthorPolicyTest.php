<?php

use Escalated\Laravel\Models\Mention;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\MentionNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/*
 * Only staff-authored content (agent/admin replies and internal notes)
 * triggers @mention notifications. A customer or guest naming an agent
 * must not be able to ping them.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    Notification::fake();
});

it('does not notify an agent named in a customer reply', function () {
    $agent = $this->createAgent(['name' => 'Dana', 'email' => 'dana@example.com']);
    $customer = $this->createTestUser(['name' => 'Customer', 'email' => 'customer@example.com']);
    $ticket = Ticket::factory()->create([
        'requester_type' => $customer->getMorphClass(),
        'requester_id' => $customer->id,
    ]);

    $this->actingAs($customer)
        ->post(route('escalated.customer.tickets.reply', $ticket->reference), [
            'body' => 'Hey @Dana and @{Dana}, please look at this now.',
        ])
        ->assertRedirect();

    expect(Mention::count())->toBe(0);
    Notification::assertNotSentTo($agent, MentionNotification::class);
});

it('does not notify an agent named in a guest reply', function () {
    $agent = $this->createAgent(['name' => 'Dana', 'email' => 'dana@example.com']);
    $ticket = Ticket::factory()->create(['requester_type' => null, 'requester_id' => null]);
    $token = $this->guestToken($ticket);

    $this->post(route('escalated.guest.tickets.reply', $token), [
        'body' => 'Hi @Dana, are you there?',
    ])->assertRedirect();

    expect(Mention::count())->toBe(0);
    Notification::assertNotSentTo($agent, MentionNotification::class);
});

it('notifies an agent named in another agent internal note', function () {
    $mentioned = $this->createAgent(['name' => 'Dana', 'email' => 'dana@example.com']);
    $author = $this->createAgent(['name' => 'Sam', 'email' => 'sam@example.com']);
    $ticket = Ticket::factory()->create();

    $this->actingAs($author)
        ->post(route('escalated.agent.tickets.note', $ticket->reference), [
            'body' => 'Can you take this, @Dana?',
        ])
        ->assertRedirect();

    expect(Mention::where('user_id', $mentioned->id)->count())->toBe(1);
    Notification::assertSentTo($mentioned, MentionNotification::class);
});

it('notifies an agent named in another agent public reply', function () {
    $mentioned = $this->createAgent(['name' => 'Dana', 'email' => 'dana@example.com']);
    $author = $this->createAgent(['name' => 'Sam', 'email' => 'sam@example.com']);
    $ticket = Ticket::factory()->create();

    $this->actingAs($author)
        ->post(route('escalated.agent.tickets.reply', $ticket->reference), [
            'body' => 'Looping in @{Dana}.',
        ])
        ->assertRedirect();

    Notification::assertSentTo($mentioned, MentionNotification::class);
});

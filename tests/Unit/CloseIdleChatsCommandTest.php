<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatEnded;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([ChatStarted::class, ChatEnded::class]);
});

it('closes idle chat sessions past the timeout', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);

    $session = ChatSession::factory()->active()->create([
        'ticket_id' => $ticket->id,
        'started_at' => now()->subMinutes(60),
    ]);

    $this->artisan('escalated:close-idle-chats')
        ->expectsOutputToContain('Closed 1 idle chat session(s)')
        ->assertSuccessful();

    $session->refresh();
    expect($session->status)->toBe(ChatSessionStatus::Ended);
});

it('does not close active sessions with recent messages', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);

    $session = ChatSession::factory()->active()->create([
        'ticket_id' => $ticket->id,
        'started_at' => now()->subMinutes(5),
    ]);

    // Add a recent reply
    $ticket->replies()->create([
        'body' => 'Recent message',
        'is_internal_note' => false,
        'type' => 'reply',
    ]);

    $this->artisan('escalated:close-idle-chats')
        ->expectsOutputToContain('Closed 0 idle chat session(s)')
        ->assertSuccessful();

    $session->refresh();
    expect($session->status)->toBe(ChatSessionStatus::Active);
});

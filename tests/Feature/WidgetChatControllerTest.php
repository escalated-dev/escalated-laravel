<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatEnded;
use Escalated\Laravel\Events\ChatMessage;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Events\ChatTyping;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([
        ChatStarted::class,
        ChatAssigned::class,
        ChatEnded::class,
        ChatMessage::class,
        ChatTyping::class,
    ]);
    EscalatedSettings::set('chat_enabled', '1');
});

it('checks chat availability', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    $response = $this->getJson('/support/widget/chat/availability');
    $response->assertOk();
    $response->assertJson(['available' => true]);
});

it('returns unavailable when chat is disabled', function () {
    EscalatedSettings::set('chat_enabled', '0');

    $response = $this->getJson('/support/widget/chat/availability');
    $response->assertOk();
    $response->assertJson(['available' => false]);
});

it('starts a new chat session', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    $response = $this->postJson('/support/widget/chat/start', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'subject' => 'Need help',
        'message' => 'Hello',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['session_id', 'ticket_reference', 'status']);

    $ticket = Ticket::latest('id')->first();
    expect($ticket->status)->toBe(TicketStatus::Live);
});

it('sends a customer message', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/support/widget/chat/{$session->customer_session_id}/message", [
        'body' => 'Hello from customer!',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['message', 'reply_id']);
});

it('rejects message on ended chat', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed, 'channel' => 'chat']);
    $session = ChatSession::factory()->ended()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/support/widget/chat/{$session->customer_session_id}/message", [
        'body' => 'Hello',
    ]);

    $response->assertStatus(422);
});

it('customer ends a chat', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/support/widget/chat/{$session->customer_session_id}/end");
    $response->assertOk();

    $session->refresh();
    expect($session->status)->toBe(ChatSessionStatus::Ended);
});

it('rates a completed chat', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed, 'channel' => 'chat']);
    $session = ChatSession::factory()->ended()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/support/widget/chat/{$session->customer_session_id}/rate", [
        'rating' => 5,
        'comment' => 'Great help!',
    ]);

    $response->assertOk();

    $session->refresh();
    expect($session->rating)->toBe(5);
    expect($session->rating_comment)->toBe('Great help!');
});

it('rejects rating on active chat', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/support/widget/chat/{$session->customer_session_id}/rate", [
        'rating' => 5,
    ]);

    $response->assertStatus(422);
});

it('sends customer typing indicator', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/support/widget/chat/{$session->customer_session_id}/typing");
    $response->assertOk();

    $session->refresh();
    expect($session->customer_typing_at)->not->toBeNull();
});

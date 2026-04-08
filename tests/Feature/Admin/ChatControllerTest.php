<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatEnded;
use Escalated\Laravel\Events\ChatMessage;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Events\ChatTransferred;
use Escalated\Laravel\Events\ChatTyping;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Event::fake([
        ChatStarted::class,
        ChatAssigned::class,
        ChatEnded::class,
        ChatMessage::class,
        ChatTyping::class,
        ChatTransferred::class,
    ]);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin ?? false);
    Gate::define('escalated-agent', fn ($user) => $user->is_agent ?? false);
});

it('lists active chats for current agent', function () {
    $admin = $this->createAdmin(['is_agent' => true]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    ChatSession::factory()->active($admin->id)->create(['ticket_id' => $ticket->id]);

    $response = $this->actingAs($admin)->getJson('/support/admin/chat/active');
    $response->assertOk();
    $response->assertJsonCount(1, 'sessions');
});

it('lists waiting chats in queue', function () {
    $admin = $this->createAdmin(['is_agent' => true]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    ChatSession::factory()->waiting()->create(['ticket_id' => $ticket->id]);

    $response = $this->actingAs($admin)->getJson('/support/admin/chat/queue');
    $response->assertOk();
    $response->assertJsonCount(1, 'sessions');
});

it('accepts a chat from the queue', function () {
    $admin = $this->createAdmin(['is_agent' => true]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->waiting()->create(['ticket_id' => $ticket->id]);

    $response = $this->actingAs($admin)->postJson("/support/admin/chat/{$session->id}/accept");
    $response->assertOk();

    $session->refresh();
    expect($session->status)->toBe(ChatSessionStatus::Active);
    expect($session->agent_id)->toBe($admin->id);
});

it('ends a chat session', function () {
    $admin = $this->createAdmin(['is_agent' => true]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active($admin->id)->create(['ticket_id' => $ticket->id]);

    $response = $this->actingAs($admin)->postJson("/support/admin/chat/{$session->id}/end");
    $response->assertOk();

    $session->refresh();
    expect($session->status)->toBe(ChatSessionStatus::Ended);
});

it('transfers a chat to another agent', function () {
    $admin = $this->createAdmin(['is_agent' => true]);
    $agent2 = $this->createAgent(['email' => 'agent2@example.com']);

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Live,
        'channel' => 'chat',
        'assigned_to' => $admin->id,
    ]);
    $session = ChatSession::factory()->active($admin->id)->create(['ticket_id' => $ticket->id]);

    $response = $this->actingAs($admin)->postJson("/support/admin/chat/{$session->id}/transfer", [
        'agent_id' => $agent2->id,
    ]);
    $response->assertOk();

    $session->refresh();
    expect($session->agent_id)->toBe($agent2->id);
});

it('sends a message in a chat session', function () {
    $admin = $this->createAdmin(['is_agent' => true]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active($admin->id)->create(['ticket_id' => $ticket->id]);

    $response = $this->actingAs($admin)->postJson("/support/admin/chat/{$session->id}/message", [
        'body' => 'Hello from agent!',
    ]);
    $response->assertOk();
    $response->assertJsonStructure(['message', 'reply_id']);
});

it('updates agent chat status', function () {
    $admin = $this->createAdmin(['is_agent' => true]);
    AgentProfile::forUser($admin->id);

    $response = $this->actingAs($admin)->postJson('/support/admin/chat/status', [
        'status' => 'online',
    ]);
    $response->assertOk();

    $profile = AgentProfile::where('user_id', $admin->id)->first();
    expect($profile->chat_status)->toBe(ChatStatus::Online);
});

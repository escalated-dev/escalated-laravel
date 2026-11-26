<?php

/*
 * Agents run their own live chats through the agent routes
 * (escalated.agent.chat.*). The admin routes stay as they are.
 */

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatEnded;
use Escalated\Laravel\Events\ChatMessage;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Events\ChatTransferred;
use Escalated\Laravel\Events\ChatTyping;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\RoutePermissions;
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
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function liveChat(array $session = [], array $ticket = []): ChatSession
{
    $ticket = Ticket::factory()->create(array_merge([
        'status' => TicketStatus::Live,
        'channel' => 'chat',
        'guest_name' => 'Visitor Vera',
        'guest_email' => 'vera@example.com',
    ], $ticket));

    return ChatSession::factory()->create(array_merge([
        'ticket_id' => $ticket->id,
        'status' => ChatSessionStatus::Waiting,
        'started_at' => now(),
    ], $session));
}

it('registers agent chat routes that need a permission while roles are enforced', function () {
    foreach (['active', 'queue', 'accept', 'end', 'transfer', 'message', 'typing'] as $action) {
        expect(RoutePermissions::isMapped("escalated.agent.chat.{$action}"))->toBeTrue();
    }
});

it('keeps the admin chat routes closed to agents', function () {
    $agent = $this->createAgent();
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $this->actingAs($agent)->postJson("/support/admin/chat/{$session->id}/end")->assertForbidden();
});

it('lets an agent accept a waiting chat', function () {
    $agent = $this->createAgent();
    $session = liveChat();

    $this->actingAs($agent)
        ->postJson(route('escalated.agent.chat.accept', $session))
        ->assertOk();

    $session->refresh();
    expect($session->status)->toBe(ChatSessionStatus::Active)
        ->and((string) $session->agent_id)->toBe((string) $agent->id)
        ->and((string) $session->ticket->assigned_to)->toBe((string) $agent->id);
});

it('refuses to accept a chat that is not waiting', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    $session = liveChat(['agent_id' => $other->id, 'status' => ChatSessionStatus::Active]);

    $this->actingAs($agent)
        ->postJson(route('escalated.agent.chat.accept', $session))
        ->assertStatus(422);
});

it('lets the assigned agent send a message as themselves', function () {
    $agent = $this->createAgent(['name' => 'Agent Ada']);
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $response = $this->actingAs($agent)
        ->postJson(route('escalated.agent.chat.message', $session), ['body' => 'Hi, how can I help?'])
        ->assertOk();

    $reply = Reply::find($response->json('reply_id'));
    expect($reply->body)->toBe('Hi, how can I help?')
        ->and((string) $reply->author_id)->toBe((string) $agent->id)
        ->and($reply->is_internal_note)->toBeFalse();

    Event::assertDispatched(ChatMessage::class, fn (ChatMessage $event) => $event->isAgent
        && $event->broadcastWith()['author'] === 'Agent Ada');
});

it('keeps a note written in the chat composer off the chat', function () {
    $agent = $this->createAgent();
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $response = $this->actingAs($agent)
        ->postJson(route('escalated.agent.chat.message', $session), ['body' => 'Check the refund log', 'is_internal_note' => true])
        ->assertOk();

    expect(Reply::find($response->json('reply_id'))->is_internal_note)->toBeTrue();
    Event::assertNotDispatched(ChatMessage::class);
});

it('names the agent who is typing', function () {
    $agent = $this->createAgent(['name' => 'Agent Ada']);
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $this->actingAs($agent)->postJson(route('escalated.agent.chat.typing', $session))->assertOk();

    Event::assertDispatched(ChatTyping::class, fn (ChatTyping $event) => $event->broadcastWith() === [
        'is_agent' => true,
        'user_name' => 'Agent Ada',
    ]);
    expect($session->fresh()->agent_typing_at)->not->toBeNull();
});

it('lets the assigned agent end the chat', function () {
    $agent = $this->createAgent();
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $this->actingAs($agent)->postJson(route('escalated.agent.chat.end', $session))->assertOk();

    expect($session->fresh()->status)->toBe(ChatSessionStatus::Ended)
        ->and($session->ticket->fresh()->status)->toBe(TicketStatus::Closed);
    Event::assertDispatched(ChatEnded::class, fn (ChatEnded $event) => $event->endedBy === 'agent');
});

it('lets the assigned agent transfer the chat to another agent', function () {
    $agent = $this->createAgent();
    $colleague = $this->createAgent(['email' => 'colleague@example.com']);
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $this->actingAs($agent)
        ->postJson(route('escalated.agent.chat.transfer', $session), ['agent_id' => $colleague->id])
        ->assertOk();

    expect((string) $session->fresh()->agent_id)->toBe((string) $colleague->id);
});

it('transfers only to staff', function () {
    $agent = $this->createAgent();
    $customer = $this->createTestUser(['email' => 'customer@example.com']);
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $this->actingAs($agent)
        ->postJson(route('escalated.agent.chat.transfer', $session), ['agent_id' => $customer->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('agent_id');

    expect((string) $session->fresh()->agent_id)->toBe((string) $agent->id);
});

it('keeps another agent out of a chat that is not theirs', function (string $action, array $body) {
    $owner = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    $colleague = $this->createAgent(['email' => 'colleague@example.com']);
    $session = liveChat(['agent_id' => $owner->id, 'status' => ChatSessionStatus::Active]);
    if ($action === 'transfer') {
        $body = ['agent_id' => $colleague->id];
    }

    $this->actingAs($other)
        ->postJson(route("escalated.agent.chat.{$action}", $session), $body)
        ->assertForbidden();

    expect($session->fresh()->status)->toBe(ChatSessionStatus::Active)
        ->and((string) $session->fresh()->agent_id)->toBe((string) $owner->id);
})->with([
    'end' => ['end', []],
    'transfer' => ['transfer', []],
    'message' => ['message', ['body' => 'Hello']],
    'typing' => ['typing', []],
]);

it('lets an admin act on any chat through the agent routes', function () {
    $owner = $this->createAgent();
    $admin = $this->createAdmin();
    $session = liveChat(['agent_id' => $owner->id, 'status' => ChatSessionStatus::Active]);

    $this->actingAs($admin)
        ->postJson(route('escalated.agent.chat.message', $session), ['body' => 'Stepping in'])
        ->assertOk();
    $this->actingAs($admin)->postJson(route('escalated.agent.chat.end', $session))->assertOk();

    expect($session->fresh()->status)->toBe(ChatSessionStatus::Ended);
});

it('refuses messages on an ended chat', function () {
    $agent = $this->createAgent();
    $session = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Ended, 'ended_at' => now()]);

    $this->actingAs($agent)
        ->postJson(route('escalated.agent.chat.message', $session), ['body' => 'Hello?'])
        ->assertStatus(422);
});

it('lists the agent\'s own active chats and the queue', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    $mine = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);
    liveChat(['agent_id' => $other->id, 'status' => ChatSessionStatus::Active]);
    $waiting = liveChat([], ['guest_name' => 'Waiting Walt', 'description' => 'Where is my order?']);

    $active = $this->actingAs($agent)->getJson(route('escalated.agent.chat.active'))->assertOk()->json('sessions');
    expect($active)->toHaveCount(1)
        ->and($active[0]['id'])->toBe($mine->id)
        ->and($active[0]['ticket_reference'])->toBe($mine->ticket->reference)
        ->and($active[0]['customer_name'])->toBe('Visitor Vera');

    $queue = $this->actingAs($agent)->getJson(route('escalated.agent.chat.queue'))->assertOk()->json('sessions');
    expect($queue)->toHaveCount(1)
        ->and($queue[0]['id'])->toBe($waiting->id)
        ->and($queue[0]['customer_name'])->toBe('Waiting Walt')
        ->and($queue[0]['first_message'])->toBe('Where is my order?');
});

function sharedEscalatedProps($test, $user): array
{
    $shared = $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.index'))
        ->assertOk()
        ->json('props.escalated');
    $test->flushHeaders();

    return $shared;
}

it('shares chat_enabled, the agent\'s active chats and the queue while live chat is on', function () {
    EscalatedSettings::set('chat_enabled', '1');
    $agent = $this->createAgent();
    $mine = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);
    $waiting = liveChat();

    $shared = sharedEscalatedProps($this, $agent);

    expect($shared['chat_enabled'])->toBeTrue()
        ->and(collect($shared['active_chats'])->pluck('id')->all())->toBe([$mine->id])
        ->and(collect($shared['chat_queue'])->pluck('id')->all())->toBe([$waiting->id])
        ->and($shared['active_chats'][0])->toHaveKeys(['ticket_reference', 'customer_name', 'last_message', 'last_message_at']);
});

it('shares no chats while live chat is off', function () {
    EscalatedSettings::set('chat_enabled', '0');
    $agent = $this->createAgent();
    liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);

    $shared = sharedEscalatedProps($this, $agent);

    expect($shared['chat_enabled'])->toBeFalse()
        ->and($shared)->not->toHaveKey('active_chats')
        ->and($shared)->not->toHaveKey('chat_queue');
});

it('filters the agent ticket list to chats in progress', function () {
    $agent = $this->createAgent();
    $chat = liveChat(['agent_id' => $agent->id, 'status' => ChatSessionStatus::Active]);
    Ticket::factory()->create(['status' => TicketStatus::Open]);

    $tickets = $this->actingAs($agent)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.index', ['live_chat' => 1]))
        ->assertOk()
        ->json('props.tickets.data');

    expect(collect($tickets)->pluck('id')->all())->toBe([$chat->ticket_id]);
});

<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\TicketChannel;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatEnded;
use Escalated\Laravel\Events\ChatMessage;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Events\ChatTransferred;
use Escalated\Laravel\Events\ChatTyping;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ChatSessionService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([
        ChatStarted::class,
        ChatAssigned::class,
        ChatEnded::class,
        ChatMessage::class,
        ChatTyping::class,
        ChatTransferred::class,
    ]);
});

it('starts a chat and creates ticket and session', function () {
    $service = app(ChatSessionService::class);

    $result = $service->startChat([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'subject' => 'Help me',
        'message' => 'I need assistance',
    ]);

    expect($result)->toHaveKeys(['session_id', 'ticket_reference', 'status']);

    $ticket = Ticket::latest('id')->first();
    expect($ticket->status)->toBe(TicketStatus::Live);
    expect($ticket->channel)->toBe(TicketChannel::Chat);
    expect($ticket->guest_name)->toBe('John Doe');

    $session = ChatSession::where('customer_session_id', $result['session_id'])->first();
    expect($session)->not->toBeNull();
    expect($session->ticket_id)->toBe($ticket->id);
});

it('assigns an agent to a chat session', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->create(['ticket_id' => $ticket->id]);

    $service = app(ChatSessionService::class);
    $service->assignAgent($session, $agent->id);

    $session->refresh();
    expect($session->agent_id)->toBe($agent->id);
    expect($session->status)->toBe(ChatSessionStatus::Active);
    expect($session->ticket->assigned_to)->toBe($agent->id);
});

it('ends a chat session and closes the ticket', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);

    $service = app(ChatSessionService::class);
    $service->endChat($session, 'agent');

    $session->refresh();
    expect($session->status)->toBe(ChatSessionStatus::Ended);
    expect($session->ended_at)->not->toBeNull();
    expect($session->ticket->status)->toBe(TicketStatus::Closed);
});

it('transfers a chat to another agent', function () {
    $agent1 = $this->createAgent(['email' => 'agent1@example.com']);
    $agent2 = $this->createAgent(['email' => 'agent2@example.com']);

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Live,
        'channel' => 'chat',
        'assigned_to' => $agent1->id,
    ]);
    $session = ChatSession::factory()->active($agent1->id)->create(['ticket_id' => $ticket->id]);

    $service = app(ChatSessionService::class);
    $service->transferChat($session, $agent2->id);

    $session->refresh();
    expect($session->agent_id)->toBe($agent2->id);
    expect($session->ticket->assigned_to)->toBe($agent2->id);
});

it('sends a message in a chat session', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active($agent->id)->create(['ticket_id' => $ticket->id]);

    $service = app(ChatSessionService::class);
    $reply = $service->sendMessage($session, 'Hello there!', $agent->id, true);

    expect($reply->body)->toBe('Hello there!');
    expect($reply->ticket_id)->toBe($ticket->id);
});

it('updates typing indicator', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);

    $service = app(ChatSessionService::class);
    $service->updateTyping($session, false);

    $session->refresh();
    expect($session->customer_typing_at)->not->toBeNull();
});

it('rates a completed chat session', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed, 'channel' => 'chat']);
    $session = ChatSession::factory()->ended()->create(['ticket_id' => $ticket->id]);

    $service = app(ChatSessionService::class);
    $service->rateChat($session, 5, 'Great service!');

    $session->refresh();
    expect($session->rating)->toBe(5);
    expect($session->rating_comment)->toBe('Great service!');
});

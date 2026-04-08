<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\TicketChannel;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([ChatStarted::class]);
});

it('uses dynamic table name from config', function () {
    $session = new ChatSession;
    expect($session->getTable())->toBe('escalated_chat_sessions');
});

it('casts status to enum', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->create(['ticket_id' => $ticket->id]);

    expect($session->status)->toBeInstanceOf(ChatSessionStatus::class);
});

it('scopes waiting sessions', function () {
    $ticket1 = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $ticket2 = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $ticket3 = Ticket::factory()->create(['status' => TicketStatus::Closed]);

    ChatSession::factory()->waiting()->create(['ticket_id' => $ticket1->id]);
    ChatSession::factory()->active()->create(['ticket_id' => $ticket2->id]);
    ChatSession::factory()->ended()->create(['ticket_id' => $ticket3->id]);

    expect(ChatSession::waiting()->count())->toBe(1);
});

it('scopes active sessions', function () {
    $ticket1 = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $ticket2 = Ticket::factory()->create(['status' => TicketStatus::Live]);

    ChatSession::factory()->waiting()->create(['ticket_id' => $ticket1->id]);
    ChatSession::factory()->active()->create(['ticket_id' => $ticket2->id]);

    expect(ChatSession::active()->count())->toBe(1);
});

it('scopes ended sessions', function () {
    $ticket1 = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $ticket2 = Ticket::factory()->create(['status' => TicketStatus::Closed]);

    ChatSession::factory()->active()->create(['ticket_id' => $ticket1->id]);
    ChatSession::factory()->ended()->create(['ticket_id' => $ticket2->id]);

    expect(ChatSession::ended()->count())->toBe(1);
});

it('scopes sessions for agent', function () {
    $ticket1 = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $ticket2 = Ticket::factory()->create(['status' => TicketStatus::Live]);

    ChatSession::factory()->active(1)->create(['ticket_id' => $ticket1->id]);
    ChatSession::factory()->active(2)->create(['ticket_id' => $ticket2->id]);

    expect(ChatSession::forAgent(1)->count())->toBe(1);
});

it('belongs to ticket', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $session = ChatSession::factory()->create(['ticket_id' => $ticket->id]);

    expect($session->ticket)->toBeInstanceOf(Ticket::class);
    expect($session->ticket->id)->toBe($ticket->id);
});

it('ticket has chatSession relationship', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $session = ChatSession::factory()->create(['ticket_id' => $ticket->id]);

    expect($ticket->chatSession)->toBeInstanceOf(ChatSession::class);
    expect($ticket->chatSession->id)->toBe($session->id);
});

it('ticket has isLiveChat accessor', function () {
    $liveTicket = Ticket::factory()->create([
        'status' => TicketStatus::Live,
        'channel' => TicketChannel::Chat,
    ]);

    $normalTicket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'channel' => TicketChannel::Web,
    ]);

    expect($liveTicket->is_live_chat)->toBeTrue();
    expect($normalTicket->is_live_chat)->toBeFalse();
});

it('ticket has live scope', function () {
    Ticket::factory()->create(['status' => TicketStatus::Live]);
    Ticket::factory()->create(['status' => TicketStatus::Open]);
    Ticket::factory()->create(['status' => TicketStatus::Live]);

    expect(Ticket::live()->count())->toBe(2);
});

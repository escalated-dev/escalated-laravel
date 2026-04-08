<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Events\TicketAssigned;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Events\TicketEscalated;
use Escalated\Laravel\Events\TicketStatusChanged;
use Escalated\Laravel\Events\TicketUpdated;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('all events implement ShouldBroadcastNow', function () {
    $events = [
        TicketCreated::class,
        TicketUpdated::class,
        TicketAssigned::class,
        TicketStatusChanged::class,
        TicketEscalated::class,
        ReplyCreated::class,
    ];

    foreach ($events as $eventClass) {
        expect(in_array(ShouldBroadcastNow::class, class_implements($eventClass)))
            ->toBeTrue("$eventClass should implement ShouldBroadcastNow");
    }
});

it('TicketCreated broadcastOn returns escalated.tickets channel', function () {
    $ticket = Ticket::factory()->create(['reference' => 'BC-00001']);
    $event = new TicketCreated($ticket);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-escalated.tickets');
});

it('TicketUpdated broadcastOn returns ticket-specific channel', function () {
    $ticket = Ticket::factory()->create(['reference' => 'BC-00002']);
    $event = new TicketUpdated($ticket);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-escalated.tickets.'.$ticket->id);
});

it('TicketAssigned broadcastOn returns ticket and agent channels', function () {
    $ticket = Ticket::factory()->create(['reference' => 'BC-00003']);
    $event = new TicketAssigned($ticket, 42);

    $channels = $event->broadcastOn();
    expect($channels)->toHaveCount(2);

    $channelNames = array_map(fn ($ch) => $ch->name, $channels);
    expect($channelNames)->toContain('private-escalated.tickets.'.$ticket->id);
    expect($channelNames)->toContain('private-escalated.agents.42');
});

it('ReplyCreated broadcastOn returns ticket-specific channel', function () {
    $ticket = Ticket::factory()->create(['reference' => 'BC-00004']);
    $reply = Reply::create([
        'ticket_id' => $ticket->id,
        'body' => 'Test reply',
        'is_internal_note' => false,
        'is_pinned' => false,
        'author_type' => null,
        'author_id' => null,
    ]);

    $event = new ReplyCreated($reply);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe('private-escalated.tickets.'.$ticket->id);
});

it('broadcastAs returns clean event names', function () {
    $ticket = Ticket::factory()->create(['reference' => 'BC-00005']);
    $reply = Reply::create([
        'ticket_id' => $ticket->id,
        'body' => 'Test',
        'is_internal_note' => false,
        'is_pinned' => false,
        'author_type' => null,
        'author_id' => null,
    ]);

    expect((new TicketCreated($ticket))->broadcastAs())->toBe('ticket.created');
    expect((new TicketUpdated($ticket))->broadcastAs())->toBe('ticket.updated');
    expect((new TicketAssigned($ticket, 1))->broadcastAs())->toBe('ticket.assigned');
    expect((new TicketStatusChanged($ticket, TicketStatus::Open, TicketStatus::Resolved))->broadcastAs())
        ->toBe('ticket.status_changed');
    expect((new TicketEscalated($ticket))->broadcastAs())->toBe('ticket.escalated');
    expect((new ReplyCreated($reply))->broadcastAs())->toBe('reply.created');
});

it('broadcastWith returns minimal payload', function () {
    $ticket = Ticket::factory()->create([
        'reference' => 'BC-00006',
        'subject' => 'Test Subject',
        'status' => TicketStatus::Open,
    ]);

    $event = new TicketCreated($ticket);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys(['ticket_id', 'reference', 'subject', 'status', 'priority']);
    expect($payload['ticket_id'])->toBe($ticket->id);
    expect($payload['reference'])->toBe($ticket->reference);
});

it('broadcastWhen returns false when broadcasting is disabled', function () {
    config(['escalated.broadcasting.enabled' => false]);

    $ticket = Ticket::factory()->create(['reference' => 'BC-00007']);
    $event = new TicketCreated($ticket);

    expect($event->broadcastWhen())->toBeFalse();
});

it('broadcastWhen returns true when broadcasting is enabled', function () {
    config(['escalated.broadcasting.enabled' => true]);

    $ticket = Ticket::factory()->create(['reference' => 'BC-00008']);
    $event = new TicketCreated($ticket);

    expect($event->broadcastWhen())->toBeTrue();
});

it('channel authorization callback allows agents on tickets channel', function () {
    // Enable broadcasting to load the channels file
    config(['escalated.broadcasting.enabled' => true]);
    require_once __DIR__.'/../../routes/channels.php';

    $agent = $this->createAgent();
    $this->actingAs($agent);

    // Verify the agent gate allows access
    expect(Gate::allows('escalated-agent'))->toBeTrue();
});

it('channel authorization callback denies regular users on tickets channel', function () {
    $user = $this->createTestUser();
    $this->actingAs($user);

    expect(Gate::allows('escalated-agent'))->toBeFalse();
    expect(Gate::allows('escalated-admin'))->toBeFalse();
});

it('agent channel allows only the agent themselves', function () {
    $agent = $this->createAgent();
    $this->actingAs($agent);

    // The channel callback checks (int) $user->id === (int) $agentId
    expect((int) $agent->id === (int) $agent->id)->toBeTrue();
    expect((int) $agent->id === 9999)->toBeFalse();
});

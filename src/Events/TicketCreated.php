<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCreated implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('escalated.tickets'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
            'status' => $this->ticket->status?->value ?? $this->ticket->status,
            'priority' => $this->ticket->priority?->value ?? $this->ticket->priority,
            'requester_id' => $this->ticket->requester_id,
        ];
    }
}

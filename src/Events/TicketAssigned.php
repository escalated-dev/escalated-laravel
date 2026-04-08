<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAssigned implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public int $agentId,
        public mixed $causer = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('escalated.tickets.'.$this->ticket->id),
            new PrivateChannel('escalated.agents.'.$this->agentId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
            'agent_id' => $this->agentId,
            'causer_id' => $this->causer?->id ?? $this->causer,
        ];
    }
}

<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\TicketVisibility;
use Escalated\Laravel\Tenancy\TenantBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAssigned implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public int|string $agentId,
        public mixed $causer = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel(TenantBroadcast::channel('tickets.'.$this->ticket->id, $this->ticket)),
        ];
        if (TicketVisibility::agentCanAccess($this->agentId, $this->ticket)) {
            $channels[] = new PrivateChannel(TenantBroadcast::channel('agents.'.$this->agentId, $this->ticket));
        }

        return $channels;
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

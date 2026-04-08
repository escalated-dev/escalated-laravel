<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusChanged implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public TicketStatus $oldStatus,
        public TicketStatus $newStatus,
        public mixed $causer = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('escalated.tickets.'.$this->ticket->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.status_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
            'causer_id' => $this->causer?->id ?? $this->causer,
        ];
    }
}

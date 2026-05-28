<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCustomActionTriggered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $action,
        public Authenticatable $user,
        public array $payload = [],
        public array $metadata = [],
    ) {}
}

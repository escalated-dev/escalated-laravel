<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;

interface TicketAction
{
    public function key(): string;

    public function label(Ticket $ticket, Authenticatable $user): string;

    public function visible(Ticket $ticket, Authenticatable $user): bool;

    public function enabled(Ticket $ticket, Authenticatable $user): bool;

    public function variant(): string;

    public function confirmation(Ticket $ticket, Authenticatable $user): ?string;

    public function metadata(Ticket $ticket, Authenticatable $user): array;
}

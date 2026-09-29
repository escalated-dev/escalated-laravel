<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Model;

/** Optional driver capability; the existing TicketDriver contract is unchanged. */
interface CreatesAgentTickets
{
    public function createAgentTicket(Model&Ticketable $actor, array $data): Ticket;
}

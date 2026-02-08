<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TicketDriver
{
    public function createTicket(Ticketable $requester, array $data): Ticket;

    public function updateTicket(Ticket $ticket, array $data): Ticket;

    public function transitionStatus(Ticket $ticket, TicketStatus $status, ?Ticketable $causer = null): Ticket;

    public function assignTicket(Ticket $ticket, int $agentId, ?Ticketable $causer = null): Ticket;

    public function unassignTicket(Ticket $ticket, ?Ticketable $causer = null): Ticket;

    public function addReply(Ticket $ticket, Ticketable $author, string $body, bool $isNote = false, array $attachments = []): Reply;

    public function getTicket(int|string $id): Ticket;

    public function listTickets(array $filters = [], ?Ticketable $for = null): LengthAwarePaginator;

    public function addTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket;

    public function removeTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket;

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Ticketable $causer = null): Ticket;

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Ticketable $causer = null): Ticket;
}

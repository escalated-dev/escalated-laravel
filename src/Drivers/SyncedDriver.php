<?php

namespace Escalated\Laravel\Drivers;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Client\HostedApiClient;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AttachmentService;
use Illuminate\Support\Facades\Log;

class SyncedDriver extends LocalDriver
{
    protected HostedApiClient $apiClient;

    public function __construct(
        AttachmentService $attachmentService,
        HostedApiClient $apiClient
    ) {
        parent::__construct($attachmentService);
        $this->apiClient = $apiClient;
    }

    public function createTicket(Ticketable $requester, array $data): Ticket
    {
        $ticket = parent::createTicket($requester, $data);
        $this->syncEvent('ticket.created', $ticket->toArray());

        return $ticket;
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $ticket = parent::updateTicket($ticket, $data);
        $this->syncEvent('ticket.updated', $ticket->toArray());

        return $ticket;
    }

    public function transitionStatus(Ticket $ticket, TicketStatus $status, ?Ticketable $causer = null): Ticket
    {
        $ticket = parent::transitionStatus($ticket, $status, $causer);
        $this->syncEvent('ticket.status_changed', ['ticket' => $ticket->toArray(), 'new_status' => $status->value]);

        return $ticket;
    }

    public function assignTicket(Ticket $ticket, int $agentId, ?Ticketable $causer = null): Ticket
    {
        $ticket = parent::assignTicket($ticket, $agentId, $causer);
        $this->syncEvent('ticket.assigned', ['ticket' => $ticket->toArray(), 'agent_id' => $agentId]);

        return $ticket;
    }

    public function unassignTicket(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        $ticket = parent::unassignTicket($ticket, $causer);
        $this->syncEvent('ticket.unassigned', $ticket->toArray());

        return $ticket;
    }

    public function addReply(Ticket $ticket, Ticketable $author, string $body, bool $isNote = false, array $attachments = []): Reply
    {
        $reply = parent::addReply($ticket, $author, $body, $isNote, $attachments);
        $this->syncEvent('reply.created', ['ticket_reference' => $ticket->reference, 'reply' => $reply->toArray()]);

        return $reply;
    }

    public function addTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        $ticket = parent::addTags($ticket, $tagIds, $causer);
        $this->syncEvent('ticket.tags_added', ['ticket' => $ticket->toArray(), 'tag_ids' => $tagIds]);

        return $ticket;
    }

    public function removeTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        $ticket = parent::removeTags($ticket, $tagIds, $causer);
        $this->syncEvent('ticket.tags_removed', ['ticket' => $ticket->toArray(), 'tag_ids' => $tagIds]);

        return $ticket;
    }

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Ticketable $causer = null): Ticket
    {
        $ticket = parent::changeDepartment($ticket, $departmentId, $causer);
        $this->syncEvent('ticket.department_changed', ['ticket' => $ticket->toArray(), 'department_id' => $departmentId]);

        return $ticket;
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Ticketable $causer = null): Ticket
    {
        $ticket = parent::changePriority($ticket, $priority, $causer);
        $this->syncEvent('ticket.priority_changed', ['ticket' => $ticket->toArray(), 'priority' => $priority->value]);

        return $ticket;
    }

    protected function syncEvent(string $event, array $payload): void
    {
        try {
            $this->apiClient->emit($event, $payload);
        } catch (\Throwable $e) {
            Log::warning("Escalated sync failed for {$event}: {$e->getMessage()}");
        }
    }
}

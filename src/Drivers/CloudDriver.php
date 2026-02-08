<?php

namespace Escalated\Laravel\Drivers;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Contracts\TicketDriver;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Client\HostedApiClient;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CloudDriver implements TicketDriver
{
    public function __construct(protected HostedApiClient $apiClient) {}

    public function createTicket(Ticketable $requester, array $data): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.create', array_merge($data, [
            'requester_name' => $requester->getTicketableNameAttribute(),
            'requester_email' => $requester->getTicketableEmailAttribute(),
            'requester_id' => $requester->getKey(),
        ]));

        return $this->hydrateTicket($response);
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.update', array_merge($data, [
            'reference' => $ticket->reference,
        ]));

        return $this->hydrateTicket($response);
    }

    public function transitionStatus(Ticket $ticket, TicketStatus $status, ?Ticketable $causer = null): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.transition', [
            'reference' => $ticket->reference,
            'status' => $status->value,
        ]);

        return $this->hydrateTicket($response);
    }

    public function assignTicket(Ticket $ticket, int $agentId, ?Ticketable $causer = null): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.assign', [
            'reference' => $ticket->reference,
            'agent_id' => $agentId,
        ]);

        return $this->hydrateTicket($response);
    }

    public function unassignTicket(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.unassign', [
            'reference' => $ticket->reference,
        ]);

        return $this->hydrateTicket($response);
    }

    public function addReply(Ticket $ticket, Ticketable $author, string $body, bool $isNote = false, array $attachments = []): Reply
    {
        $response = $this->apiClient->sendCommand('tickets.reply', [
            'reference' => $ticket->reference,
            'body' => $body,
            'is_note' => $isNote,
            'author_name' => $author->getTicketableNameAttribute(),
            'author_email' => $author->getTicketableEmailAttribute(),
            'author_id' => $author->getKey(),
        ]);

        return $this->hydrateReply($response);
    }

    public function getTicket(int|string $id): Ticket
    {
        $response = $this->apiClient->query("tickets/{$id}");

        return $this->hydrateTicket($response);
    }

    public function listTickets(array $filters = [], ?Ticketable $for = null): LengthAwarePaginator
    {
        if ($for) {
            $filters['requester_id'] = $for->getKey();
        }

        $response = $this->apiClient->query('tickets', $filters);
        $items = collect($response['data'] ?? [])->map(fn ($item) => $this->hydrateTicket($item));

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $response['total'] ?? 0,
            $response['per_page'] ?? 15,
            $response['current_page'] ?? 1,
        );
    }

    public function addTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.add_tags', [
            'reference' => $ticket->reference, 'tag_ids' => $tagIds,
        ]);

        return $this->hydrateTicket($response);
    }

    public function removeTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.remove_tags', [
            'reference' => $ticket->reference, 'tag_ids' => $tagIds,
        ]);

        return $this->hydrateTicket($response);
    }

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Ticketable $causer = null): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.change_department', [
            'reference' => $ticket->reference, 'department_id' => $departmentId,
        ]);

        return $this->hydrateTicket($response);
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Ticketable $causer = null): Ticket
    {
        $response = $this->apiClient->sendCommand('tickets.change_priority', [
            'reference' => $ticket->reference, 'priority' => $priority->value,
        ]);

        return $this->hydrateTicket($response);
    }

    protected function hydrateTicket(array $data): Ticket
    {
        $ticket = new Ticket();
        $ticket->forceFill($data);
        $ticket->exists = true;

        return $ticket;
    }

    protected function hydrateReply(array $data): Reply
    {
        $reply = new Reply();
        $reply->forceFill($data);
        $reply->exists = true;

        return $reply;
    }
}

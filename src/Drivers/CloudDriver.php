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
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Proxies every ticket operation to cloud.escalated.dev.
 *
 * The cloud speaks a slightly different vocabulary from this package
 * (`normal` instead of `medium`, `waiting` instead of `waiting_on_*`,
 * `ticket_number` instead of `reference`), so payloads are translated on
 * the way out and responses on the way back. Hydrated models are in-memory
 * projections of the cloud row: they carry `exists = true` so views and
 * policies treat them as persisted, but nothing is written locally.
 */
class CloudDriver implements TicketDriver
{
    /** Package priority → cloud priority. Identity when not listed. */
    private const PRIORITY_TO_CLOUD = [
        'medium' => 'normal',
        'critical' => 'urgent',
    ];

    /** Cloud priority → package priority. Identity when not listed. */
    private const PRIORITY_FROM_CLOUD = [
        'normal' => 'medium',
    ];

    /** Package status → cloud status. Identity when not listed. */
    private const STATUS_TO_CLOUD = [
        'waiting_on_customer' => 'waiting',
        'waiting_on_agent' => 'waiting',
        'escalated' => 'open',
        'reopened' => 'open',
        'live' => 'open',
    ];

    /** Cloud status → package status. Identity when not listed. */
    private const STATUS_FROM_CLOUD = [
        'waiting' => 'waiting_on_customer',
        'snoozed' => 'open',
    ];

    /** Cloud-only keys that must not be forced onto the local model. */
    private const CLOUD_ONLY_TICKET_KEYS = [
        'account_id', 'assignee', 'sla', 'ticket_number', 'requester_name', 'requester_email',
        'tags', 'created_by', 'external_id', 'ticket_subject_id', 'split_from_ticket_id', 'split_from_comment_id',
    ];

    public function __construct(protected HostedApiClient $apiClient) {}

    public function createTicket(Ticketable $requester, array $data): Ticket
    {
        $payload = $this->translateTicketData($data);
        $payload['requester_name'] = $requester->getTicketableNameAttribute();
        $payload['requester_email'] = $requester->getTicketableEmailAttribute();
        $payload['requester_id'] = $requester->getKey();
        $payload['metadata'] = array_merge(
            is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
            [
                'host_requester_type' => $requester->getMorphClass(),
                'host_requester_id' => $requester->getKey(),
            ],
        );

        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.create', $payload)));
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $payload = $this->translateTicketData($data);
        $payload['reference'] = $ticket->reference;

        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.update', $payload)));
    }

    public function transitionStatus(Ticket $ticket, TicketStatus $status, ?Ticketable $causer = null): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.transition', [
            'reference' => $ticket->reference,
            'status' => self::STATUS_TO_CLOUD[$status->value] ?? $status->value,
        ])));
    }

    public function assignTicket(Ticket $ticket, int|string $agentId, ?Ticketable $causer = null): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.assign', [
            'reference' => $ticket->reference,
            'agent_id' => $agentId,
        ])));
    }

    public function unassignTicket(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.unassign', [
            'reference' => $ticket->reference,
        ])));
    }

    public function addReply(Ticket $ticket, Ticketable $author, string $body, bool $isNote = false, array $attachments = []): Reply
    {
        return $this->hydrateReply($this->data($this->apiClient->sendCommand('tickets.reply', [
            'reference' => $ticket->reference,
            'body' => $body,
            'is_note' => $isNote,
            'author_name' => $author->getTicketableNameAttribute(),
            'author_email' => $author->getTicketableEmailAttribute(),
            'author_id' => $author->getKey(),
        ])));
    }

    public function getTicket(int|string $id): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->query("tickets/{$id}")));
    }

    public function listTickets(array $filters = [], ?Ticketable $for = null): LengthAwarePaginator
    {
        if ($for) {
            $filters['requester_id'] = $for->getKey();
        }

        $json = $this->data($this->apiClient->query('tickets', $filters));
        $meta = is_array($json['meta'] ?? null) ? $json['meta'] : $json;

        $items = collect($json['data'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => $this->hydrateTicket($item))
            ->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            (int) ($meta['total'] ?? $items->count()),
            (int) ($meta['per_page'] ?? 15),
            (int) ($meta['current_page'] ?? 1),
        );
    }

    public function addTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.add_tags', [
            'reference' => $ticket->reference, 'tag_ids' => array_map('strval', $tagIds),
        ])));
    }

    public function removeTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.remove_tags', [
            'reference' => $ticket->reference, 'tag_ids' => array_map('strval', $tagIds),
        ])));
    }

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Ticketable $causer = null): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.change_department', [
            'reference' => $ticket->reference, 'department_id' => $departmentId,
        ])));
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Ticketable $causer = null): Ticket
    {
        return $this->hydrateTicket($this->data($this->apiClient->sendCommand('tickets.change_priority', [
            'reference' => $ticket->reference,
            'priority' => self::PRIORITY_TO_CLOUD[$priority->value] ?? $priority->value,
        ])));
    }

    /**
     * Build a local Ticket projection from a raw cloud ticket array.
     * Public so callers holding a cached cloud payload can rehydrate it.
     */
    public function getTicketFromArray(array $data): Ticket
    {
        return $this->hydrateTicket($data);
    }

    /**
     * Decode a cloud response, raising on non-2xx.
     */
    protected function data(?Response $response): array
    {
        if ($response === null) {
            throw new RuntimeException('Escalated Cloud returned no response.');
        }

        $json = $response->throw()->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Translate outbound ticket attributes into the cloud vocabulary.
     */
    protected function translateTicketData(array $data): array
    {
        if (isset($data['priority'])) {
            $value = $data['priority'] instanceof TicketPriority ? $data['priority']->value : (string) $data['priority'];
            $data['priority'] = self::PRIORITY_TO_CLOUD[$value] ?? $value;
        }

        if (isset($data['status'])) {
            $value = $data['status'] instanceof TicketStatus ? $data['status']->value : (string) $data['status'];
            $data['status'] = self::STATUS_TO_CLOUD[$value] ?? $value;
        }

        return $data;
    }

    protected function hydrateTicket(array $data): Ticket
    {
        $data = $this->unwrap($data);

        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $attributes = $data;

        $attributes['reference'] = (string) ($data['reference'] ?? $data['ticket_number'] ?? $data['id'] ?? '');

        if (isset($data['priority'])) {
            $attributes['priority'] = self::PRIORITY_FROM_CLOUD[$data['priority']] ?? $data['priority'];
        }

        if (isset($data['status'])) {
            $attributes['status'] = self::STATUS_FROM_CLOUD[$data['status']] ?? $data['status'];
        }

        if (isset($metadata['host_requester_type'], $metadata['host_requester_id'])) {
            $attributes['requester_type'] = $metadata['host_requester_type'];
            $attributes['requester_id'] = $metadata['host_requester_id'];
        } else {
            $attributes['requester_type'] = null;
            $attributes['requester_id'] = null;
            $attributes['guest_name'] = $data['requester_name'] ?? null;
            $attributes['guest_email'] = $data['requester_email'] ?? null;
            $attributes['guest_token'] = 'cloud:'.$attributes['reference'];
        }

        $metadata['cloud_id'] = $data['id'] ?? null;
        $metadata['cloud_ticket_number'] = $data['ticket_number'] ?? null;

        if (array_key_exists('tags', $data)) {
            $metadata['cloud_tags'] = is_array($data['tags']) ? $data['tags'] : [];
        }

        if (isset($data['requester_name']) || isset($data['requester_email'])) {
            $metadata['cloud_requester'] = [
                'name' => $data['requester_name'] ?? null,
                'email' => $data['requester_email'] ?? null,
            ];
        }

        $attributes['metadata'] = $metadata;

        foreach (self::CLOUD_ONLY_TICKET_KEYS as $key) {
            unset($attributes[$key]);
        }

        $ticket = new Ticket;
        $ticket->forceFill($attributes);
        $ticket->exists = true;

        return $ticket;
    }

    protected function hydrateReply(array $data): Reply
    {
        $data = $this->unwrap($data);

        $reply = new Reply;
        $reply->forceFill([
            'id' => $data['id'] ?? null,
            'ticket_id' => $data['ticket_id'] ?? null,
            'body' => (string) ($data['body'] ?? ''),
            'is_internal_note' => (bool) ($data['is_internal'] ?? $data['is_internal_note'] ?? false),
            'type' => 'reply',
            'metadata' => [
                'cloud_comment_id' => $data['id'] ?? null,
                'author_name' => $data['author_name'] ?? null,
                'author_email' => $data['author_email'] ?? null,
            ],
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
        ]);
        $reply->exists = true;

        return $reply;
    }

    /**
     * Single resources come back wrapped in `data` from the REST endpoints
     * and bare from the command endpoint; accept both.
     */
    private function unwrap(array $data): array
    {
        if (isset($data['data']) && is_array($data['data']) && ! array_is_list($data['data'])) {
            return $data['data'];
        }

        return $data;
    }
}

<?php

namespace Escalated\Laravel\Drivers;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Contracts\TicketDriver;
use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AttachmentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LocalDriver implements TicketDriver
{
    protected const ALLOWED_SORT_COLUMNS = [
        'created_at', 'updated_at', 'status', 'priority',
        'subject', 'reference', 'assigned_to', 'department_id',
        'resolved_at', 'closed_at',
    ];

    public function __construct(protected AttachmentService $attachmentService) {}

    public function createTicket(Ticketable $requester, array $data): Ticket
    {
        $ticket = Ticket::create([
            'requester_type' => $requester->getMorphClass(),
            'requester_id' => $requester->getKey(),
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::tryFrom($data['priority'] ?? '') ?? TicketPriority::from(config('escalated.default_priority', 'medium')),
            'ticket_type' => in_array($data['ticket_type'] ?? '', Ticket::TYPES, true) ? $data['ticket_type'] : 'question',
            'channel' => $data['channel'] ?? 'web',
            'department_id' => $data['department_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        if (! empty($data['attachments'])) {
            $this->attachmentService->storeMany($ticket, $data['attachments']);
        }

        if (! empty($data['tags'])) {
            $ticket->tags()->sync($data['tags']);
        }

        $this->logActivity($ticket, ActivityType::StatusChanged, $requester, [
            'new_status' => TicketStatus::Open->value,
        ]);

        // TicketCreated event is automatically dispatched by the Ticket model's $dispatchesEvents property

        return $ticket->fresh();
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $updateData = collect($data)->only(['subject', 'description', 'metadata', 'ticket_type'])->toArray();

        if (isset($updateData['ticket_type']) && ! in_array($updateData['ticket_type'], Ticket::TYPES, true)) {
            unset($updateData['ticket_type']);
        }

        $ticket->update($updateData);

        // TicketUpdated event is automatically dispatched by the Ticket model's $dispatchesEvents property

        return $ticket->fresh();
    }

    public function transitionStatus(Ticket $ticket, TicketStatus $newStatus, ?Ticketable $causer = null): Ticket
    {
        return $ticket->transitionTo($newStatus, $causer);
    }

    public function assignTicket(Ticket $ticket, int $agentId, ?Ticketable $causer = null): Ticket
    {
        return $ticket->assign($agentId, $causer);
    }

    public function unassignTicket(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        return $ticket->unassignTicket($causer);
    }

    public function addReply(Ticket $ticket, Ticketable $author, string $body, bool $isNote = false, array $attachments = []): Reply
    {
        $reply = $ticket->addReply($author, $body, $isNote);

        if (! empty($attachments)) {
            $this->attachmentService->storeMany($reply, $attachments);
        }

        return $reply->fresh();
    }

    public function getTicket(int|string $id): Ticket
    {
        if (is_string($id) && str_starts_with($id, 'ESC-')) {
            return Ticket::where('reference', $id)->firstOrFail();
        }

        return Ticket::findOrFail($id);
    }

    public function listTickets(array $filters = [], ?Ticketable $for = null): LengthAwarePaginator
    {
        $query = Ticket::query()->with(['requester', 'assignee', 'department', 'tags', 'latestReply.author']);

        if ($for) {
            $query->where('requester_type', $for->getMorphClass())
                  ->where('requester_id', $for->getKey());
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['ticket_type'])) {
            $query->where('ticket_type', $filters['ticket_type']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereNull('assigned_to');
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['sla_breached']) && $filters['sla_breached']) {
            $query->breachedSla();
        }

        if (! empty($filters['tag_ids'])) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('id', $filters['tag_ids']));
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('name', 'like', "%{$filters['tag']}%"));
        }

        if (! empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (! empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        if (isset($filters['has_attachments']) && $filters['has_attachments']) {
            $query->whereHas('attachments');
        }

        if (! empty($filters['requester'])) {
            $term = $filters['requester'];
            $query->where(function ($q) use ($term) {
                $q->where('guest_name', 'like', "%{$term}%")
                  ->orWhere('guest_email', 'like', "%{$term}%")
                  ->orWhereHas('requester', function ($rq) use ($term) {
                      $rq->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                  });
            });
        }

        if (isset($filters['following']) && $filters['following'] && $for) {
            $query->whereHas('followers', fn ($q) => $q->where('user_id', $for->getKey()));
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = strtolower($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function addTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        $ticket->tags()->syncWithoutDetaching($tagIds);

        foreach ($tagIds as $tagId) {
            $this->logActivity($ticket, ActivityType::TagAdded, $causer, ['tag_id' => $tagId]);

            $tag = \Escalated\Laravel\Models\Tag::find($tagId);
            if ($tag) {
                Events\TagAddedToTicket::dispatch($ticket, $tag);
            }
        }

        return $ticket->fresh();
    }

    public function removeTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        $ticket->tags()->detach($tagIds);

        foreach ($tagIds as $tagId) {
            $this->logActivity($ticket, ActivityType::TagRemoved, $causer, ['tag_id' => $tagId]);

            $tag = \Escalated\Laravel\Models\Tag::find($tagId);
            if ($tag) {
                Events\TagRemovedFromTicket::dispatch($ticket, $tag);
            }
        }

        return $ticket->fresh();
    }

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Ticketable $causer = null): Ticket
    {
        return $ticket->changeDepartment($departmentId, $causer);
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Ticketable $causer = null): Ticket
    {
        return $ticket->changePriority($priority, $causer);
    }

    protected function logActivity(Ticket $ticket, ActivityType $type, ?Ticketable $causer = null, array $properties = []): void
    {
        $ticket->logActivity($type, $causer, $properties);
    }
}

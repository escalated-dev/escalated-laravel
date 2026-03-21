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
use Escalated\Laravel\Models\TicketActivity;
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
        $ticket = new Ticket();
        $ticket->reference = Ticket::generateReference();
        $ticket->requester_type = $requester->getMorphClass();
        $ticket->requester_id = $requester->getKey();
        $ticket->subject = $data['subject'];
        $ticket->description = $data['description'];
        $ticket->status = TicketStatus::Open;
        $ticket->priority = TicketPriority::tryFrom($data['priority'] ?? '') ?? TicketPriority::from(config('escalated.default_priority', 'medium'));
        $ticket->ticket_type = in_array($data['ticket_type'] ?? '', Ticket::TYPES, true) ? $data['ticket_type'] : 'question';
        $ticket->channel = $data['channel'] ?? 'web';
        $ticket->department_id = $data['department_id'] ?? null;
        $ticket->metadata = $data['metadata'] ?? null;
        $ticket->save();

        if (! empty($data['attachments'])) {
            $this->attachmentService->storeMany($ticket, $data['attachments']);
        }

        if (! empty($data['tags'])) {
            $ticket->tags()->sync($data['tags']);
        }

        $this->logActivity($ticket, ActivityType::StatusChanged, $requester, [
            'new_status' => TicketStatus::Open->value,
        ]);

        Events\TicketCreated::dispatch($ticket);

        return $ticket->fresh();
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $updateData = collect($data)->only(['subject', 'description', 'metadata', 'ticket_type'])->toArray();

        if (isset($updateData['ticket_type']) && ! in_array($updateData['ticket_type'], Ticket::TYPES, true)) {
            unset($updateData['ticket_type']);
        }

        $ticket->update($updateData);

        Events\TicketUpdated::dispatch($ticket);

        return $ticket->fresh();
    }

    public function transitionStatus(Ticket $ticket, TicketStatus $status, ?Ticketable $causer = null): Ticket
    {
        $oldStatus = $ticket->status;

        if (! $oldStatus->canTransitionTo($status)) {
            throw new \InvalidArgumentException("Cannot transition from {$oldStatus->value} to {$status->value}");
        }

        $ticket->status = $status;

        if ($status === TicketStatus::Resolved) {
            $ticket->resolved_at = now();
        } elseif ($status === TicketStatus::Closed) {
            $ticket->closed_at = now();
        } elseif ($status === TicketStatus::Reopened) {
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
        }

        $ticket->save();

        $this->logActivity($ticket, ActivityType::StatusChanged, $causer, [
            'old_status' => $oldStatus->value,
            'new_status' => $status->value,
        ]);

        Events\TicketStatusChanged::dispatch($ticket, $oldStatus, $status, $causer);

        if ($status === TicketStatus::Resolved) {
            Events\TicketResolved::dispatch($ticket, $causer);
        } elseif ($status === TicketStatus::Closed) {
            Events\TicketClosed::dispatch($ticket, $causer);
        } elseif ($status === TicketStatus::Reopened) {
            Events\TicketReopened::dispatch($ticket, $causer);
        } elseif ($status === TicketStatus::Escalated) {
            Events\TicketEscalated::dispatch($ticket);
        }

        return $ticket->fresh();
    }

    public function assignTicket(Ticket $ticket, int $agentId, ?Ticketable $causer = null): Ticket
    {
        $ticket->update(['assigned_to' => $agentId]);

        $this->logActivity($ticket, ActivityType::Assigned, $causer, ['agent_id' => $agentId]);

        Events\TicketAssigned::dispatch($ticket, $agentId, $causer);

        return $ticket->fresh();
    }

    public function unassignTicket(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        $previousAgentId = $ticket->assigned_to;
        $ticket->update(['assigned_to' => null]);

        $this->logActivity($ticket, ActivityType::Unassigned, $causer, ['previous_agent_id' => $previousAgentId]);

        Events\TicketUnassigned::dispatch($ticket, $previousAgentId, $causer);

        return $ticket->fresh();
    }

    public function addReply(Ticket $ticket, Ticketable $author, string $body, bool $isNote = false, array $attachments = []): Reply
    {
        $reply = new Reply();
        $reply->ticket_id = $ticket->id;
        $reply->author_type = $author->getMorphClass();
        $reply->author_id = $author->getKey();
        $reply->body = $body;
        $reply->is_internal_note = $isNote;
        $reply->type = $isNote ? 'note' : 'reply';
        $reply->save();

        if (! empty($attachments)) {
            $this->attachmentService->storeMany($reply, $attachments);
        }

        $activityType = $isNote ? ActivityType::NoteAdded : ActivityType::Replied;
        $this->logActivity($ticket, $activityType, $author);

        if ($isNote) {
            Events\InternalNoteAdded::dispatch($reply);
        } else {
            Events\ReplyCreated::dispatch($reply);
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
        $oldDepartmentId = $ticket->department_id;
        $ticket->update(['department_id' => $departmentId]);

        $this->logActivity($ticket, ActivityType::DepartmentChanged, $causer, [
            'old_department_id' => $oldDepartmentId,
            'new_department_id' => $departmentId,
        ]);

        Events\DepartmentChanged::dispatch($ticket, $oldDepartmentId, $departmentId, $causer);

        return $ticket->fresh();
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Ticketable $causer = null): Ticket
    {
        $oldPriority = $ticket->priority;
        $ticket->update(['priority' => $priority]);

        $this->logActivity($ticket, ActivityType::PriorityChanged, $causer, [
            'old_priority' => $oldPriority->value,
            'new_priority' => $priority->value,
        ]);

        Events\TicketPriorityChanged::dispatch($ticket, $oldPriority, $priority, $causer);

        return $ticket->fresh();
    }

    protected function logActivity(Ticket $ticket, ActivityType $type, ?Ticketable $causer = null, array $properties = []): void
    {
        $activity = new TicketActivity();
        $activity->ticket_id = $ticket->id;
        $activity->type = $type;
        $activity->properties = $properties ?: null;

        if ($causer) {
            $activity->causer_type = $causer->getMorphClass();
            $activity->causer_id = $causer->getKey();
        }

        $activity->save();
    }
}

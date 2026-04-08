<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['question', 'problem', 'incident', 'task'];

    protected $guarded = ['id'];

    protected $dispatchesEvents = [
        'updated' => Events\TicketUpdated::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $ticket) {
            if (empty($ticket->reference)) {
                $ticket->reference = 'TEMP-' . Str::uuid()->toString();
            }

            if (empty($ticket->status)) {
                $ticket->status = TicketStatus::Open;
            }
        });

        static::created(function (self $ticket) {
            // Generate the final reference from the primary key, then dispatch TicketCreated
            // so listeners always receive the real reference (not TEMP-).
            if (Str::startsWith($ticket->reference, 'TEMP-')) {
                $ticket->updateQuietly([
                    'reference' => $ticket->generateReference(),
                ]);
            }

            Events\TicketCreated::dispatch($ticket);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'metadata' => 'array',
            'first_response_at' => 'datetime',
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'sla_first_response_breached' => 'boolean',
            'sla_resolution_breached' => 'boolean',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('tickets');
    }

    public function requester(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class, 'sla_policy_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class, 'ticket_id');
    }

    public function publicReplies(): HasMany
    {
        return $this->replies()->where('is_internal_note', false);
    }

    public function internalNotes(): HasMany
    {
        return $this->replies()->where('is_internal_note', true);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, Escalated::table('ticket_tag'), 'ticket_id', 'tag_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class, 'ticket_id');
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(Reply::class, 'ticket_id')->where('is_internal_note', false)->latestOfMany();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(Escalated::userModel(), Escalated::table('ticket_followers'), 'ticket_id', 'user_id')->withTimestamps();
    }

    public function satisfactionRating(): HasOne
    {
        return $this->hasOne(SatisfactionRating::class, 'ticket_id');
    }

    public function sideConversations(): HasMany
    {
        return $this->hasMany(SideConversation::class, 'ticket_id');
    }

    public function linksAsParent(): HasMany
    {
        return $this->hasMany(TicketLink::class, 'parent_ticket_id');
    }

    public function linksAsChild(): HasMany
    {
        return $this->hasMany(TicketLink::class, 'child_ticket_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function pinnedNotes(): HasMany
    {
        return $this->hasMany(Reply::class, 'ticket_id')->where('is_internal_note', true)->where('is_pinned', true);
    }

    public function isFollowedBy(int $userId): bool
    {
        return $this->followers()->where('user_id', $userId)->exists();
    }

    public function follow(int $userId): void
    {
        $this->followers()->syncWithoutDetaching([$userId]);
    }

    public function unfollow(int $userId): void
    {
        $this->followers()->detach($userId);
    }

    // Scopes

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value]);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeAssignedTo($query, int $agentId)
    {
        return $query->where('assigned_to', $agentId);
    }

    public function scopeWithStatus($query, TicketStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopeWithPriority($query, TicketPriority $priority)
    {
        return $query->where('priority', $priority->value);
    }

    public function scopeInDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeWithTicketType($query, string $ticketType)
    {
        return $query->where('ticket_type', $ticketType);
    }

    public function scopeBreachedSla($query)
    {
        return $query->where(function ($q) {
            $q->where('sla_first_response_breached', true)
              ->orWhere('sla_resolution_breached', true);
        });
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('subject', 'like', "%{$term}%")
              ->orWhere('reference', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('guest_name', 'like', "%{$term}%")
              ->orWhere('guest_email', 'like', "%{$term}%")
              ->orWhereHas('requester', function ($rq) use ($term) {
                  $rq->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
              });
        });
    }

    public function scopeSnoozed($query)
    {
        return $query->whereNotNull('snoozed_until')->where('snoozed_until', '>', now());
    }

    public function scopeNotSnoozed($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now());
        });
    }

    // Guest helpers

    public function isGuest(): bool
    {
        return $this->requester_type === null && $this->guest_token !== null;
    }

    public function getRequesterNameAttribute(): string
    {
        if ($this->isGuest()) {
            return $this->guest_name ?? 'Guest';
        }

        return $this->requester?->ticketable_name ?? 'Unknown';
    }

    public function getRequesterEmailAttribute(): string
    {
        if ($this->isGuest()) {
            return $this->guest_email ?? '';
        }

        return $this->requester?->email ?? '';
    }

    public function getLastReplyAtAttribute(): ?string
    {
        return $this->latestReply?->created_at?->toIso8601String();
    }

    public function getLastReplyAuthorAttribute(): ?string
    {
        return $this->latestReply?->author?->name;
    }

    public function getIsSnoozedAttribute(): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->isFuture();
    }

    // Helpers

    public function generateReference(): string
    {
        $prefix = EscalatedSettings::get('ticket_reference_prefix', 'ESC');

        return sprintf('%s-%05d', $prefix, $this->id);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    protected function canTransitionTo(TicketStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    protected static function newFactory(): \Escalated\Laravel\Database\Factories\TicketFactory
    {
        return \Escalated\Laravel\Database\Factories\TicketFactory::new();
    }

    public function changeDepartment(Department|int $newDepartment, ?Ticketable $causer): self
    {
        // If an ID is provided, attempt to find the department
        if (is_int($newDepartment)) {
            $departmentId = $newDepartment;
            $newDepartment = Department::find($departmentId);

            if (! $newDepartment) {
                throw new \InvalidArgumentException("No department found with ID {$departmentId}");
            }
        }

        $oldDepartmentId = $this->department_id;
        $this->update(['department_id' => $newDepartment->id]);

        $this->logActivity(ActivityType::DepartmentChanged, $causer, [
            'old_department_id' => $oldDepartmentId,
            'new_department_id' => $newDepartment->id,
        ]);

        Events\DepartmentChanged::dispatch($this, $oldDepartmentId, $newDepartment, $causer);

        return $this->fresh();
    }

    public function changePriority(TicketPriority $priority, ?Ticketable $causer = null): self
    {
        $oldPriority = $this->priority;
        $this->update(['priority' => $priority]);

        $this->logActivity(ActivityType::PriorityChanged, $causer, [
            'old_priority' => $oldPriority->value,
            'new_priority' => $priority->value,
        ]);

        Events\TicketPriorityChanged::dispatch($this, $oldPriority, $priority, $causer);

        return $this->fresh();
    }

    public function assign(Model|int $user, ?Ticketable $causer = null): self
    {
        $userModel = Escalated::userModel();

        // If an ID is provided, attempt to find the user
        if (is_int($user)) {
            $userId = $user;
            $user = $userModel::find($userId);

            if (! $user) {
                throw new \InvalidArgumentException("No user found with ID {$userId}");
            }
        }

        // If an Eloquent model is provided, ensure it's the correct type
        if (! $user instanceof $userModel) {
            throw new \InvalidArgumentException("Assigned user must be an instance of {$userModel}");
        }

        $this->update(['assigned_to' => $user->id]);

        $this->logActivity(ActivityType::Assigned, $causer, ['agent_id' => $user->id]);

        Events\TicketAssigned::dispatch($this, $user->id, $causer);

        return $this->fresh();
    }

    public function unassignTicket(?Ticketable $causer = null): self
    {
        $previousAgentId = $this->assigned_to;
        $this->update(['assigned_to' => null]);

        $this->logActivity(ActivityType::Unassigned, $causer, ['previous_agent_id' => $previousAgentId]);

        Events\TicketUnassigned::dispatch($this, $previousAgentId, $causer);

        return $this->fresh();
    }

    public function logActivity(ActivityType $type, ?Ticketable $causer = null, array $properties = []): void
    {
        $data = [
            'type' => $type,
            'properties' => $properties ?: null,
        ];

        if ($causer) {
            $data['causer_type'] = $causer->getMorphClass();
            $data['causer_id'] = $causer->getKey();
        }

        $this->activities()->create($data);
    }

    public function addReply(Ticketable $author, string $body, bool $isNote = false): Reply
    {
        $reply = $this->replies()->create([
            'ticket_id' => $this->id,
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
            'body' => $body,
            'is_internal_note' => $isNote,
            'type' => $isNote ? 'note' : 'reply',
        ]);

        $activityType = $isNote ? ActivityType::NoteAdded : ActivityType::Replied;
        $this->logActivity($activityType, $author);

        // ReplyCreated / InternalNoteAdded events are automatically dispatched by Reply::booted()

        return $reply;
    }

    public function markResolved(?Ticketable $causer = null): self
    {
        $oldStatus = $this->status;
        $newStatus = TicketStatus::Resolved;

        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value} to {$newStatus->value}");
        }

        $this->update([
            'status' => $newStatus->value,
            'resolved_at' => now(),
        ]);

        // Moved logging to Listener of TicketStatusChanged event

        Events\TicketStatusChanged::dispatch($this, $oldStatus, $newStatus, $causer);
        Events\TicketResolved::dispatch($this, $causer);

        return $this->fresh();
    }

    public function markClosed(?Ticketable $causer = null): self
    {
        $oldStatus = $this->status;
        $newStatus = TicketStatus::Closed;

        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value} to {$newStatus->value}");
        }

        $this->update([
            'closed_at' => now(),
            'status' => $newStatus->value,
        ]);

        // Moved logging to Listener of TicketStatusChanged event

        Events\TicketStatusChanged::dispatch($this, $oldStatus, $newStatus, $causer);
        Events\TicketClosed::dispatch($this, $causer);

        return $this->fresh();
    }

    public function markReopened(?Ticketable $causer = null): self
    {
        $oldStatus = $this->status;
        $newStatus = TicketStatus::Reopened;

        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value} to {$newStatus->value}");
        }

        $this->update([
            'resolved_at' => null,
            'closed_at' => null,
            'status' => $newStatus->value,
        ]);

        // Moved logging to Listener of TicketStatusChanged event

        Events\TicketStatusChanged::dispatch($this, $oldStatus, $newStatus, $causer);
        Events\TicketReopened::dispatch($this, $causer);

        return $this->fresh();
    }

    public function markEscalated(?Ticketable $causer = null): self
    {
        $oldStatus = $this->status;
        $newStatus = TicketStatus::Escalated;

        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value} to {$newStatus->value}");
        }

        $this->update([
            'status' => $newStatus->value,
        ]);

        // Moved logging to Listener of TicketStatusChanged event

        Events\TicketStatusChanged::dispatch($this, $oldStatus, $newStatus, $causer);
        Events\TicketEscalated::dispatch($this, $causer);

        return $this->fresh();
    }

    public function transitionTo(TicketStatus $newStatus, ?Ticketable $causer = null): self
    {
        // Handle special status transitions with dedicated methods to ensure proper timestamp management and event dispatching
        switch ($newStatus) {
            case TicketStatus::Resolved:
                return $this->markResolved($causer);

            case TicketStatus::Closed:
                return $this->markClosed($causer);

            case TicketStatus::Reopened:
                return $this->markReopened($causer);

            case TicketStatus::Escalated:
                return $this->markEscalated($causer);
        }

        $oldStatus = $this->status;

        if (! $oldStatus->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$this->status->value} to {$newStatus->value}");
        }

        $this->update([
            'status' => $newStatus->value,
        ]);

        // Moved logging to Listener of TicketStatusChanged event

        Events\TicketStatusChanged::dispatch($this, $oldStatus, $newStatus, $causer);

        return $this->fresh();
    }

}

<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['question', 'problem', 'incident', 'task'];

    protected $guarded = ['id'];

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

    protected static function newFactory(): \Escalated\Laravel\Database\Factories\TicketFactory
    {
        return \Escalated\Laravel\Database\Factories\TicketFactory::new();
    }
}

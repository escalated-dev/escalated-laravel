<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowLog extends Model
{
    protected $guarded = ['id'];

    protected $appends = [
        'workflow_name',
        'ticket_reference',
        'event',
        'matched',
        'duration_ms',
        'status',
        'action_details',
    ];

    public function getTable(): string
    {
        return Escalated::table('workflow_logs');
    }

    protected function casts(): array
    {
        return [
            'conditions_matched' => 'boolean',
            'actions_executed' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function getWorkflowNameAttribute(): ?string
    {
        return $this->workflow?->name;
    }

    public function getTicketReferenceAttribute(): ?string
    {
        return $this->ticket?->reference;
    }

    public function getEventAttribute(): ?string
    {
        return $this->trigger_event;
    }

    public function getMatchedAttribute(): bool
    {
        return (bool) $this->conditions_matched;
    }

    public function getDurationMsAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds($this->completed_at);
    }

    public function getStatusAttribute(): string
    {
        return $this->error ? 'failed' : 'success';
    }

    public function getActionDetailsAttribute(): array
    {
        return is_array($this->actions_executed) ? $this->actions_executed : [];
    }
}

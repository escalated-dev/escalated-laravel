<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['trigger'];

    public function getTable(): string
    {
        return Escalated::table('workflows');
    }

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'position' => 'integer',
            'trigger_count' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'created_by');
    }

    public function workflowLogs(): HasMany
    {
        return $this->hasMany(WorkflowLog::class, 'workflow_id');
    }

    public function delayedActions(): HasMany
    {
        return $this->hasMany(DelayedAction::class, 'workflow_id');
    }

    public function getTriggerAttribute(): ?string
    {
        return $this->trigger_event;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('trigger_event', $event);
    }
}

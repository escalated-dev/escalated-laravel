<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelayedAction extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('delayed_actions');
    }

    protected function casts(): array
    {
        return [
            'action' => 'array',
            'remaining_actions' => 'array',
            'execute_at' => 'datetime',
            'executed' => 'boolean',
            'cancelled' => 'boolean',
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

    public function scopePending($query)
    {
        return $query->where('executed', false)->where('cancelled', false);
    }

    public function scopeDue($query)
    {
        return $query->where('execute_at', '<=', now())
            ->where('executed', false)
            ->where('cancelled', false);
    }
}

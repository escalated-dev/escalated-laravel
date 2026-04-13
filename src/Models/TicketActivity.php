<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TicketActivity extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['created_at_human'];

    public $timestamps = true;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'properties' => 'array',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('ticket_activities');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function getCreatedAtHumanAttribute(): string
    {
        return $this->created_at?->diffForHumans() ?? '';
    }
}

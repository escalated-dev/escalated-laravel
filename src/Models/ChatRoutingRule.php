<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Enums\OfflineBehavior;
use Escalated\Laravel\Enums\RoutingStrategy;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRoutingRule extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('chat_routing_rules');
    }

    protected function casts(): array
    {
        return [
            'routing_strategy' => RoutingStrategy::class,
            'offline_behavior' => OfflineBehavior::class,
            'max_queue_size' => 'integer',
            'max_concurrent_per_agent' => 'integer',
            'auto_close_after_minutes' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }
}

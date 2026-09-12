<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCapacity extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('agent_capacity');
    }

    protected function casts(): array
    {
        return [
            'max_concurrent' => 'integer',
            'current_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'user_id');
    }

    public function loadPercentage(): float
    {
        if ($this->max_concurrent <= 0) {
            return 100.0;
        }

        return round(($this->current_count / $this->max_concurrent) * 100, 1);
    }

    public function hasCapacity(): bool
    {
        return $this->current_count < $this->max_concurrent;
    }
}

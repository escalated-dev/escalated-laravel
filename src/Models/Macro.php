<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Macro extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('macros');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'created_by');
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where(function ($q) use ($agentId) {
            $q->where('is_shared', true)
                ->orWhere('created_by', $agentId);
        });
    }
}

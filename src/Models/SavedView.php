<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedView extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'is_shared' => 'boolean',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('saved_views');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'user_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('is_shared', true);
        });
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }
}

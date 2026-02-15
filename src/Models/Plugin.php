<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $fillable = [
        'slug',
        'is_active',
        'activated_at',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('plugins');
    }

    /**
     * Scope to get only active plugins.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive plugins.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}

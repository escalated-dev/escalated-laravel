<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('automations');
    }

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'active' => 'boolean',
            'position' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('position');
    }
}

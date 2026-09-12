<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessSchedule extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('business_schedules');
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class, 'schedule_id');
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}

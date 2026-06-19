<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holiday extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'recurring' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('holidays');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BusinessSchedule::class, 'schedule_id');
    }
}

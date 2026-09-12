<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SatisfactionRating extends Model
{
    use UsesEscalatedConnection;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('satisfaction_ratings');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function ratedBy(): MorphTo
    {
        return $this->morphTo('rated_by');
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }
}

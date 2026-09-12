<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('audit_logs');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            if (is_null($log->created_at)) {
                $log->created_at = now();
            }
        });
    }
}

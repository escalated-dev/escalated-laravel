<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactor extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('two_factor');
    }

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'encrypted:array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'user_id');
    }

    /**
     * Check if two-factor is confirmed (fully set up).
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}

<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;

class GuestVerification extends Model
{
    use UsesEscalatedConnection;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'email', 'purpose', 'code_hash', 'attempts', 'expires_at', 'used_at'];

    protected $hidden = ['email', 'code_hash'];

    protected $casts = ['email' => 'encrypted', 'attempts' => 'integer', 'expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function getTable(): string
    {
        return Escalated::table('guest_verifications');
    }
}

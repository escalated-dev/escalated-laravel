<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    public $timestamps = false;

    public function getTable(): string
    {
        return Escalated::table('permissions');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, Escalated::table('role_permission'), 'permission_id', 'role_id');
    }
}

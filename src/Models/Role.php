<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('roles');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, Escalated::table('role_permission'), 'role_id', 'permission_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Escalated::userModel(), Escalated::table('role_user'), 'role_id', 'user_id');
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    protected static function booted(): void
    {
        static::creating(function (self $role) {
            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name, '_');
            }
        });
    }
}

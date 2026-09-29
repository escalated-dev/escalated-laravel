<?php

namespace Escalated\Laravel\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** The host owns account selection, membership and host-entity visibility. */
interface TenantResolver
{
    public function resolve(Request $request): int|string|null;

    public function canAccess(Model $user, string $tenantId): bool;

    public function canReference(Model $model, string $tenantId): bool;

    /** Constrain host identity/subject discovery on that model's own connection. */
    public function scope(Builder $query, string $tenantId): void;

    /** Platform access is separate from being an administrator of one account. */
    public function isPlatformAdmin(Model $user): bool;
}

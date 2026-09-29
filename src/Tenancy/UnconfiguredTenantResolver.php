<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Contracts\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class UnconfiguredTenantResolver implements TenantResolver
{
    public function resolve(Request $request): int|string|null
    {
        return null;
    }

    public function canAccess(Model $user, string $tenantId): bool
    {
        return false;
    }

    public function canReference(Model $model, string $tenantId): bool
    {
        return false;
    }

    public function scope(Builder $query, string $tenantId): void
    {
        $query->whereRaw('1 = 0');
    }

    public function isPlatformAdmin(Model $user): bool
    {
        return false;
    }
}

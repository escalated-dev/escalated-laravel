<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Tenancy\UnconfiguredTenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TestTenantResolver extends UnconfiguredTenantResolver
{
    public ?string $selected = null;

    public array $members = [];

    /** Tenant-local seats. Null keeps the fixtures' default: every member is staff. */
    public ?array $agents = null;

    public ?array $admins = null;

    public function resolve(Request $request): int|string|null
    {
        return $request->attributes->get('trusted_tenant', $this->selected);
    }

    public function canAccess(Model $user, string $tenantId): bool
    {
        return in_array($user->getKey(), $this->members[$tenantId] ?? [], true);
    }

    public function isAgent(Model $user, string $tenantId): bool
    {
        return in_array($user->getKey(), ($this->agents ?? $this->members)[$tenantId] ?? [], true);
    }

    public function isAdmin(Model $user, string $tenantId): bool
    {
        return in_array($user->getKey(), ($this->admins ?? $this->members)[$tenantId] ?? [], true);
    }

    public function canReference(Model $model, string $tenantId): bool
    {
        return $this->canAccess($model, $tenantId);
    }

    public function scope(Builder $query, string $tenantId): void
    {
        $query->whereKey($this->members[$tenantId] ?? []);
    }
}

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

    public function resolve(Request $request): int|string|null
    {
        return $request->attributes->get('trusted_tenant', $this->selected);
    }

    public function canAccess(Model $user, string $tenantId): bool
    {
        return in_array($user->getKey(), $this->members[$tenantId] ?? [], true);
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

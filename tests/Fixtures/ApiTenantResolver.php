<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Tenancy\UnconfiguredTenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApiTenantResolver extends UnconfiguredTenantResolver
{
    public array $members = [];

    /** Tenant-local seats. Null keeps the fixtures' default: every member is staff. */
    public ?array $agents = null;

    public ?array $admins = null;

    public array $denied = [];

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
        return ! in_array($model::class.':'.$model->getKey(), $this->denied, true)
            && ($model instanceof ApiShipment ? $model->account === $tenantId : $this->canAccess($model, $tenantId));
    }

    public function scope(Builder $query, string $tenantId): void
    {
        if ($query->getModel() instanceof ApiShipment) {
            $query->where('account', $tenantId);
        } else {
            $query->whereKey($this->members[$tenantId] ?? []);
        }
    }
}

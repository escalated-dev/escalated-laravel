<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Tenancy\UnconfiguredTenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApiTenantResolver extends UnconfiguredTenantResolver
{
    public array $members = [];

    public array $denied = [];

    public function canAccess(Model $user, string $tenantId): bool
    {
        return in_array($user->getKey(), $this->members[$tenantId] ?? [], true);
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

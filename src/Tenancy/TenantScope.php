<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        if ($context->enabled() && TenantTables::contains($model->getTable())) {
            $builder->where($model->qualifyColumn('tenant_id'), $context->id());
        }
    }
}

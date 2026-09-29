<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Database\Eloquent\ConnectionPropagation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

trait GuardsHostRelation
{
    protected function assertTenantParent(): void
    {
        if (app(TenantContext::class)->enabled() && $this->parent->exists && TenantTables::contains($this->parent->getTable())) {
            app(TenantContext::class)->assertOwns($this->parent);
        }
    }

    protected function visibleHostResult(?Model $result): ?Model
    {
        $context = app(TenantContext::class);
        if ($result && $context->enabled() && ! ConnectionPropagation::belongsToEscalated($result)
            && ! $context->resolver()->canReference($result, $context->id())) {
            return null;
        }

        return $result;
    }

    protected function assertHostReadOnly(): void
    {
        $this->assertTenantParent();
        if (app(TenantContext::class)->enabled() && ! ConnectionPropagation::belongsToEscalated($this->related)) {
            throw new AuthorizationException('Host records must be changed through the host application.');
        }
    }

    public function __call($method, $parameters)
    {
        $this->assertTenantParent();
        if (preg_match('/^(?:save|create|force|update|insert|fill|delete|increment|decrement|touch|truncate|firstOrCreate)/i', $method)) {
            $this->assertHostReadOnly();
        }

        return parent::__call($method, $parameters);
    }

    public function rawUpdate(array $attributes = [])
    {
        $this->assertHostReadOnly();

        return $this->query->update($attributes);
    }

    public function touch()
    {
        $this->assertHostReadOnly();

        return parent::touch();
    }
}

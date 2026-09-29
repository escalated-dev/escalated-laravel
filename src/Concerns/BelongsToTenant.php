<?php

namespace Escalated\Laravel\Concerns;

use Escalated\Laravel\Tenancy\TenantBuilder;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tenancy\TenantReferences;
use Escalated\Laravel\Tenancy\TenantScope;
use Escalated\Laravel\Tenancy\TenantTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function newEloquentBuilder($query)
    {
        return TenantTables::contains($this->getTable()) ? new TenantBuilder($query) : parent::newEloquentBuilder($query);
    }

    protected function tenantBoundaryEnabled(): bool
    {
        return app(TenantContext::class)->enabled() && TenantTables::contains($this->getTable());
    }

    protected function assertTenantIdentity(): void
    {
        if (! $this->tenantBoundaryEnabled()) {
            return;
        }
        $tenant = app(TenantContext::class)->id();
        if ($this->exists && ($this->getRawOriginal('tenant_id') !== $tenant || $this->getAttribute('tenant_id') !== $tenant)) {
            throw new AuthorizationException('This record belongs to another tenant or its ownership was changed.');
        }
    }

    public function save(array $options = [])
    {
        if ($this->tenantBoundaryEnabled()) {
            $this->assertTenantIdentity();
            $tenant = app(TenantContext::class)->id();
            if (! $this->exists) {
                $provided = $this->getAttribute('tenant_id');
                if ($provided !== null && $provided !== $tenant) {
                    throw new AuthorizationException('Tenant identity is supplied by the host context.');
                }
                $this->setAttribute('tenant_id', $tenant);
                // created callbacks may save the freshly inserted model before
                // Eloquent's finishSave has synchronized its original attributes.
                $this->syncOriginalAttribute('tenant_id');
                if ($this->getKey() !== null) {
                    $this->syncOriginalAttribute($this->getKeyName());
                }
            }
            app(TenantReferences::class)->validate($this, $this->exists ? $this->getDirty() : $this->getAttributes());
        }

        return parent::save($options);
    }

    protected function insertAndSetId(Builder $query, $attributes)
    {
        parent::insertAndSetId($query, $attributes);
        if ($this->tenantBoundaryEnabled()) {
            $this->syncOriginalAttribute($this->getKeyName());
        }
    }

    public function delete()
    {
        $this->assertTenantIdentity();

        return parent::delete();
    }

    public function fresh($with = [])
    {
        $this->assertTenantIdentity();

        return parent::fresh($with);
    }

    public function refresh()
    {
        $this->assertTenantIdentity();

        return parent::refresh();
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        $this->assertTenantIdentity();
        if ($this->tenantBoundaryEnabled()) {
            if (! is_string($column) || app(TenantReferences::class)->isReferenceColumn($column)) {
                throw new AuthorizationException('Identity columns cannot be incremented.');
            }
            app(TenantReferences::class)->validate($this, $extra);
        }

        return parent::incrementOrDecrement($column, $amount, $extra, $method);
    }

    public function newQueryForRestoration($ids)
    {
        // Queue restoration normally removes all global scopes. Keep the tenant
        // boundary even when restoring a model before a job's middleware runs.
        return $this->newQueryWithoutScopes()->withGlobalScope(TenantScope::class, new TenantScope)->whereKey($ids);
    }
}

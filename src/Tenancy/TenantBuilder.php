<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantBuilder extends Builder
{
    public function getModels($columns = ['*'])
    {
        if (! app(TenantContext::class)->enabled()) {
            return parent::getModels($columns);
        }
        $builder = clone $this;
        $builder->callScope(fn ($query) => $query->where($this->model->qualifyColumn('tenant_id'), app(TenantContext::class)->id()));

        return $this->model->hydrate($builder->getQuery()->get($columns)->all())->all();
    }

    public function forceDelete()
    {
        if (! app(TenantContext::class)->enabled()) {
            return parent::forceDelete();
        }

        return $this->withoutGlobalScope(SoftDeletingScope::class)->toBase()->delete();
    }

    public function touch($column = null)
    {
        if ($column !== null && app(TenantContext::class)->enabled()) {
            foreach ((array) $column as $name) {
                if (! is_string($name) || str_contains($name, '.') || app(TenantReferences::class)->isReferenceColumn($name)) {
                    throw new AuthorizationException('Identity columns cannot be timestamped.');
                }
            }
        }

        return parent::touch($column);
    }

    public function applyScopes()
    {
        $builder = parent::applyScopes();
        if (app(TenantContext::class)->enabled()) {
            // Framework writes/restoration can remove optional global scopes.
            // Tenant ownership is mandatory on these package-owned tables.
            $builder = clone $builder;
            $builder->callScope(fn ($query) => $query->where($this->model->qualifyColumn('tenant_id'), app(TenantContext::class)->id()));
        }

        return $builder;
    }

    public function update(array $values)
    {
        if (app(TenantContext::class)->enabled() && array_key_exists('id', $values)) {
            throw new AuthorizationException('Existing row identities cannot be reassigned.');
        }
        $this->validateWrite($values);

        return parent::update($values);
    }

    public function insert(array $values)
    {
        if (! app(TenantContext::class)->enabled() || $values === []) {
            return $this->toBase()->insert($values);
        }
        $rows = is_array(reset($values)) ? $values : [$values];
        foreach ($rows as &$row) {
            $this->validateWrite($row);
            $row['tenant_id'] = app(TenantContext::class)->id();
        }

        return $this->toBase()->insert($rows);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $this->validateWrite($values);
        if (app(TenantContext::class)->enabled()) {
            $values['tenant_id'] = app(TenantContext::class)->id();
        }

        return $this->toBase()->insertGetId($values, $sequence);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->rejectUnsafeWrite();

        return parent::upsert($values, $uniqueBy, $update);
    }

    public function __call($method, $parameters)
    {
        if (in_array(strtolower($method), ['insertorignore', 'insertorignorereturning', 'insertusing', 'insertorignoreusing', 'updateorinsert', 'updatefrom', 'truncate', 'incrementeach', 'decrementeach'], true)) {
            $this->rejectUnsafeWrite();
        }

        return parent::__call($method, $parameters);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->validateIncrement($column, $extra);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->validateIncrement($column, $extra);

        return parent::decrement($column, $amount, $extra);
    }

    // Laravel 13 added concrete methods, so __call alone no longer guards these.
    public function incrementEach(array $columns, array $extra = [])
    {
        $this->rejectUnsafeWrite();

        return method_exists(Builder::class, 'incrementEach')
            ? parent::incrementEach($columns, $extra)
            : parent::__call('incrementEach', [$columns, $extra]);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->rejectUnsafeWrite();

        return method_exists(Builder::class, 'decrementEach')
            ? parent::decrementEach($columns, $extra)
            : parent::__call('decrementEach', [$columns, $extra]);
    }

    private function validateIncrement($column, array $extra): void
    {
        if (app(TenantContext::class)->enabled()) {
            if (! is_string($column) || app(TenantReferences::class)->isReferenceColumn($column)) {
                throw new AuthorizationException('Identity columns cannot be incremented.');
            }
            $this->validateWrite($extra);
        }
    }

    private function validateWrite(array $values): void
    {
        app(TenantReferences::class)->validate($this->model, $values);
        if (app(TenantContext::class)->enabled() && array_key_exists('id', $values) && $this->model->exists) {
            throw new AuthorizationException('Existing row identities cannot be reassigned.');
        }
    }

    private function rejectUnsafeWrite(): void
    {
        if (app(TenantContext::class)->enabled()) {
            throw new AuthorizationException('Use tenant-scoped model creation and updates for this operation.');
        }
    }
}

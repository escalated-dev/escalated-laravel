<?php

namespace Escalated\Laravel\Tenancy;

use Closure;
use Escalated\Laravel\Database\Eloquent\ConnectionPropagation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TenantBelongsToMany extends BelongsToMany
{
    private ?string $resolvedTenant = null;

    public function __construct(Builder $query, Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        $this->resolvedTenant = app(TenantContext::class)->enabled() ? app(TenantContext::class)->id() : null;
        if (! ConnectionPropagation::belongsToEscalated($query->getModel())) {
            // Keep this query-local and deferred: existence/count queries replace
            // the host SQL with a package pivot subquery on separate databases.
            $query->withGlobalScope('escalated-host-tenant', fn (Builder $builder) => app(TenantContext::class)->scopeHost($builder));
        }
        parent::__construct($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
        if ($this->tenantScoped()) {
            $this->withPivot('tenant_id');
        }
    }

    protected function tenantScoped(): bool
    {
        return app(TenantContext::class)->enabled() && TenantTables::contains($this->table);
    }

    protected function performJoin($query = null)
    {
        parent::performJoin($query);
        if ($this->tenantScoped()) {
            ($query ?? $this->query)->withGlobalScope('escalated-pivot-tenant', fn (Builder $builder) => $builder->where($this->qualifyPivotColumn('tenant_id'), app(TenantContext::class)->id()));
        }

        return $this;
    }

    public function newPivotStatement()
    {
        $this->assertCurrentContext();
        $query = $this->parent->getConnection()->table($this->table);
        if ($this->tenantScoped()) {
            $query->where($this->qualifyPivotColumn('tenant_id'), app(TenantContext::class)->id());
        }

        return $query;
    }

    protected function assertCurrentContext(): void
    {
        if ($this->tenantScoped()) {
            if ($this->resolvedTenant !== app(TenantContext::class)->id()) {
                throw new AuthorizationException('A relationship cannot be reused across tenant contexts.');
            }
            if ($this->parent->exists) {
                $this->assertParent();
            }
        }
    }

    public function get($columns = ['*'])
    {
        $this->assertCurrentContext();

        return parent::get($columns);
    }

    public function getEager()
    {
        $this->assertCurrentContext();

        return parent::getEager();
    }

    public function __call($method, $parameters)
    {
        $this->assertCurrentContext();
        if (preg_match('/^(?:save|create|force|update|insert|fill|delete|increment|decrement|touch|truncate|upsert|firstOrCreate)/i', $method)) {
            $this->assertRelatedWritable();
        }

        return parent::__call($method, $parameters);
    }

    public function rawUpdate(array $attributes = [])
    {
        $this->assertRelatedWritable();

        return $this->query->update($attributes);
    }

    public function touch()
    {
        $this->assertRelatedWritable();

        return parent::touch();
    }

    private function assertRelatedWritable(): void
    {
        $this->assertCurrentContext();
        if ($this->tenantScoped() && ! ConnectionPropagation::belongsToEscalated($this->related)) {
            throw new AuthorizationException('Host identities must be managed by the host. Use attach/sync for tenant membership.');
        }
    }

    public function save(Model $model, array $pivotAttributes = [], $touch = true)
    {
        $this->assertRelatedWritable();

        return parent::save($model, $pivotAttributes, $touch);
    }

    public function create(array $attributes = [], array $joining = [], $touch = true)
    {
        $this->assertRelatedWritable();

        return parent::create($attributes, $joining, $touch);
    }

    public function firstOrCreate(array $attributes = [], Closure|array $values = [], array $joining = [], $touch = true)
    {
        $this->assertRelatedWritable();

        return parent::firstOrCreate($attributes, $values, $joining, $touch);
    }

    public function createOrFirst(array $attributes = [], Closure|array $values = [], array $joining = [], $touch = true)
    {
        $this->assertRelatedWritable();

        return parent::createOrFirst($attributes, $values, $joining, $touch);
    }

    public function updateOrCreate(array $attributes, array|Closure $values = [], array $joining = [], $touch = true)
    {
        $this->assertRelatedWritable();

        return parent::updateOrCreate($attributes, $values, $joining, $touch);
    }

    protected function formatAttachRecords($ids, array $attributes)
    {
        $records = parent::formatAttachRecords($ids, $attributes);
        if ($this->tenantScoped()) {
            $this->assertParent();
            foreach ($records as &$record) {
                $this->assertAttributes($record, false);
                if ((string) $record[$this->foreignPivotKey] !== (string) $this->parent->getAttribute($this->parentKey)) {
                    throw new AuthorizationException('A relationship parent cannot be reassigned.');
                }
                app(TenantReferences::class)->assertReference($this->related, $record[$this->relatedPivotKey]);
                app(TenantReferences::class)->validate((new TenantRow)->setTable($this->table), $record);
                $record['tenant_id'] = app(TenantContext::class)->id();
            }
        }

        return $records;
    }

    public function sync($ids, $detaching = true)
    {
        if (! $this->tenantScoped()) {
            return parent::sync($ids, $detaching);
        }
        $this->assertParent();
        // Validate the complete proposed membership before detaching old rows.
        $this->formatAttachRecords($this->parseIds($ids), []);

        return $this->parent->getConnection()->transaction(fn () => parent::sync($ids, $detaching));
    }

    public function toggle($ids, $touch = true)
    {
        if (! $this->tenantScoped()) {
            return parent::toggle($ids, $touch);
        }
        $this->assertParent();
        $this->formatAttachRecords($this->parseIds($ids), []);

        return $this->parent->getConnection()->transaction(fn () => parent::toggle($ids, $touch));
    }

    public function detach($ids = null, $touch = true)
    {
        $this->assertParent();

        return parent::detach($ids, $touch);
    }

    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        $this->assertParent();
        $this->assertAttributes($attributes, true);

        return parent::updateExistingPivot($id, $attributes, $touch);
    }

    public function newPivot(array $attributes = [], $exists = false)
    {
        if (! $this->tenantScoped()) {
            return parent::newPivot($attributes, $exists);
        }

        return TenantPivot::fromAttributes($this->parent, $attributes, $this->table, $exists)
            ->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey)
            ->setRelatedModel($this->related);
    }

    private function assertParent(): void
    {
        if ($this->tenantScoped() && TenantTables::contains($this->parent->getTable())) {
            app(TenantContext::class)->assertOwns($this->parent);
        }
    }

    private function assertAttributes(array $attributes, bool $updating): void
    {
        if (! $this->tenantScoped()) {
            return;
        }
        app(TenantReferences::class)->validate((new TenantRow)->setTable($this->table), $attributes);
        if (array_key_exists('tenant_id', $attributes) && $attributes['tenant_id'] !== app(TenantContext::class)->id()) {
            throw new AuthorizationException('Pivot tenant identity is immutable.');
        }
        if ($updating && (array_key_exists($this->foreignPivotKey, $attributes) || array_key_exists($this->relatedPivotKey, $attributes))) {
            throw new AuthorizationException('Pivot identities cannot be reassigned.');
        }
    }
}

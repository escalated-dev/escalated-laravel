<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Escalated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;

/** Raw reporting/maintenance queries with the same boundary as package models. */
class TenantQueryBuilder extends Builder
{
    private bool $tenantApplied = false;

    public function toSql()
    {
        return $this->mustScope() ? $this->scopedCopy()->toSql() : parent::toSql();
    }

    public function getBindings()
    {
        return $this->mustScope() ? $this->scopedCopy()->getBindings() : parent::getBindings();
    }

    public function exists()
    {
        return $this->mustScope() ? $this->scopedCopy()->exists() : parent::exists();
    }

    public function update(array $values)
    {
        if ($this->mustScope()) {
            if (array_key_exists('id', $values)) {
                throw new AuthorizationException('Row identities cannot be reassigned.');
            }
            $this->validateValues($values);

            return $this->scopedCopy()->update($values);
        }

        return parent::update($values);
    }

    public function delete($id = null)
    {
        if ($this->mustScope()) {
            $this->validateValues([]);

            return $this->scopedCopy()->delete($id);
        }

        return parent::delete($id);
    }

    public function insert(array $values)
    {
        if ($this->mustScope() && $values !== []) {
            $rows = is_array(reset($values)) ? $values : [$values];
            foreach ($rows as &$row) {
                $this->validateValues($row);
                $row['tenant_id'] = app(TenantContext::class)->id();
            }

            return $this->scopedCopy()->insert($rows);
        }

        return parent::insert($values);
    }

    public function insertGetId(array $values, $sequence = null)
    {
        if ($this->mustScope()) {
            $this->validateValues($values);
            $values['tenant_id'] = app(TenantContext::class)->id();

            return $this->scopedCopy()->insertGetId($values, $sequence);
        }

        return parent::insertGetId($values, $sequence);
    }

    public function insertOrIgnore(array $values)
    {
        $this->rejectUnsafeWrite();

        return parent::insertOrIgnore($values);
    }

    public function insertOrIgnoreReturning(array $values, array $returning = ['*'], array|string|null $uniqueBy = null)
    {
        $this->rejectUnsafeWrite();

        return parent::insertOrIgnoreReturning($values, $returning, $uniqueBy);
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        $this->rejectUnsafeWrite();

        return parent::updateOrInsert($attributes, $values);
    }

    public function updateFrom(array $values)
    {
        // PostgreSQL compiles this without calling toSql/getBindings/update.
        $this->rejectUnsafeWrite();

        return parent::updateFrom($values);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->rejectUnsafeWrite();

        return parent::upsert($values, $uniqueBy, $update);
    }

    public function insertUsing(array $columns, $query)
    {
        $this->rejectUnsafeWrite();

        return parent::insertUsing($columns, $query);
    }

    public function insertOrIgnoreUsing(array $columns, $query)
    {
        $this->rejectUnsafeWrite();

        return parent::insertOrIgnoreUsing($columns, $query);
    }

    public function truncate()
    {
        $this->rejectUnsafeWrite();

        return parent::truncate();
    }

    private function rejectUnsafeWrite(): void
    {
        if (app(TenantContext::class)->enabled()) {
            throw new AuthorizationException('Use tenant-scoped model creation and updates for this operation.');
        }
    }

    private function mustScope(): bool
    {
        return ! $this->tenantApplied && app(TenantContext::class)->enabled();
    }

    private function validateValues(array $values): void
    {
        [$table] = $this->tableParts($this->from);
        if (! TenantTables::contains($table)) {
            throw new AuthorizationException('Platform tables cannot be written through tenant queries.');
        }
        app(TenantReferences::class)->validate((new TenantRow)->setTable($table), $values);
    }

    private function scopedCopy(): static
    {
        $tenant = app(TenantContext::class)->id();
        $copy = clone $this;
        $copy->tenantApplied = true;
        [$table, $alias] = $this->tableParts($copy->from);
        if (! TenantTables::contains($table) && $table !== Escalated::table('permissions')) {
            throw new AuthorizationException('This table is not available through tenant queries.');
        }
        if (TenantTables::contains($table)) {
            // Group caller OR predicates before applying the mandatory boundary.
            if ($copy->wheres !== []) {
                $nested = $copy->forNestedWhere();
                $nested->wheres = $copy->wheres;
                $nested->setBindings($copy->bindings['where'], 'where');
                $copy->wheres = [];
                $copy->setBindings([], 'where');
                $copy->addNestedWhereQuery($nested);
            }
            $copy->where($alias.'.tenant_id', $tenant);
        }
        if ($copy->joins) {
            $copy->setBindings([], 'join');
            foreach ($copy->joins as $index => $join) {
                $join = clone $join;
                [$joinTable, $joinAlias] = $this->tableParts($join->table);
                if (TenantTables::contains($joinTable)) {
                    // ON keeps optional department/tag left joins optional.
                    // Group the entire existing ON predicate before appending
                    // tenant ownership, including caller-supplied OR clauses.
                    if ($join->wheres !== []) {
                        $nested = $join->forNestedWhere();
                        $nested->wheres = $join->wheres;
                        $nested->setBindings($join->bindings['where'], 'where');
                        $join->wheres = [];
                        $join->setBindings([], 'where');
                        $join->addNestedWhereQuery($nested);
                    }
                    $join->where($joinAlias.'.tenant_id', $tenant);
                } elseif ($joinTable !== Escalated::table('permissions')) {
                    throw new AuthorizationException('Host identities must be resolved on their own scoped connection.');
                }
                $copy->joins[$index] = $join;
                $copy->addBinding($join->getBindings(), 'join');
            }
        }

        return $copy;
    }

    private function tableParts(mixed $table): array
    {
        if (! is_string($table) || ! preg_match('/^([a-zA-Z0-9_]+)(?:\s+(?:as\s+)?([a-zA-Z0-9_]+))?$/i', $table, $matches)) {
            throw new AuthorizationException('Tenant queries require explicit package table names.');
        }

        return [$matches[1], $matches[2] ?? $matches[1]];
    }
}

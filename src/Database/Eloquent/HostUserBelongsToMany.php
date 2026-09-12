<?php

namespace Escalated\Laravel\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Expression;

/**
 * A many-to-many between an Escalated pivot table and the HOST's user table.
 *
 * Four of Escalated's relations look like this — department agents, role
 * members, skill agents and ticket followers — and they are the one shape that
 * a configurable connection genuinely breaks. Eloquent resolves a
 * `belongsToMany` with a single SQL statement that JOINs the pivot to the
 * related table, and **no database can join across two connections**. Once
 * Escalated's tables move, that join names a pivot the host's database has
 * never heard of.
 *
 * So when (and only when) the two land on different connections, this resolves
 * the relation in two steps instead: read the pivot on Escalated's connection,
 * then load the users by key on the host's. Both are indexed primary-key
 * lookups, so the cost is one extra round trip rather than a worse query.
 *
 * When both live on the same connection — the default, and what almost every
 * host runs — nothing here diverges from Eloquent at all: every override falls
 * through to the parent, and the single JOIN is still issued.
 */
class HostUserBelongsToMany extends BelongsToMany
{
    /**
     * The pivot rows for a set of parent keys, keyed by parent key.
     *
     * Populated once per relation resolution and reused by match(), so eager
     * loading a hundred tickets still costs one pivot query.
     *
     * @var array<string, array<int, object>>|null
     */
    protected ?array $pivotRowCache = null;

    /**
     * Whether the pivot table and the related model are on different
     * connections, which is the only situation this class behaves differently
     * in.
     */
    public function crossesConnections(): bool
    {
        return $this->pivotConnectionName() !== $this->related->getConnectionName();
    }

    /**
     * The connection the pivot table lives on: the parent's, because the pivot
     * is one of Escalated's own tables.
     */
    protected function pivotConnectionName(): ?string
    {
        return $this->parent->getConnectionName();
    }

    /**
     * A query builder against the pivot table, on the pivot's own connection.
     */
    public function newPivotStatement()
    {
        if (! $this->crossesConnections()) {
            return parent::newPivotStatement();
        }

        return $this->parent->getConnection()->table($this->table);
    }

    public function addConstraints(): void
    {
        if (! $this->crossesConnections()) {
            parent::addConstraints();

            return;
        }

        if (static::$constraints) {
            $this->query->whereIn(
                $this->getQualifiedRelatedKeyName(),
                $this->relatedKeysForParents([$this->parent->{$this->parentKey}]),
            );
        }
    }

    /**
     * @param  array<int, Model>  $models
     */
    public function addEagerConstraints(array $models): void
    {
        if (! $this->crossesConnections()) {
            parent::addEagerConstraints($models);

            return;
        }

        $this->pivotRowCache = null;

        $this->query->whereIn(
            $this->getQualifiedRelatedKeyName(),
            $this->relatedKeysForParents($this->getKeys($models, $this->parentKey)),
        );
    }

    /**
     * @param  array<int, Model>  $models
     * @return array<int, Model>
     */
    public function match(array $models, Collection $results, $relation): array
    {
        if (! $this->crossesConnections()) {
            return parent::match($models, $results, $relation);
        }

        $byRelatedKey = $results->keyBy(fn (Model $model) => (string) $model->{$this->relatedKey});
        $pivotRows = $this->pivotRowsFor($this->getKeys($models, $this->parentKey));

        foreach ($models as $model) {
            $parentKey = (string) $model->{$this->parentKey};
            $matched = [];

            foreach ($pivotRows[$parentKey] ?? [] as $row) {
                $related = $byRelatedKey->get((string) $row->{$this->relatedPivotKey});

                if ($related === null) {
                    // A pivot row pointing at a user the host has since
                    // deleted. There is no cross-connection foreign key to
                    // cascade it away, so skip it rather than hydrating a hole.
                    continue;
                }

                $matched[] = $this->hydrateCrossConnectionPivot(clone $related, $row);
            }

            $model->setRelation($relation, $this->related->newCollection($matched));
        }

        return $models;
    }

    /**
     * The related rows for an eager load.
     *
     * Deliberately NOT routed through get(): during an eager load `$this->parent`
     * is only the first of the models being loaded, so filtering to it there
     * would hand match() one parent's rows and leave every other model empty.
     * The eager constraints already narrowed this to the right set of users;
     * match() does the per-parent grouping from the pivot.
     */
    public function getEager(): Collection
    {
        if (! $this->crossesConnections()) {
            return parent::getEager();
        }

        return $this->query->get();
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function get($columns = ['*']): Collection
    {
        if (! $this->crossesConnections()) {
            return parent::get($columns);
        }

        $parentKey = $this->parent->{$this->parentKey};
        $rows = $this->pivotRowsFor([$parentKey])[(string) $parentKey] ?? [];

        $models = $this->query->get($columns)->keyBy(fn (Model $model) => (string) $model->{$this->relatedKey});

        $matched = [];

        foreach ($rows as $row) {
            $related = $models->get((string) $row->{$this->relatedPivotKey});

            if ($related !== null) {
                $matched[] = $this->hydrateCrossConnectionPivot(clone $related, $row);
            }
        }

        return $this->related->newCollection($matched);
    }

    /**
     * Count through the pivot table alone.
     *
     * `withCount('agents')` normally compiles a correlated subquery that joins
     * the related table, which cannot span connections. Counting pivot rows
     * answers the same question — and does so without touching the users table
     * at all, which is cheaper on a single connection too.
     */
    public function getRelationExistenceCountQuery(Builder $query, Builder $parentQuery): Builder
    {
        if (! $this->crossesConnections()) {
            return parent::getRelationExistenceCountQuery($query, $parentQuery);
        }

        return $this->pivotExistenceQuery($query, new Expression('count(*)'));
    }

    /**
     * @param  array<int, string>|mixed  $columns
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        if (! $this->crossesConnections()) {
            return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        }

        return $this->pivotExistenceQuery($query, $columns);
    }

    /**
     * Rewrite an existence/count subquery to run against the pivot table alone.
     *
     * The subquery is correlated into the parent's own statement, and the
     * parent and the pivot share a connection, so this compiles to valid SQL
     * where a join to the related table could not. Any global scopes the
     * related model had applied to `$query` go with the discarded inner query
     * on purpose -- we are counting pivot rows, not users.
     *
     * @param  array<int, string>|mixed  $columns
     */
    protected function pivotExistenceQuery(Builder $query, mixed $columns): Builder
    {
        $pivot = $this->parent->newQuery()->getQuery()->newQuery()
            ->from($this->table)
            ->select($columns)
            ->whereColumn(
                $this->getQualifiedForeignPivotKeyName(),
                '=',
                $this->getQualifiedParentKeyName(),
            );

        return $query->setQuery($pivot);
    }

    /**
     * Copy the pivot columns onto a hydrated related model, so `->pivot` reads
     * the same as it would after a join.
     */
    protected function hydrateCrossConnectionPivot(Model $related, object $row): Model
    {
        $related->setRelation(
            $this->accessor,
            $this->newExistingPivot((array) $row),
        );

        return $related;
    }

    /**
     * Pivot rows for the given parent keys, grouped by parent key.
     *
     * @param  array<int, mixed>  $parentKeys
     * @return array<string, array<int, object>>
     */
    protected function pivotRowsFor(array $parentKeys): array
    {
        if ($this->pivotRowCache !== null) {
            return $this->pivotRowCache;
        }

        $rows = $this->newPivotStatement()
            ->whereIn($this->foreignPivotKey, $parentKeys)
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(string) $row->{$this->foreignPivotKey}][] = $row;
        }

        return $this->pivotRowCache = $grouped;
    }

    /**
     * The related (user) keys referenced by the pivot for these parents.
     *
     * @param  array<int, mixed>  $parentKeys
     * @return array<int, mixed>
     */
    protected function relatedKeysForParents(array $parentKeys): array
    {
        $keys = [];

        foreach ($this->pivotRowsFor($parentKeys) as $rows) {
            foreach ($rows as $row) {
                $keys[] = $row->{$this->relatedPivotKey};
            }
        }

        return array_values(array_unique($keys));
    }
}

<?php

namespace Escalated\Laravel\Concerns;

use Escalated\Laravel\Database\Eloquent\ConnectionPropagation;
use Escalated\Laravel\Database\Eloquent\HostUserBelongsToMany as EscalatedBelongsToMany;
use Escalated\Laravel\Database\Eloquent\MorphTo as EscalatedMorphTo;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Resolve this model against Escalated's configured database connection.
 *
 * Every Escalated model used to resolve the host application's default
 * connection with no way to change it, which made the package unusable in any
 * host that partitions its database — a schema shared with a legacy system, a
 * multi-tenant split, a separate reporting store, or simply a host that would
 * rather keep support tables out of its application database.
 *
 * `escalated.connection` names the connection; null keeps the default, so a
 * host that never sets it is unaffected.
 *
 * An explicitly assigned connection still wins. `$model->setConnection(...)`,
 * a `$connection` property on a subclass, and Eloquent's own propagation of
 * the parent's connection through relations and `newInstance()`/`hydrate()`
 * all set `$this->connection`, and all of them continue to work — this only
 * supplies the default for a model that has not been told otherwise.
 */
trait UsesEscalatedConnection
{
    public function getConnectionName(): ?string
    {
        return $this->connection ?? Escalated::connection();
    }

    /**
     * Build a related model without forcing Escalated's connection onto it.
     *
     * Eloquent assigns the parent's connection to any related instance that
     * has not already got one. That is the right default when an application's
     * models all live together, and exactly wrong across this boundary: a
     * ticket's assignee, its requester and its subjects are the HOST's models,
     * and with Escalated on its own connection they were being looked up in
     * Escalated's database — `no such table: users`, because the host's users
     * table was never over there.
     *
     * Escalated's own models resolve the package connection through this same
     * trait, so propagation is kept only for them. That preserves the useful
     * case: `$ticket->setConnection('archive')` still carries across
     * Escalated's own graph.
     *
     * @param  class-string<Model>  $class
     * @return Model
     */
    protected function newRelatedInstance($class)
    {
        return tap(new $class, function ($instance) {
            if ($instance->getConnectionName() === null && ConnectionPropagation::belongsToEscalated($instance)) {
                $instance->setConnection($this->getConnectionName());
            }
        });
    }

    /**
     * Use Escalated's MorphTo, which applies the same rule when it resolves a
     * polymorphic target by type. See {@see EscalatedMorphTo}.
     *
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $type
     * @param  string  $relation
     */
    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation): MorphTo
    {
        return new EscalatedMorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }

    /**
     * Use a cross-connection-capable many-to-many when the far side is one of
     * the host's models.
     *
     * Escalated has four such relations — department agents, role members,
     * skill agents and ticket followers — where an Escalated pivot table joins
     * to the host's users table. Eloquent resolves those with a single JOIN,
     * and no database can join across two connections, so once Escalated's
     * tables move the join names a pivot the host's database has never heard
     * of. {@see EscalatedBelongsToMany} resolves them in two steps instead,
     * and is a straight pass-through when both sides share a connection.
     *
     * Pivots between two Escalated models (role/permission, ticket/tag) are
     * left entirely alone: both sides move together, so the join is always
     * valid.
     *
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ): BelongsToMany {
        if (! ConnectionPropagation::belongsToEscalated($query->getModel())) {
            return new EscalatedBelongsToMany(
                $query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName
            );
        }

        return parent::newBelongsToMany(
            $query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName
        );
    }
}

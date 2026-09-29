<?php

namespace Escalated\Laravel\Database\Eloquent;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Tenancy\GuardsHostRelation;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo as BaseMorphTo;

/**
 * A `morphTo` that does not force Escalated's connection onto the model it
 * resolves.
 *
 * Eloquent's own `createModelByType()` assigns the relation's connection to any
 * related instance that has not already got one. That is the right default for
 * an application whose models all live together, and exactly wrong here: a
 * ticket's requester, a reply's author and a ticket subject are HOST models.
 * With Escalated on its own connection they were being looked up in Escalated's
 * database, which produced `no such table: users` — the host's users table was
 * never over there.
 *
 * Escalated's own models resolve the package connection through
 * {@see UsesEscalatedConnection}, so they need no
 * help; a host model is left to resolve whatever connection it already uses.
 */
class MorphTo extends BaseMorphTo
{
    use GuardsHostRelation;

    public function getResults()
    {
        $this->assertTenantParent();

        return $this->visibleHostResult(parent::getResults());
    }

    protected function getResultsByType($type)
    {
        $class = Model::getActualClassNameForMorph($type);
        $previous = $this->morphableConstraints[$class] ?? null;
        if (! ConnectionPropagation::belongsToEscalated(new $class)) {
            $this->morphableConstraints[$class] = function ($query) use ($previous) {
                if ($previous) {
                    $previous($query);
                }
                $query->withGlobalScope('escalated-host-tenant', fn ($builder) => app(TenantContext::class)->scopeHost($builder));
            };
        }
        try {
            return parent::getResultsByType($type)->filter(fn ($model) => $this->visibleHostResult($model) !== null)->values();
        } finally {
            if ($previous) {
                $this->morphableConstraints[$class] = $previous;
            } else {
                unset($this->morphableConstraints[$class]);
            }
        }
    }

    /**
     * @param  string  $type
     * @return Model
     */
    public function createModelByType($type)
    {
        $class = Model::getActualClassNameForMorph($type);

        $instance = new $class;

        // Only propagate onto models that are ours, and only when they have
        // not resolved a connection of their own. This keeps an explicitly
        // reconnected parent (`$ticket->setConnection(...)`) propagating across
        // Escalated's own graph, without reaching into the host's.
        if ($instance->getConnectionName() === null && ConnectionPropagation::belongsToEscalated($instance)) {
            $instance->setConnection($this->getConnection()->getName());
        }

        return $instance;
    }
}

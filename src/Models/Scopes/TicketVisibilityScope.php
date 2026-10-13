<?php

namespace Escalated\Laravel\Models\Scopes;

use Escalated\Laravel\Support\TicketVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TicketVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // The request resolver also carries the API-token actor. Do not capture
        // an actor at model boot: workers and long-lived servers reuse models.
        TicketVisibility::apply($builder, TicketVisibility::actor());
    }
}

<?php

namespace Escalated\Laravel\Models\Scopes;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\TicketVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TicketChildVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TicketVisibility::restricted(TicketVisibility::actor())) {
            $builder->whereIn($model->qualifyColumn('ticket_id'), Ticket::query()->select((new Ticket)->qualifyColumn('id')));
        }
    }
}

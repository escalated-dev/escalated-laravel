<?php

namespace Escalated\Laravel\Contracts;

use Illuminate\Database\Eloquent\Model;

interface TicketAccountResolver
{
    /** Resolve the account of a requester, ticket subject, or agent; null means none. */
    public function resolve(Model $model): ?Model;
}

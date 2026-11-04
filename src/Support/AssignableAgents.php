<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;

/**
 * The staff a ticket can be assigned to: agents and admins who can see it.
 *
 * Uses the host's `escalated.authorization.agent_scope` when one is set,
 * otherwise the first 500 users filtered through the staff gates, as the
 * staff API's agent list does.
 */
final class AssignableAgents
{
    /** @return list<array{id: int|string, name: string}> */
    public static function for(Ticket $ticket): array
    {
        $query = Escalated::userQuery();
        $scope = config('escalated.authorization.agent_scope');

        $users = $scope && is_callable($scope) ? $scope($query)->get() : $query->limit(500)->get();

        if (! ($scope && is_callable($scope)) || app(TenantContext::class)->enabled()) {
            $users = $users->filter(fn ($user) => StaffAccess::isStaff($user));
        }

        return $users
            ->filter(fn ($user) => TicketVisibility::agentCanAccess($user->getKey(), $ticket))
            ->map(fn ($user) => ['id' => $user->getKey(), 'name' => (string) $user->name])
            ->values()
            ->all();
    }
}

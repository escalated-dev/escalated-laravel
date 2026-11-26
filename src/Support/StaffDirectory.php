<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lists the host's staff (agents and admins) without assuming anything about
 * the users table beyond its key and `email`.
 *
 * Uses `escalated.authorization.agent_scope` when the host sets one, otherwise
 * checks the staff gates (StaffAccess) against at most `$limit` users, as the
 * assignee pickers do. Hosts with many users should set `agent_scope`.
 */
final class StaffDirectory
{
    public const DEFAULT_LIMIT = 500;

    /**
     * @param  Builder|null  $query  a user query (Escalated::userQuery()) the caller has narrowed, e.g. by a search
     */
    public static function staff(?Builder $query = null, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $query ??= Escalated::userQuery();
        $scope = config('escalated.authorization.agent_scope');

        if ($scope && is_callable($scope)) {
            $users = $scope($query)->get();

            // A host scope is global; tenant mode still needs a tenant-local seat.
            return app(TenantContext::class)->enabled()
                ? $users->filter(fn ($user) => StaffAccess::isStaff($user))->values()
                : $users->values();
        }

        return $query->limit($limit)->get()
            ->filter(fn ($user) => StaffAccess::isStaff($user))
            ->values();
    }

    /** @return array{id: int|string, name: string|null, email: string|null} */
    public static function row(mixed $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => Escalated::userDisplayName($user),
            'email' => $user->getAttribute('email'),
        ];
    }
}

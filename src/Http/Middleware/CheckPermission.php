<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Support\AccessFeatures;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if (self::allows($user->getKey(), $permission)) {
            return $next($request);
        }

        abort(403, 'You do not have the required permission: '.$permission);
    }

    /**
     * Whether the user may do what the permission covers. While roles &
     * permissions is switched off the host gates decide everything, so this
     * is always true; callers have already checked the gate.
     */
    public static function allows(int|string $userId, string $permissionSlug): bool
    {
        return ! AccessFeatures::rolesPermissions() || self::userHasPermission($userId, $permissionSlug);
    }

    public static function userHasPermission(int|string $userId, string $permissionSlug): bool
    {
        return Escalated::query(Escalated::table('role_user'))
            ->join(Escalated::table('role_permission'), Escalated::table('role_user').'.role_id', '=', Escalated::table('role_permission').'.role_id')
            ->join(Escalated::table('permissions'), Escalated::table('role_permission').'.permission_id', '=', Escalated::table('permissions').'.id')
            ->where(Escalated::table('role_user').'.user_id', $userId)
            ->where(Escalated::table('permissions').'.slug', $permissionSlug)
            ->exists();
    }

    /**
     * All permission slugs granted to the user (via their roles).
     * Returns [] if the permission tables are not present yet.
     *
     * @return list<string>
     */
    public static function userPermissions(int|string $userId): array
    {
        try {
            return Escalated::query(Escalated::table('role_user'))
                ->join(Escalated::table('role_permission'), Escalated::table('role_user').'.role_id', '=', Escalated::table('role_permission').'.role_id')
                ->join(Escalated::table('permissions'), Escalated::table('role_permission').'.permission_id', '=', Escalated::table('permissions').'.id')
                ->where(Escalated::table('role_user').'.user_id', $userId)
                ->pluck(Escalated::table('permissions').'.slug')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}

<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Escalated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if ($this->userHasPermission($user->id, $permission)) {
            return $next($request);
        }

        abort(403, 'You do not have the required permission: '.$permission);
    }

    protected function userHasPermission(int $userId, string $permissionSlug): bool
    {
        return DB::table(Escalated::table('role_user'))
            ->join(Escalated::table('role_permission'), Escalated::table('role_user').'.role_id', '=', Escalated::table('role_permission').'.role_id')
            ->join(Escalated::table('permissions'), Escalated::table('role_permission').'.permission_id', '=', Escalated::table('permissions').'.id')
            ->where(Escalated::table('role_user').'.user_id', $userId)
            ->where(Escalated::table('permissions').'.slug', $permissionSlug)
            ->exists();
    }
}

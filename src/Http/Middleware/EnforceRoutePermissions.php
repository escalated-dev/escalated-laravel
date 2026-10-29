<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\AccessFeatures;
use Escalated\Laravel\Support\RoutePermissions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the route's permission (RoutePermissions) from the user's roles
 * while roles & permissions is switched on. Does nothing while it is off.
 *
 * Runs after the admin/agent gate or API token middleware, so the gates still
 * decide who reaches the area at all; this adds the permission on top. A route
 * missing from the map is refused rather than let through.
 */
class EnforceRoutePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AccessFeatures::rolesPermissions()) {
            return $next($request);
        }

        $user = $request->user();
        $name = $request->route()?->getName();

        if (! $user || ! is_string($name)) {
            abort(403, 'Unauthorized.');
        }

        $required = RoutePermissions::requiredFor($name, $request);

        if ($required === null) {
            abort(403, 'This route has no permission mapping.');
        }

        $granted = CheckPermission::userPermissions($user->getKey());

        foreach ($required as $slug) {
            if (! in_array($slug, $granted, true)) {
                abort(403, 'You do not have the required permission: '.$slug);
            }
        }

        return $next($request);
    }
}

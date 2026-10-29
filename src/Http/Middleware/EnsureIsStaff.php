<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Agents and admins: for screens about the staff member's own account. */
class EnsureIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! StaffAccess::isStaff($request->user())) {
            abort(403, __('escalated::messages.middleware.not_agent'));
        }

        return $next($request);
    }
}

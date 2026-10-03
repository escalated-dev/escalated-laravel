<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! StaffAccess::isAdmin($request->user())) {
            abort(403, __('escalated::messages.middleware.not_admin'));
        }

        return $next($request);
    }
}

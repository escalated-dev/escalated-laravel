<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! StaffAccess::isAgent($request->user())) {
            abort(403, __('escalated::messages.middleware.not_agent'));
        }

        return $next($request);
    }
}

<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('escalated.authorization.admin_gate', 'escalated-admin');

        if (! Gate::check($gate)) {
            abort(403, __('escalated::messages.middleware.not_admin'));
        }

        return $next($request);
    }
}

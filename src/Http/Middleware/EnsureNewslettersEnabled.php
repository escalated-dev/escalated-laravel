<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNewslettersEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(! config('escalated.enable_newsletters', false), 404);

        return $next($request);
    }
}

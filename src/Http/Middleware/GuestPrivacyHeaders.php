<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GuestPrivacyHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}

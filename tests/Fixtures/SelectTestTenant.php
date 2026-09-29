<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;

class SelectTestTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->hasSession() && $request->session()->has('current_account')) {
            $request->attributes->set('trusted_tenant', $request->session()->get('current_account'));
        }

        return $next($request);
    }
}

<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stand-ins for a host application's own admin middleware in the embedding
 * tests (ADR 2026-11-05-embeddable-admin).
 *
 * - `handle` marks the response, proving the host's middleware ran.
 * - `owner` is a host admin gate: only owner@host.test gets through.
 * - `frame` is a host-wide X-Frame-Options header, as many apps send.
 * - `csp` is a host-wide Content-Security-Policy of its own.
 */
class EmbedHostMiddleware
{
    public const OWNER_EMAIL = 'owner@host.test';

    public function handle(Request $request, Closure $next, string $mode = 'mark'): Response
    {
        if ($mode === 'owner' && $request->user()?->email !== self::OWNER_EMAIL) {
            abort(403, 'Host admins only.');
        }

        $response = $next($request);

        if ($mode === 'frame') {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        } elseif ($mode === 'csp') {
            $response->headers->set('Content-Security-Policy', "script-src 'self'");
        } else {
            $response->headers->set('X-Host-Admin', '1');
        }

        return $response;
    }
}

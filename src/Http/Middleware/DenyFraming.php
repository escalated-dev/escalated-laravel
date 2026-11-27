<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forbids framing the browser extension connect page (staff API contract v1,
 * "Connect page requirements": every response from the page, the error page
 * included, carries both headers).
 */
class DenyFraming
{
    public const CSP = "frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request));
    }

    /**
     * Laravel's middleware priority runs the session and auth middleware
     * before route middleware, so the sign-in redirect for a signed-out user
     * (and anything else answered that early) never passes through handle().
     * Give every answer from a connect page route the headers here too.
     */
    public static function decorateEarlyResponse(RequestHandled $event): void
    {
        $name = $event->request->route()?->getName();

        if (is_string($name) && str_starts_with($name, 'escalated.agent.extension.')) {
            self::apply($event->response);
        }
    }

    protected static function apply(Response $response): Response
    {
        // Added as its own policy, so a host CSP already on the response
        // (script-src and the like) still applies alongside it.
        if (! in_array(self::CSP, $response->headers->all('content-security-policy'), true)) {
            $response->headers->set('Content-Security-Policy', self::CSP, false);
        }
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }
}

<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\WidgetOrigins;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

/**
 * Stands in for the host's CSRF middleware on the widget routes. A request
 * WidgetCors accepted from an allowed embedding site carries no session
 * cookie and runs as a guest, so it has no CSRF token to send and none to
 * protect. Every other request goes through the host's own CSRF middleware,
 * with its except list, exactly as before.
 */
class WidgetCsrf
{
    /**
     * Base classes of the framework's CSRF middleware: VerifyCsrfToken (and
     * its ValidateCsrfToken subclass) up to Laravel 12, PreventRequestForgery
     * from Laravel 13. The widget routes exclude both.
     */
    public const CSRF_MIDDLEWARE = [
        'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        VerifyCsrfToken::class,
    ];

    public function __construct(protected Router $router) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->attributes->has(WidgetOrigins::ATTRIBUTE)) {
            return $next($request);
        }

        $csrf = $this->hostCsrfMiddleware();

        return $csrf === null ? $next($request) : app($csrf)->handle($request, $next);
    }

    /** The CSRF middleware the host registered in its web group, if any. */
    protected function hostCsrfMiddleware(): ?string
    {
        foreach ($this->router->getMiddlewareGroups()['web'] ?? [] as $middleware) {
            foreach (static::CSRF_MIDDLEWARE as $csrf) {
                if (is_string($middleware) && is_a($middleware, $csrf, true)) {
                    return $middleware;
                }
            }
        }

        return null;
    }
}

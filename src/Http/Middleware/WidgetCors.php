<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\WidgetOrigins;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the widget routes. Only origins listed in
 * escalated.widget.allowed_origins or the widget_allowed_origins setting
 * get CORS headers; such requests are marked so WidgetCsrf lets them
 * through and always run as a guest. Everything else passes untouched.
 */
class WidgetCors
{
    protected const SEEN = 'escalated.widget.cors_checked';

    public function handle(Request $request, Closure $next): mixed
    {
        $request->attributes->set(self::SEEN, true);
        $origin = WidgetOrigins::crossOrigin($request);
        $allowed = $origin !== null && WidgetOrigins::allows($origin);

        if ($request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method')) {
            $response = response()->noContent();
            $response->headers->set('Vary', 'Origin');
            if ($allowed) {
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
                $response->headers->set('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type, X-Requested-With');
                $response->headers->set('Access-Control-Max-Age', (string) (int) config('escalated.widget.preflight_max_age', 600));

                return $this->withCorsHeaders($response, $origin);
            }

            return $response;
        }

        if (! $allowed) {
            return $next($request);
        }

        // A cross-site embed is always anonymous: never act as whoever is
        // signed in to Escalated in the same browser.
        $request->attributes->set(WidgetOrigins::ATTRIBUTE, $origin);
        $request->setUserResolver(fn () => null);

        return $this->withCorsHeaders($next($request), $origin);
    }

    /**
     * Laravel's middleware priority runs the rate limiter (and the host's
     * tenancy middleware) before this middleware, so a 429 never passes
     * through handle(). Give such answers the same CORS grant so the embed
     * can read them. Before a tenant is resolved only the config list is
     * consulted, never a tenant's settings.
     */
    public static function decorateEarlyResponse(RequestHandled $event): void
    {
        $request = $event->request;
        $name = $request->route()?->getName();
        if ($request->attributes->has(self::SEEN) || ! is_string($name)
            || ! str_starts_with($name, 'escalated.widget.') || $name === 'escalated.widget.script') {
            return;
        }

        $origin = WidgetOrigins::crossOrigin($request);
        if ($origin === null) {
            return;
        }

        $tenancy = app(TenantContext::class);
        $allowed = $tenancy->enabled() && $tenancy->current() === null
            ? WidgetOrigins::allowsConfigured($origin)
            : WidgetOrigins::allows($origin);
        if ($allowed) {
            (new static)->withCorsHeaders($event->response, $origin);
        }
    }

    protected function withCorsHeaders(mixed $response, string $origin): mixed
    {
        if ($response instanceof Response) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Expose-Headers', 'Retry-After');
            $vary = array_filter(array_map('trim', explode(',', (string) $response->headers->get('Vary'))));
            if (! in_array('Origin', $vary, true)) {
                $vary[] = 'Origin';
            }
            $response->headers->set('Vary', implode(', ', $vary));
        }

        return $response;
    }
}

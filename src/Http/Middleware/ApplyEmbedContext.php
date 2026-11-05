<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\EmbedConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on the admin routes while they are embedded in the host's admin panel
 * (ADR 2026-11-05-embeddable-admin), and on no other route. It marks the
 * request as embedded (shared as `escalated.embed.enabled`) and switches the
 * Inertia root view when `escalated.ui.embed.root_view` is set. The iframe
 * framing headers are set once the whole response is built
 * (EmbedConfig::decorateResponse), after any host middleware.
 */
class ApplyEmbedContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(EmbedConfig::REQUEST_ATTRIBUTE, true);

        if (($view = EmbedConfig::rootView()) !== null && class_exists(Inertia::class)) {
            Inertia::setRootView($view);
        }

        return $next($request);
    }
}

<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            return $next($request);
        }

        $context->set(null);
        try {
            if ($request->route()?->getName() === 'escalated.attachments.download'
                && ($guard = config('escalated.storage.download_guard'))) {
                $request->setUserResolver(fn () => Auth::guard($guard)->user());
            }
            $requiresToken = collect($request->route()?->gatherMiddleware() ?? [])
                ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, AuthenticateApiToken::class));
            $bearer = $request->bearerToken();
            $tokenTenant = null;
            if ($requiresToken || ($bearer && $request->route()?->getName() === 'escalated.attachments.download')) {
                abort_unless(is_string($bearer) && $bearer !== '', 401);
                // The only unscoped token read: an opaque credential hash, never
                // a user-supplied tenant ID or an ordinary token-list query.
                $row = Escalated::db()->table(Escalated::table('api_tokens'))
                    ->where('token', hash('sha256', $bearer))->first();
                abort_unless($row, 401);
                $token = (new ApiToken)->newFromBuilder((array) $row);
                abort_if($token->isExpired(), 401);
                $context->set($token->tenant_id);
                $tokenTenant = $context->id();
                $owner = $token->tokenable;
                abort_unless($owner instanceof Model && $context->canAccess($owner), 403);
                $request->setUserResolver(fn () => $owner);
            }

            $hostTenant = $context->resolver()->resolve($request);
            if ($tokenTenant !== null && $hostTenant !== null) {
                abort_unless((string) $hostTenant === $tokenTenant, 403);
            }
            $context->set($hostTenant ?? $tokenTenant);
            $context->id(); // Missing host routing/context always fails closed.
            if ($request->user() !== null) {
                abort_unless($request->user() instanceof Model && $context->canAccess($request->user()), 403);
            }

            return $next($request);
        } finally {
            $context->set(null);
        }
    }
}

<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Contracts\Bus\Dispatcher;

/** Explicit adapter for work deferred past the HTTP tenant middleware lifetime. */
class TenantDispatch
{
    public function afterResponse($command, $handler = null): void
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            app(Dispatcher::class)->dispatchAfterResponse($command, $handler);

            return;
        }
        $tenant = $context->id();
        app()->terminating(fn () => app(TenantContext::class)->run(
            $tenant, fn () => app(Dispatcher::class)->dispatchSync($command, $handler),
        ));
    }
}

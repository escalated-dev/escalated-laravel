<?php

namespace Escalated\Laravel\Tenancy;

class TenantDeferredQueue extends TenantSyncQueue
{
    public function push($job, $data = '', $queue = null)
    {
        $context = app(TenantContext::class);
        $tenant = $context->current();

        return \Illuminate\Support\defer(function () use ($tenant, $job, $data, $queue) {
            $context = app(TenantContext::class);
            $previous = $context->current();
            try {
                $context->set($tenant);

                return parent::push($job, $data, $queue);
            } finally {
                $context->set($previous);
            }
        });
    }
}

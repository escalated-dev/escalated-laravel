<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\SyncJob;

/** Queue metadata is trusted infrastructure, established before model restoration. */
class TenantQueueContext
{
    private array $frames = [];

    public function payload(): array
    {
        $context = app(TenantContext::class);

        return $context->enabled() ? ['escalated_tenant' => $context->current()] : [];
    }

    public function begin(Job $job): void
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            return;
        }
        $previous = $job instanceof SyncJob || $this->frames !== [] ? $context->current() : null;
        $this->frames[] = ['job' => $job, 'previous' => $previous];
        // A host job without metadata must not inherit a preceding merchant.
        $context->set(null);
        $tenant = $job->payload()['escalated_tenant'] ?? null;
        $context->set($tenant);
    }

    public function finish(Job $job): void
    {
        $frame = end($this->frames);
        if ($frame !== false && $frame['job'] === $job) {
            array_pop($this->frames);
            app(TenantContext::class)->set($frame['previous']);
        }
    }

    public function depth(): int
    {
        return count($this->frames);
    }

    public function unwind(int $depth, ?string $previous): void
    {
        $this->frames = array_slice($this->frames, 0, $depth);
        app(TenantContext::class)->set($previous);
    }

    public function clear(): void
    {
        $this->frames = [];
        app(TenantContext::class)->set(null);
    }
}

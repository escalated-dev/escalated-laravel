<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SyncQueue;

class TenantSyncQueue extends SyncQueue
{
    public function push($job, $data = '', $queue = null)
    {
        $context = app(TenantContext::class);
        if (! $context->enabled() || ! $this->shouldDispatchAfterCommit($job) || ! $this->container->bound('db.transactions')) {
            return parent::push($job, $data, $queue);
        }

        // A host transaction can commit after the tenant HTTP middleware has
        // unwound. Capture at dispatch, not when the deferred job finally runs.
        $tenant = $context->current();
        $transactions = $this->container->make('db.transactions');
        if (method_exists($this, 'registerRollbackCallbacksForJobsThatDispatchAfterCommit')) {
            $this->registerRollbackCallbacksForJobsThatDispatchAfterCommit($job);
        } elseif ($job instanceof ShouldBeUnique && method_exists($transactions, 'addCallbackForRollback')) {
            $transactions->addCallbackForRollback(function () use ($job) {
                (new UniqueLock($this->container->make(Repository::class)))->release($job);
            });
        }

        return $transactions->addCallback(function () use ($tenant, $job, $data, $queue) {
            $context = app(TenantContext::class);
            $previous = $context->current();
            try {
                $context->set($tenant);

                return $this->executeJob($job, $data, $queue);
            } finally {
                $context->set($previous);
            }
        });
    }

    protected function executeJob($job, $data = '', $queue = null)
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            return parent::executeJob($job, $data, $queue);
        }
        $state = app(TenantQueueContext::class);
        $depth = $state->depth();
        $previous = $context->current();
        try {
            return parent::executeJob($job, $data, $queue);
        } finally {
            // Laravel 11's sync driver has no JobAttempted event. Keep the
            // tenant through failed() callbacks, then restore even if a queue
            // event listener itself threw before the usual cleanup events.
            $state->unwind($depth, $previous);
        }
    }
}

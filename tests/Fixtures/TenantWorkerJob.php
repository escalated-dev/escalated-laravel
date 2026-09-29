<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Illuminate\Queue\Jobs\Job;

/** In-memory transport; execution still uses the real Worker and CallQueuedHandler. */
class TenantWorkerJob extends Job implements \Illuminate\Contracts\Queue\Job
{
    public function __construct(private string $body)
    {
        $this->container = app();
        $this->connectionName = 'test-worker';
        $this->queue = 'test';
    }

    public function getRawBody()
    {
        return $this->body;
    }

    public function getJobId()
    {
        return 'test-worker-job';
    }

    public function attempts()
    {
        return 1;
    }
}

<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Queue\BackgroundQueue;
use Illuminate\Queue\Connectors\ConnectorInterface;
use LogicException;

class TenantBackgroundConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        return new class($config['after_commit'] ?? false) extends BackgroundQueue
        {
            public function push($job, $data = '', $queue = null)
            {
                $context = app(TenantContext::class);
                if ($context->enabled() && $context->current() !== null) {
                    throw new LogicException('Use a sync, deferred or durable queue for tenant work; background process propagation is not supported.');
                }

                return parent::push($job, $data, $queue);
            }
        };
    }
}

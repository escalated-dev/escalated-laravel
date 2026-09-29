<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Queue\Connectors\ConnectorInterface;

class TenantSyncConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        return new TenantSyncQueue($config['after_commit'] ?? false);
    }
}

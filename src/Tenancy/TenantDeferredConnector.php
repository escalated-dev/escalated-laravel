<?php

namespace Escalated\Laravel\Tenancy;

use Illuminate\Queue\Connectors\ConnectorInterface;

class TenantDeferredConnector implements ConnectorInterface
{
    public function connect(array $config)
    {
        return new TenantDeferredQueue($config['after_commit'] ?? false);
    }
}

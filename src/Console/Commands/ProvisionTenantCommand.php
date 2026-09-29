<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Tenancy\TenantProvisioner;
use Illuminate\Console\Command;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'escalated:tenant-provision {tenant}';

    protected $description = 'Create default Escalated settings and system roles for a host tenant';

    public function handle(TenantProvisioner $provisioner): int
    {
        $provisioner->provision($this->argument('tenant'));
        $this->info('Tenant defaults provisioned. Membership remains managed by the host.');

        return self::SUCCESS;
    }
}

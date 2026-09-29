<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Contracts\TenantCatalog;
use LogicException;

class UnconfiguredTenantCatalog implements TenantCatalog
{
    public function tenantIds(): iterable
    {
        throw new LogicException('Configure escalated.tenancy.catalog before running scheduled tenant commands.');
    }
}

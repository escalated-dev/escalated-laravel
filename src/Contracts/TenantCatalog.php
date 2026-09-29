<?php

namespace Escalated\Laravel\Contracts;

/** Trusted host inventory for scheduled work; never inferred from ticket data. */
interface TenantCatalog
{
    /** @return iterable<int|string> */
    public function tenantIds(): iterable;
}

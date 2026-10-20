<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events\DepartmentChanged;
use Escalated\Laravel\Services\SlaService;
use Escalated\Laravel\Support\ImportContext;

/**
 * Targeted SLAs are chosen by department, so a ticket that changes
 * department is given the policy the new department would have assigned.
 * Untargeted selection does not depend on the department and is left alone.
 */
class ReselectSlaPolicy
{
    public function __construct(protected SlaService $slaService) {}

    public function handle(DepartmentChanged $event): void
    {
        if (ImportContext::isImporting()
            || ! config('escalated.sla.enabled')
            || ! config('escalated.sla.targeted', false)) {
            return;
        }

        $this->slaService->reselectPolicy($event->ticket);
    }
}

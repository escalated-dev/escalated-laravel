<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Services\SlaService;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    protected $signature = 'escalated:check-sla';

    protected $description = 'Check for SLA breaches and warnings';

    public function handle(SlaService $slaService): int
    {
        if (! config('escalated.sla.enabled')) {
            $this->info('SLA checking is disabled.');

            return self::SUCCESS;
        }

        $breaches = $slaService->checkBreaches();
        $warnings = $slaService->checkWarnings();

        $this->info("SLA check complete: {$breaches} breaches, {$warnings} warnings.");

        return self::SUCCESS;
    }
}

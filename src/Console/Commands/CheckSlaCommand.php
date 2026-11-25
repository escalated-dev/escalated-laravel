<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Services\SlaService;
use Escalated\Laravel\Support\RecurringCommands;
use Illuminate\Console\Command;

class CheckSlaCommand extends Command
{
    protected $signature = 'escalated:check-sla';

    protected $description = 'Check for SLA breaches and warnings';

    public function handle(SlaService $slaService): int
    {
        // Admins are warned when this stops arriving: it shows the recurring
        // commands are scheduled, whether or not SLAs are on.
        RecurringCommands::heartbeat();

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

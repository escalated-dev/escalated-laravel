<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Tenancy\LegacyTenantAssignment;
use Illuminate\Console\Command;

class AssignLegacyTenantCommand extends Command
{
    protected $signature = 'escalated:tenant-backfill {tenant} {--apply : Persist the checked assignment} {--writers-paused : Confirm HTTP, queue workers and scheduler are paused}';

    protected $description = 'Validate and assign all legacy single-install data to one host tenant (dry run by default)';

    public function handle(LegacyTenantAssignment $assignment): int
    {
        if ($this->option('apply') && ! $this->option('writers-paused')) {
            $this->error('Pause HTTP writes, queue workers and the scheduler, then pass --writers-paused.');

            return self::FAILURE;
        }
        $counts = $assignment->assign($this->argument('tenant'), (bool) $this->option('apply'));
        $this->table(['Table', 'Rows'], collect($counts)->map(fn ($count, $table) => [$table, $count])->values()->all());
        $this->info($this->option('apply') ? 'Legacy assignment committed.' : 'Dry run complete; all changes rolled back.');

        return self::SUCCESS;
    }
}

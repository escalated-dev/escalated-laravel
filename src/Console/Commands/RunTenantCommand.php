<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Contracts\TenantCatalog;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

class RunTenantCommand extends Command
{
    protected $signature = 'escalated:tenant-run {task : Escalated maintenance command} {--tenant=* : Run only these catalog tenants} {--arguments= : JSON object of command arguments and options}';

    protected $description = 'Run an Escalated maintenance command separately for each host tenant';

    private const COMMANDS = [
        'escalated:check-sla', 'escalated:evaluate-escalations', 'escalated:run-automations',
        'escalated:process-delayed-actions', 'escalated:wake-snoozed-tickets',
        'escalated:close-resolved', 'escalated:purge-activities', 'escalated:purge-expired',
        'escalated:close-idle-chats', 'escalated:cleanup-abandoned-chats',
        'escalated:newsletters:dispatch', 'escalated:poll-imap',
        'escalated:attachments:privatize', 'escalated:import',
        'escalated:slack:process',
    ];

    public function handle(): int
    {
        $context = app(TenantContext::class);
        $command = $this->argument('task');
        if (! $context->enabled() || ! in_array($command, self::COMMANDS, true)) {
            $this->error('Enable tenancy and choose a supported Escalated maintenance command.');

            return self::FAILURE;
        }
        try {
            $arguments = json_decode($this->option('arguments') ?? '{}', flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $arguments = null;
        }
        if (! $arguments instanceof \stdClass) {
            $this->error('--arguments must be a JSON object.');

            return self::FAILURE;
        }
        $arguments = (array) $arguments;

        $selected = array_map(strval(...), $this->option('tenant'));
        $found = [];
        $failed = false;
        foreach (app(TenantCatalog::class)->tenantIds() as $tenant) {
            $tenant = (string) $tenant;
            if (isset($found[$tenant]) || ($selected !== [] && ! in_array($tenant, $selected, true))) {
                continue;
            }
            $found[$tenant] = true;
            try {
                $status = $context->run($tenant, fn () => $this->call($command, $arguments));
                $failed = $status !== self::SUCCESS || $failed;
            } catch (Throwable $exception) {
                $this->error("Tenant {$tenant}: ".$exception->getMessage());
                $failed = true;
            }
        }
        if (array_diff($selected, array_keys($found)) !== []) {
            $this->error('A selected tenant was not present in the host catalog.');
            $failed = true;
        }
        $this->info('Processed '.count($found).' tenants.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

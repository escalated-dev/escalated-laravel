<?php

namespace Escalated\Laravel\Console\Commands;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Runs the SDK plugins' cron schedules that are due this minute
 * (domain-model/plugin-contract.md, Cron). Schedule it every minute; with
 * escalated.scheduling.auto_register on, the package does.
 */
class RunPluginCronCommand extends Command
{
    protected $signature = 'escalated:plugins:cron
        {--at= : The minute to run, as a time such as 2026-10-29T09:00:00Z (default: now)}';

    protected $description = 'Run the due cron schedules of SDK plugins';

    public function handle(): int
    {
        try {
            $at = new DateTimeImmutable($this->option('at') ?: 'now');
        } catch (\Exception) {
            $this->error('--at must be a time, such as 2026-10-29T09:00:00Z.');

            return self::FAILURE;
        }

        if (! config('escalated.plugins.enabled', true)
            || ! config('escalated.plugins.sdk_enabled', true)
            || app(TenantContext::class)->enabled()) {
            $this->info('Plugin cron is off.');

            return self::SUCCESS;
        }

        $bridge = app(PluginBridge::class);
        if (! $bridge->isBooted()) {
            $this->info('The plugin runtime is not running.');

            return self::SUCCESS;
        }

        // Each schedule runs as no user, whoever this process signed in.
        Auth::forgetUser();

        $minute = $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:00');
        $ran = 0;

        foreach ($bridge->getManifests() as $name => $manifest) {
            foreach ((array) ($manifest['cronSchedules'] ?? []) as $schedule) {
                if (! is_string($schedule) || ! CronExpression::isValidExpression($schedule)) {
                    Log::warning('Escalated plugins: '.$name.' cron "'.(is_string($schedule) ? $schedule : json_encode($schedule)).'" skipped: not a five-field cron expression');

                    continue;
                }

                if (! (new CronExpression($schedule))->isDue($minute, 'UTC')) {
                    continue;
                }

                try {
                    $bridge->cron((string) $name, $schedule);
                    $ran++;
                } catch (\Throwable $e) {
                    Log::warning('Escalated plugins: '.$name.' cron "'.$schedule.'" failed: '.$e->getMessage());
                }
            }
        }

        $this->info("Ran {$ran} plugin cron schedule(s).");

        return self::SUCCESS;
    }
}

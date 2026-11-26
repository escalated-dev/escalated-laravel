<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The package's recurring commands, and whether they are actually running.
 *
 * SLA checks, escalation rules, automations and snooze wake-ups happen only
 * when these commands run on a schedule. escalated.scheduling.auto_register
 * adds them to the Laravel scheduler; otherwise the host lists them itself.
 * escalated:check-sla records a heartbeat each time it runs, and admins are
 * warned when it is missing or older than STALE_AFTER_MINUTES.
 */
final class RecurringCommands
{
    public const HEARTBEAT_KEY = 'escalated.scheduler.heartbeat';

    public const STALE_AFTER_MINUTES = 15;

    /**
     * Every recurring command for the features that are on, in schedule
     * order. `frequency` is the Laravel scheduler method; `interval` is the
     * same thing for people.
     *
     * @return list<array{command: string, frequency: string, interval: string, without_overlapping: bool, tenant_scoped: bool}>
     */
    public static function all(): array
    {
        $commands = [
            ['escalated:check-sla', 'everyMinute'],
            ['escalated:evaluate-escalations', 'everyFiveMinutes'],
            ['escalated:run-automations', 'everyFiveMinutes'],
            ['escalated:process-delayed-actions', 'everyMinute'],
            ['escalated:wake-snoozed-tickets', 'everyMinute'],
            ['escalated:close-resolved', 'daily'],
            ['escalated:purge-activities', 'weekly'],
        ];

        $tenancy = app(TenantContext::class)->enabled();

        // SDK plugins' cron schedules. No plugin code runs in tenant mode.
        if (config('escalated.plugins.enabled', true) && config('escalated.plugins.sdk_enabled', true) && ! $tenancy) {
            $commands[] = ['escalated:plugins:cron', 'everyMinute', false, false];
        }

        if (config('escalated.slack.enabled', false)) {
            $commands[] = ['escalated:slack:process', 'everyMinute', true];
        }

        if (config('escalated.chat.enabled', false)) {
            $commands[] = ['escalated:close-idle-chats', 'everyMinute'];
            $commands[] = ['escalated:cleanup-abandoned-chats', 'everyMinute'];
        }

        if (config('escalated.enable_newsletters', false)) {
            $commands[] = ['escalated:newsletters:dispatch', 'everyMinute', true];
        }

        if (self::pollsImap($tenancy)) {
            $commands[] = ['escalated:poll-imap', 'everyMinute'];
        }

        $intervals = [
            'everyMinute' => 'every minute',
            'everyFiveMinutes' => 'every five minutes',
            'daily' => 'daily',
            'weekly' => 'weekly',
        ];

        return array_map(fn (array $entry) => [
            'command' => $entry[0],
            'frequency' => $entry[1],
            'interval' => $intervals[$entry[1]],
            'without_overlapping' => $entry[2] ?? false,
            'tenant_scoped' => $entry[3] ?? true,
        ], $commands);
    }

    /**
     * Whether to poll IMAP. The config switch (ESCALATED_INBOUND_EMAIL)
     * enables inbound email; the admin Settings page can then switch it off
     * and choose the adapter, so those settings decide, falling back to
     * config. Tenant settings are per account, so in tenant mode the config
     * adapter decides and escalated:poll-imap checks each account's settings.
     */
    private static function pollsImap(bool $tenancy): bool
    {
        if (! config('escalated.inbound_email.enabled', false)) {
            return false;
        }

        $adapter = config('escalated.inbound_email.adapter');
        if ($tenancy) {
            return $adapter === 'imap';
        }

        try {
            return EscalatedSettings::getBool('inbound_email_enabled', true)
                && EscalatedSettings::get('inbound_email_adapter', $adapter) === 'imap';
        } catch (\Throwable) {
            // No settings table yet (fresh install, migrations pending).
            return $adapter === 'imap';
        }
    }

    /**
     * The command line to schedule: tenant-scoped commands run once per
     * tenant through escalated:tenant-run while tenancy is on.
     */
    public static function scheduled(array $entry): string
    {
        return $entry['tenant_scoped'] && app(TenantContext::class)->enabled()
            ? 'escalated:tenant-run '.$entry['command']
            : $entry['command'];
    }

    /**
     * Record that the recurring commands ran. Called by escalated:check-sla.
     */
    public static function heartbeat(): void
    {
        try {
            Cache::forever(self::heartbeatKey(), now()->toIso8601String());
        } catch (\Throwable) {
            // A cache outage must not stop the SLA check itself.
        }
    }

    /**
     * When escalated:check-sla last ran, or null if it has not been seen.
     * Throws when the cache cannot be read.
     */
    public static function lastRunAt(): ?Carbon
    {
        $value = Cache::get(self::heartbeatKey());

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    /**
     * The admin warning's data: the `scheduler` page prop shared with the
     * frontend. Null when the heartbeat cannot be read, so no warning is
     * shown on a guess.
     *
     * @return array{healthy: bool, last_run_at: string|null, message: string, commands: list<array{command: string, interval: string}>}|null
     */
    public static function status(): ?array
    {
        try {
            $lastRun = self::lastRunAt();
        } catch (\Throwable) {
            return null;
        }

        $healthy = $lastRun !== null && $lastRun->greaterThanOrEqualTo(now()->subMinutes(self::STALE_AFTER_MINUTES));

        return [
            'healthy' => $healthy,
            'last_run_at' => $lastRun?->toIso8601String(),
            'message' => $healthy ? '' : self::warning($lastRun),
            'commands' => array_map(fn (array $entry) => [
                'command' => 'php artisan '.self::scheduled($entry),
                'interval' => $entry['interval'],
            ], self::all()),
        ];
    }

    private static function warning(?Carbon $lastRun): string
    {
        $seen = $lastRun === null
            ? 'escalated:check-sla has never run'
            : 'escalated:check-sla last ran '.$lastRun->diffForHumans();

        $fix = config('escalated.scheduling.auto_register', false)
            ? 'Escalated adds the commands below to the Laravel scheduler (scheduling.auto_register is on), so the server needs a cron entry that runs `php artisan schedule:run` every minute.'
            : 'Set ESCALATED_SCHEDULE_AUTO_REGISTER=true and add a cron entry that runs `php artisan schedule:run` every minute, or schedule each command below yourself.';

        return "Escalated's recurring jobs are not running ({$seen}): SLA checks, escalation rules, automations and snooze wake-ups are not happening. {$fix}";
    }

    private static function heartbeatKey(): string
    {
        return app(TenantContext::class)->cacheKey(self::HEARTBEAT_KEY);
    }
}

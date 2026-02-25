<?php

namespace Escalated\Laravel\Console\Commands;

use Carbon\Carbon;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredDataCommand extends Command
{
    protected $signature = 'escalated:purge-expired
                            {--dry-run : Show what would be purged without actually deleting}
                            {--force : Force permanent delete instead of soft-delete}';

    protected $description = 'Purge expired data based on configured retention policies';

    /**
     * Map retention setting values to days.
     */
    protected array $retentionDaysMap = [
        'never' => null,
        '90_days' => 90,
        '180_days' => 180,
        '365_days' => 365,
        '1_year' => 365,
        '2_years' => 730,
        '3_years' => 1095,
        '5_years' => 1825,
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        if ($isDryRun) {
            $this->info('DRY RUN — no data will be deleted.');
        }

        $this->purgeClosedTickets($isDryRun, $isForce);
        $this->purgeAttachments($isDryRun, $isForce);
        $this->purgeAuditLogs($isDryRun, $isForce);
        $this->permanentDeleteGraceExpired($isDryRun);

        $this->info('Purge complete.');

        return self::SUCCESS;
    }

    protected function purgeClosedTickets(bool $dryRun, bool $force): void
    {
        $setting = EscalatedSettings::get('retention_closed_tickets', 'never');
        $days = $this->retentionDaysMap[$setting] ?? null;

        if ($days === null) {
            $this->line('Closed tickets: retention set to never, skipping.');

            return;
        }

        $cutoff = Carbon::now()->subDays($days);
        $table = Escalated::table('tickets');

        $query = DB::table($table)
            ->where('status', 'closed')
            ->where('closed_at', '<', $cutoff)
            ->whereNull('deleted_at');

        $count = $query->count();
        $this->line("Closed tickets: {$count} records older than {$days} days.");

        if (! $dryRun && $count > 0) {
            if ($force) {
                DB::table($table)
                    ->where('status', 'closed')
                    ->where('closed_at', '<', $cutoff)
                    ->whereNull('deleted_at')
                    ->delete();
                $this->info("Permanently deleted {$count} closed tickets.");
            } else {
                DB::table($table)
                    ->where('status', 'closed')
                    ->where('closed_at', '<', $cutoff)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now()]);
                $this->info("Soft-deleted {$count} closed tickets (30-day grace period).");
            }
        }
    }

    protected function purgeAttachments(bool $dryRun, bool $force): void
    {
        $setting = EscalatedSettings::get('retention_attachments', 'never');
        $days = $this->retentionDaysMap[$setting] ?? null;

        if ($days === null) {
            $this->line('Attachments: retention set to never, skipping.');

            return;
        }

        $cutoff = Carbon::now()->subDays($days);
        $table = Escalated::table('attachments');

        if (! Schema::hasTable($table)) {
            $this->line('Attachments table not found, skipping.');

            return;
        }

        $count = DB::table($table)
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->line("Attachments: {$count} records older than {$days} days.");

        if (! $dryRun && $count > 0) {
            DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->delete();
            $this->info("Deleted {$count} attachments.");
        }
    }

    protected function purgeAuditLogs(bool $dryRun, bool $force): void
    {
        $setting = EscalatedSettings::get('retention_audit_logs', 'never');
        $days = $this->retentionDaysMap[$setting] ?? null;

        if ($days === null) {
            $this->line('Audit logs: retention set to never, skipping.');

            return;
        }

        $cutoff = Carbon::now()->subDays($days);
        $table = Escalated::table('audit_logs');

        if (! Schema::hasTable($table)) {
            $this->line('Audit logs table not found, skipping.');

            return;
        }

        $count = DB::table($table)
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->line("Audit logs: {$count} records older than {$days} days.");

        if (! $dryRun && $count > 0) {
            DB::table($table)
                ->where('created_at', '<', $cutoff)
                ->delete();
            $this->info("Deleted {$count} audit log entries.");
        }
    }

    /**
     * Permanently delete soft-deleted records that have exceeded the 30-day grace period.
     */
    protected function permanentDeleteGraceExpired(bool $dryRun): void
    {
        $gracePeriod = Carbon::now()->subDays(30);
        $table = Escalated::table('tickets');

        $count = DB::table($table)
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $gracePeriod)
            ->count();

        if ($count === 0) {
            $this->line('No soft-deleted records past grace period.');

            return;
        }

        $this->line("Grace period expired: {$count} soft-deleted tickets for permanent removal.");

        if (! $dryRun) {
            DB::table($table)
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', $gracePeriod)
                ->delete();
            $this->info("Permanently removed {$count} tickets past 30-day grace period.");
        }
    }
}

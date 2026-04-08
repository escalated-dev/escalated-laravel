<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Models\ImportJob;
use Escalated\Laravel\Services\ImportService;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'escalated:import
        {platform? : Platform to import from (e.g., zendesk, freshdesk)}
        {--resume= : Resume a paused/failed import job by ID}
        {--list : List all import jobs}
        {--subdomain= : Source platform subdomain}
        {--domain= : Source platform domain}
        {--token= : API token}
        {--email= : Account email (for Zendesk)}
        {--app-id= : OAuth App ID (for Help Scout)}
        {--app-secret= : OAuth App Secret (for Help Scout)}
        {--mapping= : Path to JSON field mapping file}';

    protected $description = 'Import data from external helpdesk platforms';

    public function __construct(private ImportService $importService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listJobs();
        }

        if ($this->option('resume')) {
            return $this->resumeJob($this->option('resume'));
        }

        $platform = $this->argument('platform');

        if (! $platform) {
            $adapters = $this->importService->availableAdapters();

            if (empty($adapters)) {
                $this->components->error('No import adapters installed.');

                return 1;
            }

            $platform = $this->choice(
                'Which platform would you like to import from?',
                array_map(fn ($a) => $a->name(), $adapters),
            );
        }

        $adapter = $this->importService->resolveAdapter($platform);

        if (! $adapter) {
            $this->components->error("No adapter found for platform '{$platform}'.");

            return 1;
        }

        // Collect credentials
        $credentials = $this->collectCredentials($adapter);

        // Test connection
        $this->components->task('Testing connection', function () use ($adapter, $credentials) {
            return $adapter->testConnection($credentials);
        });

        // Load field mappings
        $mappings = [];
        if ($this->option('mapping')) {
            $path = $this->option('mapping');
            if (! file_exists($path)) {
                $this->components->error("Mapping file not found: {$path}");

                return 1;
            }
            $mappings = json_decode(file_get_contents($path), true);
        }

        // Create job
        $job = ImportJob::create([
            'user_id' => 0, // CLI user
            'platform' => $platform,
            'status' => 'mapping',
            'credentials' => $credentials,
            'field_mappings' => $mappings,
        ]);

        $this->info("Import job created: {$job->id}");

        return $this->runImport($job);
    }

    private function collectCredentials($adapter): array
    {
        $credentials = [];

        foreach ($adapter->credentialFields() as $field) {
            $optionName = str_replace('_', '-', $field['name']);
            $value = $this->option($optionName);

            if (! $value) {
                $method = ($field['type'] ?? 'text') === 'password' ? 'secret' : 'ask';
                $value = $this->{$method}($field['label'].($field['help'] ? " ({$field['help']})" : ''));
            }

            $credentials[$field['name']] = $value;
        }

        return $credentials;
    }

    private function runImport(ImportJob $job): int
    {
        $this->newLine();
        $this->info('Starting import...');
        $this->newLine();

        try {
            $this->importService->run($job, function (string $entityType, array $progress) {
                $processed = $progress['processed'] ?? 0;
                $total = $progress['total'] ?? '?';
                $skipped = $progress['skipped'] ?? 0;
                $failed = $progress['failed'] ?? 0;

                $pct = is_numeric($total) && $total > 0
                    ? round(($processed / $total) * 100, 1)
                    : '?';

                $this->line(
                    "[{$entityType}] {$processed} / {$total} ({$pct}%) | {$skipped} skipped | {$failed} failed"
                );
            });

            $this->newLine();
            $this->components->info('Import completed successfully.');

            return 0;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->error("Import failed: {$e->getMessage()}");
            $this->info("Job ID: {$job->id} — use --resume={$job->id} to retry.");

            return 1;
        }
    }

    private function resumeJob(string $jobId): int
    {
        $job = ImportJob::find($jobId);

        if (! $job) {
            $this->components->error("Import job not found: {$jobId}");

            return 1;
        }

        if (! $job->isResumable()) {
            $this->components->error("Job is not resumable (status: {$job->status}).");

            return 1;
        }

        // Transition to a state that run() can pick up from
        if ($job->status === 'failed') {
            $job->transitionTo('mapping'); // failed -> mapping allowed
        }
        // paused -> importing is handled by run()

        return $this->runImport($job);
    }

    private function listJobs(): int
    {
        $jobs = ImportJob::orderByDesc('created_at')->limit(20)->get();

        if ($jobs->isEmpty()) {
            $this->info('No import jobs found.');

            return 0;
        }

        $this->table(
            ['ID', 'Platform', 'Status', 'Created', 'Completed'],
            $jobs->map(fn ($j) => [
                $j->id,
                $j->platform,
                $j->status,
                $j->created_at?->diffForHumans(),
                $j->completed_at?->diffForHumans() ?? '-',
            ]),
        );

        return 0;
    }
}

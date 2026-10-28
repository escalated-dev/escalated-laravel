<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Queue\Console\RetryCommand;
use Illuminate\Queue\Events\JobRetryRequested;

class RetryTenantJobsCommand extends RetryCommand
{
    protected $signature = 'escalated:tenant-retry {id?* : Failed job IDs or all} {--queue=} {--range=*}';

    protected $description = 'Retry failed jobs with tenant context established before model deserialization';

    public function handle(): int
    {
        $failed = false;
        foreach ($this->getJobIds() as $id) {
            $context = app(TenantContext::class);
            $previous = $context->current();
            try {
                $job = $this->laravel['queue.failer']->find($id);
                if (! $job) {
                    throw new \RuntimeException('Failed job not found: '.$id);
                }
                $context->set(json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR)['escalated_tenant'] ?? null);
                $this->laravel['events']->dispatch(new JobRetryRequested($job));
                $this->retryJob($job);
                $this->laravel['queue.failer']->forget($id);
                $this->info('Retried '.$id.'.');
            } catch (\Throwable $error) {
                $failed = true;
                $this->error($error->getMessage());
            } finally {
                $context->set($previous);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function retryJob($job)
    {
        $context = app(TenantContext::class);
        $previous = $context->current();
        try {
            $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
            $context->set($payload['escalated_tenant'] ?? null);
            // Restore the job's models under its own tenant before it goes
            // back on the queue. A payload whose tenant does not own its
            // models fails here with ModelNotFoundException. Laravel 13's
            // RetryCommand swallows that exception and re-queues the job, so
            // the check cannot be left to it.
            $this->restoreCommand($payload);

            return parent::retryJob($job);
        } finally {
            $context->set($previous);
        }
    }

    protected function restoreCommand(array $payload): void
    {
        $command = $payload['data']['command'] ?? null;
        if (! is_string($command)) {
            return;
        }
        if (! str_starts_with($command, 'O:')) {
            $command = $this->laravel->make(Encrypter::class)->decrypt($command);
        }
        unserialize($command);
    }
}

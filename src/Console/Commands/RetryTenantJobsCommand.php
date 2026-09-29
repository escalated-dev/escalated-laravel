<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Tenancy\TenantContext;
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
            $context->set(json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR)['escalated_tenant'] ?? null);

            return parent::retryJob($job);
        } finally {
            $context->set($previous);
        }
    }
}

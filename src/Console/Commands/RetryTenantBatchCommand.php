<?php

namespace Escalated\Laravel\Console\Commands;

use Illuminate\Bus\BatchRepository;
use Illuminate\Queue\Console\RetryBatchCommand;

class RetryTenantBatchCommand extends RetryBatchCommand
{
    protected $signature = 'escalated:tenant-retry-batch {id?* : Failed batch IDs}';

    public function handle(): int
    {
        $failed = false;
        foreach ($this->getBatchJobIds() as $id) {
            $batch = $this->laravel[BatchRepository::class]->find($id);
            if (! $batch) {
                $this->error('Batch not found: '.$id);
                $failed = true;

                continue;
            }
            if ($batch->failedJobIds !== []) {
                $status = $this->call('escalated:tenant-retry', ['id' => $batch->failedJobIds]);
                $failed = $status !== self::SUCCESS || $failed;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function getBatchJobIds()
    {
        // Laravel 11.23 predates the native multi-ID helper.
        return array_values(array_filter(array_unique((array) $this->argument('id'))));
    }

    public function isolatableId()
    {
        return implode(',', $this->getBatchJobIds());
    }
}

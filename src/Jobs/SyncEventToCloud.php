<?php

namespace Escalated\Laravel\Jobs;

use Escalated\Laravel\Http\Client\HostedApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one Synced-mode event to cloud.escalated.dev.
 *
 * The SyncedDriver dispatches this after every local write instead of
 * calling the cloud inline, so a slow or unreachable cloud never blocks a
 * ticket operation. The event id is minted once at dispatch and travels
 * with every retry, so the cloud can drop duplicates.
 */
class SyncEventToCloud implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 30, 120, 600];

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $event,
        public array $payload,
        public string $eventId,
        public string $timestamp,
    ) {
        $queue = config('escalated.hosted.queue');

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(HostedApiClient $client): void
    {
        $response = $client->emit($this->event, $this->payload, $this->eventId, $this->timestamp);

        $response?->throw();
    }

    public function failed(Throwable $exception): void
    {
        Log::warning("Escalated sync gave up on {$this->event} ({$this->eventId}): {$exception->getMessage()}");
    }
}

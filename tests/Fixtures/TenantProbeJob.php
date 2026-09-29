<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class TenantProbeJob implements ShouldQueue
{
    use SerializesModels;

    public static array $observations = [];

    public function __construct(public ?Ticket $ticket = null, public bool $nested = false, public bool $fail = false) {}

    public function handle(): void
    {
        $context = app(TenantContext::class);
        self::$observations[] = ['handle', $context->current(), $this->ticket?->id];
        if ($this->nested) {
            $context->run('b', fn () => Queue::connection('sync')->push(new self));
            self::$observations[] = ['after-nested', $context->current()];
        }
        if ($this->fail) {
            throw new RuntimeException('Probe failed');
        }
    }

    public function failed(): void
    {
        self::$observations[] = ['failed', app(TenantContext::class)->current(), $this->ticket?->id];
    }
}

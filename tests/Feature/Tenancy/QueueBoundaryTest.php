<?php

use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tests\Fixtures\TenantProbeJob;
use Escalated\Laravel\Tests\Fixtures\TenantWorkerJob;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Event::fake([Events\TicketCreated::class, Events\TicketUpdated::class]);
    config(['escalated.tenancy.enabled' => true]);
    $this->context = app(TenantContext::class);
    $this->ticket = $this->context->run('a', fn () => Ticket::factory()->create());
    TenantProbeJob::$observations = [];
});

it('captures context and restores models before a worker handles consecutive merchant jobs', function () {
    $payload = $this->context->run('a', fn () => tenantProbePayload(new TenantProbeJob($this->ticket)));
    expect(json_decode($payload, true)['escalated_tenant'])->toBe('a');
    $this->context->set('stale');
    app('queue.worker')->process('test-worker', new TenantWorkerJob($payload), new WorkerOptions);
    expect(TenantProbeJob::$observations)->toBe([['handle', 'a', $this->ticket->id]])
        ->and($this->context->current())->toBeNull();

    $hostPayload = tenantProbePayload(new TenantProbeJob);
    $this->context->set('stale');
    app('queue.worker')->process('test-worker', new TenantWorkerJob($hostPayload), new WorkerOptions);
    expect(TenantProbeJob::$observations[1])->toBe(['handle', null, null])
        ->and($this->context->current())->toBeNull();
});

it('denies restoration when tenant metadata is missing or disagrees with a serialized model', function (?string $tenant, string $exception) {
    $payload = $this->context->run('a', fn () => tenantProbePayload(new TenantProbeJob($this->ticket)));
    $data = json_decode($payload, true);
    $data['escalated_tenant'] = $tenant;
    $this->context->set('a');
    expect(fn () => app('queue.worker')->process('test-worker', new TenantWorkerJob(json_encode($data)), new WorkerOptions))
        ->toThrow($exception);
    expect(TenantProbeJob::$observations)->toBe([])->and($this->context->current())->toBeNull();
})->with([[null, AuthorizationException::class], ['b', ModelNotFoundException::class]]);

it('restores nested synchronous jobs and keeps the context through failure callbacks', function () {
    $this->context->run('a', function () {
        Queue::connection('sync')->push(new TenantProbeJob($this->ticket, nested: true));
        expect($this->context->current())->toBe('a');
        expect(fn () => Queue::connection('sync')->push(new TenantProbeJob($this->ticket, fail: true)))->toThrow(RuntimeException::class, 'Probe failed');
        expect($this->context->current())->toBe('a');
    });
    expect(TenantProbeJob::$observations)->toBe([
        ['handle', 'a', $this->ticket->id], ['handle', 'b', null], ['after-nested', 'a'],
        ['handle', 'a', $this->ticket->id], ['failed', 'a', $this->ticket->id],
    ])->and($this->context->current())->toBeNull();
});

it('cleans up even if a synchronous queue event listener throws', function () {
    Event::listen(JobProcessing::class, function () {
        throw new RuntimeException('Listener failed');
    });
    $this->context->run('a', function () {
        expect(fn () => Queue::connection('sync')->push(new TenantProbeJob))->toThrow(RuntimeException::class, 'Listener failed');
        expect($this->context->current())->toBe('a');
    });
    expect($this->context->current())->toBeNull();
});

function tenantProbePayload(TenantProbeJob $job): string
{
    $queue = Queue::connection('sync');

    return (new ReflectionMethod($queue, 'createPayload'))->invoke($queue, $job, 'test');
}

it('retains the dispatching tenant when a host transaction commits after request cleanup', function () {
    $transactions = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $transactions);
    $transactions->begin('deferred-fixture', 1);
    config(['queue.connections.tenant_deferred' => ['driver' => 'sync', 'after_commit' => true]]);
    $this->context->run('a', fn () => Queue::connection('tenant_deferred')->push(new TenantProbeJob($this->ticket)));
    expect(TenantProbeJob::$observations)->toBe([])->and($this->context->current())->toBeNull();
    $transactions->commit('deferred-fixture', 1, 0);
    expect(TenantProbeJob::$observations)->toBe([['handle', 'a', $this->ticket->id]])
        ->and($this->context->current())->toBeNull();
});

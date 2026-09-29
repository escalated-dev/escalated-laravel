<?php

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\TicketAssignedNotification;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tenancy\TenantDispatch;
use Escalated\Laravel\Tests\Fixtures\TenantProbeJob;
use Escalated\Laravel\Tests\Fixtures\TenantWorkerJob;
use Escalated\Laravel\Tests\Fixtures\TestTenantResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\BatchRepository;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\BackgroundQueue;
use Illuminate\Queue\DeferredQueue;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

function tenantProbePayload(object $job): string
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
it('captures deferred and explicit after-response dispatch before context cleanup', function () {
    if (class_exists(DeferredQueue::class)) {
        config(['queue.connections.deferred' => ['driver' => 'deferred']]);
        $this->context->run('a', fn () => Queue::connection('deferred')->push(new TenantProbeJob($this->ticket)));
        expect(TenantProbeJob::$observations)->toBe([]);
        app(DeferredCallbackCollection::class)->invoke();
        expect(TenantProbeJob::$observations)->toBe([['handle', 'a', $this->ticket->id]]);
    }
    TenantProbeJob::$observations = [];
    $this->context->run('a', fn () => app(TenantDispatch::class)->afterResponse(new TenantProbeJob($this->ticket)));
    expect(TenantProbeJob::$observations)->toBe([]);
    app()->terminate();
    expect(TenantProbeJob::$observations)->toBe([['handle', 'a', $this->ticket->id]])
        ->and($this->context->current())->toBeNull();
});

it('sets tenant context before Laravel hydrates queued log-context models', function () {
    $payload = $this->context->run('a', function () {
        Context::add('ticket', $this->ticket);

        return tenantProbePayload(new TenantProbeJob($this->ticket));
    });
    Context::flush();
    app('queue.worker')->process('test-worker', new TenantWorkerJob($payload), new WorkerOptions);
    expect(Context::get('ticket')->id)->toBe($this->ticket->id)
        ->and($this->context->current())->toBeNull();
});

it('rechecks membership before delivering a restored queued ticket notification', function () {
    $agent = $this->createAgent();
    $resolver = new TestTenantResolver;
    $resolver->members = ['a' => [$agent->id]];
    app()->instance(TenantResolver::class, $resolver);
    Gate::define('escalated-agent', fn ($user) => $user->is_agent);
    $job = new SendQueuedNotifications(
        $agent, new TicketAssignedNotification($this->ticket), [TenantNotificationProbeChannel::class],
    );
    $payload = $this->context->run('a', fn () => tenantProbePayload($job));
    TenantNotificationProbeChannel::$sent = [];
    app('queue.worker')->process('test-worker', new TenantWorkerJob($payload), new WorkerOptions);
    expect(TenantNotificationProbeChannel::$sent)->toBe([$agent->id]);
    $resolver->members['a'] = [];
    app('queue.worker')->process('test-worker', new TenantWorkerJob($payload), new WorkerOptions);
    expect(TenantNotificationProbeChannel::$sent)->toBe([$agent->id]);
});

class TenantNotificationProbeChannel
{
    public static array $sent = [];

    public function send($notifiable, $notification): void
    {
        self::$sent[] = $notifiable->getKey();
    }
}

it('restores each failed job tenant before retry deserialization and retains failed retries', function () {
    $ticketB = $this->context->run('b', fn () => Ticket::factory()->create());
    $records = [];
    foreach (['a' => $this->ticket, 'b' => $ticketB] as $tenant => $ticket) {
        $records[$tenant] = (object) [
            'id' => $tenant, 'connection' => 'retry-probe', 'queue' => 'tickets',
            'payload' => $this->context->run($tenant, fn () => tenantProbePayload(new TenantProbeJob($ticket))),
        ];
    }
    $invalid = json_decode($records['a']->payload, true);
    $invalid['escalated_tenant'] = 'b';
    $records['invalid'] = (object) ['id' => 'invalid', 'connection' => 'retry-probe', 'queue' => 'tickets', 'payload' => json_encode($invalid)];
    $failer = new class($records)
    {
        public function __construct(public array $records) {}

        public function find($id)
        {
            return $this->records[$id] ?? null;
        }

        public function forget($id)
        {
            unset($this->records[$id]);
        }
    };
    app()->instance('queue.failer', $failer);
    $transport = new class
    {
        public array $pushed = [];

        public function pushRaw($payload, $queue, $options)
        {
            $this->pushed[] = [app(TenantContext::class)->id(), json_decode($payload, true)['escalated_tenant']];
        }
    };
    Queue::shouldReceive('connection')->with('retry-probe')->andReturn($transport);
    $this->context->set('previous');
    $this->artisan('escalated:tenant-retry', ['id' => ['a', 'invalid', 'b']])->assertFailed();
    expect($transport->pushed)->toBe([['a', 'a'], ['b', 'b']])
        ->and(array_keys($failer->records))->toBe(['invalid'])
        ->and($this->context->current())->toBe('previous');
    $this->context->set(null);
});

it('returns a failure for a missing retry batch instead of dereferencing it', function () {
    $this->mock(BatchRepository::class)->shouldReceive('find')->with('missing')->andReturnNull();
    $this->artisan('escalated:tenant-retry-batch', ['id' => ['missing']])->assertFailed();
    expect($this->context->current())->toBeNull();
});

it('rejects tenant dispatch to an unsupported background process', function () {
    if (! class_exists(BackgroundQueue::class)) {
        $this->markTestSkipped('This framework does not provide the background queue.');
    }
    config(['queue.connections.background' => ['driver' => 'background']]);
    expect(fn () => $this->context->run('a', fn () => Queue::connection('background')->push(new TenantProbeJob($this->ticket))))
        ->toThrow(LogicException::class, 'background process propagation');
    expect($this->context->current())->toBeNull();
});

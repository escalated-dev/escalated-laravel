<?php

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantBroadcast;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tests\Fixtures\TestTenantResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function () {
    Event::fake();
    Gate::define('escalated-agent', fn ($user) => (bool) $user->is_agent);
    Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
    $this->agent = $this->createAgent();
    $this->requester = $this->createTestUser(['email' => 'requester@example.test']);
    $this->resolver = new TestTenantResolver;
    $this->resolver->selected = 'a';
    $this->resolver->members = ['a' => [$this->agent->id, $this->requester->id], 'b' => [$this->agent->id]];
    app()->instance(TenantResolver::class, $this->resolver);
    config(['escalated.tenancy.enabled' => true]);
    $this->context = app(TenantContext::class);
    $this->ticketA = $this->context->run('a', fn () => Ticket::factory()->create([
        'requester_type' => $this->requester->getMorphClass(), 'requester_id' => $this->requester->id,
    ]));
    $this->ticketB = $this->context->run('b', fn () => Ticket::factory()->create());
    // Real framework pattern matching/parameter extraction, with only the
    // remote transport's signature response replaced for this local test.
    $this->broadcaster = new class extends Broadcaster
    {
        public function auth($request)
        {
            return $this->verifyUserCanAccessChannel($request, preg_replace('/^(private|presence)-/', '', $request->channel_name));
        }

        public function validAuthenticationResponse($request, $result)
        {
            return $result;
        }

        public function broadcast(array $channels, $event, array $payload = []) {}
    };
    Broadcast::extend('tenant-test', fn () => $this->broadcaster);
    config(['broadcasting.connections.tenant-test' => ['driver' => 'tenant-test']]);
    Broadcast::setDefaultDriver('tenant-test');
    require __DIR__.'/../../../routes/channels.php';
});

function tenantChannelAuth($broadcaster, $user, string $channel): mixed
{
    $request = request();
    $request->merge(['channel_name' => $channel]);
    $request->setUserResolver(fn () => $user);

    return $broadcaster->auth($request);
}

it('authorizes the actual tenant ticket and rejects global or mismatched channels', function () {
    $prefix = 'private-escalated.tenants.'.hash('sha256', 'a');
    expect(tenantChannelAuth($this->broadcaster, $this->agent, $prefix.'.tickets.'.$this->ticketA->id))->toBeTrue();
    foreach (['private-escalated.tickets', $prefix.'.tickets.'.$this->ticketB->id,
        'private-escalated.tenants.'.hash('sha256', 'b').'.tickets', $prefix.'.tickets.invalid'] as $channel) {
        expect(fn () => tenantChannelAuth($this->broadcaster, $this->agent, $channel))->toThrow(AccessDeniedHttpException::class);
        expect($this->context->current())->toBeNull();
    }
    $this->resolver->members['a'] = [];
    expect(fn () => tenantChannelAuth($this->broadcaster, $this->agent, $prefix.'.tickets'))->toThrow(AccessDeniedHttpException::class);
});

it('requires trusted resolution even with stale ambient context and supports exact chat queue matching', function () {
    $prefix = 'private-escalated.tenants.'.hash('sha256', 'a');
    expect(tenantChannelAuth($this->broadcaster, $this->agent, $prefix.'.chat.queue'))->toBeTrue();
    $this->resolver->selected = null;
    $this->context->set('stale');
    expect(fn () => tenantChannelAuth($this->broadcaster, $this->agent, $prefix.'.tickets'))->toThrow(AccessDeniedHttpException::class);
    expect($this->context->current())->toBe('stale');
    $this->context->set(null);
});

it('allows requester private updates but limits presence and its member data to agents', function () {
    $channel = 'escalated.tenants.'.hash('sha256', 'a').'.tickets.'.$this->ticketA->id;
    expect(tenantChannelAuth($this->broadcaster, $this->requester, 'private-'.$channel))->toBeTrue();
    expect(fn () => tenantChannelAuth($this->broadcaster, $this->requester, 'presence-'.$channel))->toThrow(AccessDeniedHttpException::class);
    expect(tenantChannelAuth($this->broadcaster, $this->agent, 'presence-'.$channel))
        ->toBe(['id' => $this->agent->id, 'name' => $this->agent->name]);
});

it('namespaces event channels and refuses an event model from a different tenant', function () {
    $this->context->run('a', function () {
        expect((new Events\TicketCreated($this->ticketA))->broadcastOn()[0]->name)
            ->toBe('private-'.TenantBroadcast::prefix().'.tickets');
        expect(fn () => (new Events\TicketUpdated($this->ticketB))->broadcastOn())->toThrow(AuthorizationException::class);
        $chat = ChatSession::factory()->create(['ticket_id' => $this->ticketA->id, 'agent_id' => $this->agent->id]);
        expect((new Events\ChatStarted($chat))->broadcastWith())->not->toHaveKey('customer_session_id');
        expect((new Events\ChatStarted($chat))->broadcastOn()[1]->name)->toBe('private-'.TenantBroadcast::prefix().'.chat.queue');
    });
});

it('partitions cached presence and suppresses an internal note passed to the public reply event', function () {
    config(['escalated.broadcasting.enabled' => true]);
    $keyA = $this->context->run('a', fn () => $this->context->cacheKey('presence'));
    $keyB = $this->context->run('b', fn () => $this->context->cacheKey('presence'));
    expect($keyA)->not->toBe($keyB);
    $this->context->run('a', function () {
        $reply = $this->ticketA->replies()->create(['body' => 'Private note', 'is_internal_note' => true]);
        expect((new Events\ReplyCreated($reply))->broadcastWhen())->toBeFalse();
    });
});

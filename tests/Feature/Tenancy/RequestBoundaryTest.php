<?php

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Escalated\Laravel\Policies\TicketPolicy;
use Escalated\Laravel\Services\AttachmentService;
use Escalated\Laravel\Services\WorkflowEngine;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tests\Fixtures\TestTenantResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([Events\TicketCreated::class, Events\TicketUpdated::class]);
    Gate::define('escalated-agent', fn ($user) => (bool) $user->is_agent);
    Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
    $this->agentA = $this->createAgent();
    $this->agentB = $this->createAgent(['email' => 'b@example.test']);
    $this->resolver = new TestTenantResolver;
    $this->resolver->members = ['a' => [$this->agentA->id], 'b' => [$this->agentB->id]];
    app()->instance(TenantResolver::class, $this->resolver);
    config(['escalated.tenancy.enabled' => true]);
    $this->context = app(TenantContext::class);
    [$this->ticketA, $this->tokenA] = $this->context->run('a', fn () => [Ticket::factory()->create(), ApiToken::createToken($this->agentA, 'A', ['agent'])['plainTextToken']]);
    [$this->ticketB, $this->tokenB] = $this->context->run('b', fn () => [Ticket::factory()->create(), ApiToken::createToken($this->agentB, 'B', ['agent'])['plainTextToken']]);
});

it('bootstraps tenant-bound tokens and ignores submitted tenant selection', function () {
    $this->withToken($this->tokenA)->getJson(route('escalated.api.tickets.index', ['tenant_id' => 'b']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $this->ticketA->reference);
    expect($this->context->current())->toBeNull();
    $this->withToken($this->tokenB)->getJson(route('escalated.api.tickets.index'))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $this->ticketB->reference);
    expect($this->context->current())->toBeNull();
});

it('binds signed attachment downloads to the authenticated tenant as well as the ticket policy', function () {
    Storage::fake('local');
    $url = $this->context->run('a', fn () => app(AttachmentService::class)->store(
        $this->ticketA, UploadedFile::fake()->create('parcel.txt', 1),
    )->url);
    $this->withToken($this->tokenA)->get($url)->assertOk()->assertDownload('parcel.txt');
    expect($this->context->current())->toBeNull();
    $this->withToken($this->tokenB)->get($url)->assertNotFound();
    expect($this->context->current())->toBeNull();
});

it('authorizes presence HTTP bindings and stores presence under the tenant cache namespace', function () {
    $this->resolver->selected = 'a';
    $this->actingAs($this->agentA)->postJson(route('escalated.agent.tickets.presence', $this->ticketA->reference))->assertOk();
    $key = 'escalated.presence.'.$this->ticketA->id.'.'.$this->agentA->id;
    expect(Cache::has($key))->toBeFalse();
    $this->context->run('a', fn () => expect(Cache::get($this->context->cacheKey($key)))
        ->toBe(['id' => $this->agentA->id, 'name' => $this->agentA->name]));
    $this->postJson(route('escalated.agent.tickets.presence', $this->ticketB->reference))->assertNotFound();
    $this->postJson(route('escalated.agent.tickets.typing', $this->ticketB->reference))->assertNotFound();
});

it('rejects foreign ticket bindings and trusted host-token conflicts', function () {
    $this->withToken($this->tokenA)->getJson(route('escalated.api.tickets.show', $this->ticketB->reference))->assertNotFound();
    $this->resolver->selected = 'b';
    $this->withToken($this->tokenA)->getJson(route('escalated.api.tickets.index'))->assertForbidden();
    expect($this->context->current())->toBeNull();
});

it('rejects revoked membership and expired or absent credentials before data queries', function () {
    $this->getJson(route('escalated.api.tickets.index'))->assertUnauthorized();
    $expired = $this->context->run('a', fn () => ApiToken::createToken($this->agentA, 'Expired', ['agent'], now()->subMinute())['plainTextToken']);
    $this->withToken($expired)->getJson(route('escalated.api.tickets.index'))->assertUnauthorized();
    $this->resolver->members['a'] = [];
    $this->withToken($this->tokenA)->getJson(route('escalated.api.tickets.index'))->assertForbidden();
    expect($this->context->current())->toBeNull();
});

it('enforces membership and stored ticket ownership directly in policy', function () {
    $policy = new TicketPolicy;
    expect($policy->viewAny($this->agentA))->toBeFalse();
    $this->context->run('a', function () use ($policy) {
        expect($policy->viewAny($this->agentA))->toBeTrue()
            ->and($policy->viewAny($this->agentB))->toBeFalse()
            ->and($policy->view($this->agentA, $this->ticketA))->toBeTrue()
            ->and($policy->view($this->agentA, $this->ticketB))->toBeFalse()
            ->and($policy->update($this->agentA, $this->ticketB))->toBeFalse();
    });
});

it('limits host directories and OR searches without changing the host model globally', function () {
    $this->context->run('a', function () {
        expect(Escalated::userQuery()->whereKey($this->agentA->id)->orWhere('email', $this->agentB->email)->pluck('id')->all())
            ->toBe([$this->agentA->id]);
        expect(Escalated::findUser($this->agentB->id))->toBeNull();
        expect($this->agentA->newQuery()->count())->toBe(2);
    });
    $this->withToken($this->tokenA)->getJson(route('escalated.api.agents'))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->agentA->id);
});

it('denies foreign mobile login and registration without creating host identities', function () {
    $this->resolver->selected = 'a';
    $this->postJson(route('escalated.api.mobile.auth.login'), ['email' => $this->agentB->email, 'password' => 'password'])->assertUnprocessable();
    $this->postJson(route('escalated.api.mobile.auth.login'), ['email' => $this->agentA->email, 'password' => 'password'])->assertOk();
    $this->postJson(route('escalated.api.mobile.auth.register'), [
        'name' => 'New user', 'email' => 'new@example.test', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertForbidden();
    expect($this->agentA->newQuery()->count())->toBe(2);
});

it('assigns workflow candidates on separate host and tenant connections', function () {
    $this->context->run('a', function () {
        $workflow = Workflow::create(['name' => 'Routing', 'trigger_event' => 'ticket.created', 'conditions' => [], 'actions' => []]);
        app(WorkflowEngine::class)->executeAction($workflow, $this->ticketA, [
            'type' => 'assign_agent', 'value' => ['strategy' => 'least_busy'],
        ]);
        expect($this->ticketA->fresh()->assigned_to)->toBe($this->agentA->id);
    });
});

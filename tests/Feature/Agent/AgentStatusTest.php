<?php

/*
 * An agent sets their own Online / Away / Offline status through an agent
 * route (ADR 2026-11-05-agent-panel-data). It is the same field live-chat
 * routing reads, so the admin chat status route stays an alias for it.
 */

use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ChatAvailabilityService;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function agentStatusProps($test, $user): array
{
    $shared = $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.index'))
        ->assertOk()
        ->json('props.escalated');
    $test->flushHeaders();

    return $shared;
}

it('lets an agent set their own status with PUT or POST', function (string $method) {
    $agent = $this->createAgent();

    $this->actingAs($agent)
        ->json($method, route('escalated.agent.status'), ['status' => 'away'])
        ->assertOk()
        ->assertExactJson(['agent_status' => 'away']);

    expect(AgentProfile::where('user_id', $agent->id)->first()->chat_status)->toBe(ChatStatus::Away);
})->with(['PUT', 'POST']);

it('is the status live-chat routing reads', function () {
    $agent = $this->createAgent();

    $this->actingAs($agent)->putJson(route('escalated.agent.status'), ['status' => 'online'])->assertOk();
    expect(app(ChatAvailabilityService::class)->isAvailable())->toBeTrue();

    $this->actingAs($agent)->putJson(route('escalated.agent.status'), ['status' => 'offline'])->assertOk();
    expect(app(ChatAvailabilityService::class)->isAvailable())->toBeFalse();
});

it('lets a light agent set their status', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['agent_type' => 'light']);

    $this->actingAs($agent)->putJson(route('escalated.agent.status'), ['status' => 'away'])->assertOk();

    expect(AgentProfile::where('user_id', $agent->id)->first()->chat_status)->toBe(ChatStatus::Away);
});

it('rejects a status outside the allowed values', function () {
    $agent = $this->createAgent();

    $this->actingAs($agent)
        ->putJson(route('escalated.agent.status'), ['status' => 'busy'])
        ->assertUnprocessable();

    expect(AgentProfile::where('user_id', $agent->id)->exists())->toBeFalse();
});

it('is refused to a non-agent', function () {
    $customer = $this->createTestUser();

    $this->actingAs($customer)
        ->putJson(route('escalated.agent.status'), ['status' => 'online'])
        ->assertForbidden();
});

it('redirects back for an Inertia visit', function () {
    $agent = $this->createAgent();

    $this->actingAs($agent)
        ->from(route('escalated.agent.tickets.index'))
        ->put(route('escalated.agent.status'), ['status' => 'away'])
        ->assertRedirect(route('escalated.agent.tickets.index'));
});

it('shares the agent status and the allowed values with agents', function () {
    $agent = $this->createAgent();

    $shared = agentStatusProps($this, $agent);
    expect($shared['agent_status'])->toBe('offline')
        ->and($shared['agent_statuses'])->toBe(['online', 'away', 'offline']);

    $this->actingAs($agent)->putJson(route('escalated.agent.status'), ['status' => 'away'])->assertOk();

    expect(agentStatusProps($this, $agent)['agent_status'])->toBe('away');
});

it('shares no agent status with customers', function () {
    $customer = $this->createTestUser();
    $ticket = Ticket::factory()->create(['requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);

    $shared = $this->actingAs($customer)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.customer.tickets.index'))
        ->assertOk()
        ->json('props.escalated');

    expect($shared)->not->toHaveKey('agent_status')
        ->and($shared)->not->toHaveKey('agent_statuses');
});

it('keeps the admin chat status route as an alias for the same field', function () {
    $admin = $this->createAdmin(['is_agent' => true]);

    $this->actingAs($admin)
        ->postJson(route('escalated.admin.chat.status'), ['status' => 'away'])
        ->assertOk()
        ->assertJsonPath('chat_status', 'away')
        ->assertJsonPath('agent_status', 'away');

    expect(agentStatusProps($this, $admin)['agent_status'])->toBe('away');
});

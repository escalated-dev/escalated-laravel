<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Enums\RoutingStrategy;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ChatRoutingRule;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ChatRoutingService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([ChatStarted::class, ChatAssigned::class]);
});

it('finds an available agent when agents are online', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    $service = app(ChatRoutingService::class);
    $found = $service->findAvailableAgent();

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($agent->id);
});

it('returns null when no agents are online', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Offline]);

    $service = app(ChatRoutingService::class);
    $found = $service->findAvailableAgent();

    expect($found)->toBeNull();
});

it('returns null when all agents are at capacity', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    ChatRoutingRule::create([
        'routing_strategy' => RoutingStrategy::RoundRobin,
        'offline_behavior' => 'ticket_fallback',
        'max_concurrent_per_agent' => 1,
        'is_active' => true,
    ]);

    // Create an active session for this agent
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    ChatSession::factory()->active($agent->id)->create(['ticket_id' => $ticket->id]);

    $service = app(ChatRoutingService::class);
    $found = $service->findAvailableAgent();

    expect($found)->toBeNull();
});

it('evaluates routing and assigns agent to session', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->waiting()->create(['ticket_id' => $ticket->id]);

    $service = app(ChatRoutingService::class);
    $service->evaluateRouting($session);

    $session->refresh();
    expect($session->agent_id)->toBe($agent->id);
    expect($session->status)->toBe(ChatSessionStatus::Active);
});

it('does not assign when strategy is manual queue', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    ChatRoutingRule::create([
        'routing_strategy' => RoutingStrategy::ManualQueue,
        'offline_behavior' => 'queue',
        'is_active' => true,
    ]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->waiting()->create(['ticket_id' => $ticket->id]);

    $service = app(ChatRoutingService::class);
    $service->evaluateRouting($session);

    $session->refresh();
    expect($session->agent_id)->toBeNull();
    expect($session->status)->toBe(ChatSessionStatus::Waiting);
});

it('gets correct queue position', function () {
    $ticket1 = Ticket::factory()->create(['status' => TicketStatus::Live]);
    $ticket2 = Ticket::factory()->create(['status' => TicketStatus::Live]);

    $session1 = ChatSession::factory()->waiting()->create([
        'ticket_id' => $ticket1->id,
        'started_at' => now()->subMinutes(5),
    ]);
    $session2 = ChatSession::factory()->waiting()->create([
        'ticket_id' => $ticket2->id,
        'started_at' => now(),
    ]);

    $service = app(ChatRoutingService::class);
    expect($service->getQueuePosition($session1))->toBe(1);
    expect($service->getQueuePosition($session2))->toBe(2);
});

it('gets routing rule for department', function () {
    $globalRule = ChatRoutingRule::create([
        'routing_strategy' => 'round_robin',
        'offline_behavior' => 'ticket_fallback',
        'is_active' => true,
        'position' => 0,
    ]);

    $service = app(ChatRoutingService::class);
    $rule = $service->getRoutingRule(null);

    expect($rule)->not->toBeNull();
    expect($rule->id)->toBe($globalRule->id);
});

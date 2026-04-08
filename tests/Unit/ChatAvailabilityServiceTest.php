<?php

use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ChatRoutingRule;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ChatAvailabilityService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([ChatStarted::class]);
});

it('returns true when agents are online and under capacity', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    $service = app(ChatAvailabilityService::class);
    expect($service->isAvailable())->toBeTrue();
});

it('returns false when no agents are online', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Offline]);

    $service = app(ChatAvailabilityService::class);
    expect($service->isAvailable())->toBeFalse();
});

it('returns false when all agents are at capacity', function () {
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    ChatRoutingRule::create([
        'routing_strategy' => 'round_robin',
        'offline_behavior' => 'ticket_fallback',
        'max_concurrent_per_agent' => 1,
        'is_active' => true,
    ]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    ChatSession::factory()->active($agent->id)->create(['ticket_id' => $ticket->id]);

    $service = app(ChatAvailabilityService::class);
    expect($service->isAvailable())->toBeFalse();
});

it('gets online agents', function () {
    $agent1 = $this->createAgent(['email' => 'agent1@example.com']);
    $agent2 = $this->createAgent(['email' => 'agent2@example.com']);

    AgentProfile::forUser($agent1->id)->update(['chat_status' => ChatStatus::Online]);
    AgentProfile::forUser($agent2->id)->update(['chat_status' => ChatStatus::Offline]);

    $service = app(ChatAvailabilityService::class);
    $online = $service->getOnlineAgents();

    expect($online)->toHaveCount(1);
    expect($online->first()->user_id)->toBe($agent1->id);
});

it('gets correct agent chat count', function () {
    $agent = $this->createAgent();

    $ticket1 = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $ticket2 = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    ChatSession::factory()->active($agent->id)->create(['ticket_id' => $ticket1->id]);
    ChatSession::factory()->active($agent->id)->create(['ticket_id' => $ticket2->id]);

    // This one is ended, should not count
    $ticket3 = Ticket::factory()->create(['status' => TicketStatus::Closed, 'channel' => 'chat']);
    ChatSession::factory()->ended()->create(['ticket_id' => $ticket3->id, 'agent_id' => $agent->id]);

    $service = app(ChatAvailabilityService::class);
    expect($service->getAgentChatCount($agent->id))->toBe(2);
});

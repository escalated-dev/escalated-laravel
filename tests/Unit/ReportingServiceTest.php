<?php

use Escalated\Laravel\Enums\TicketChannel;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ReportingService;

beforeEach(function () {
    $this->service = new ReportingService;
});

// ──────────────────────────────────────────────────────────────────────
// SLA Breach Trends
// ──────────────────────────────────────────────────────────────────────

it('returns SLA breach trends grouped by day', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDays(2),
        'sla_first_response_breached' => true,
        'sla_resolution_breached' => false,
        'sla_policy_id' => 1,
    ]);
    Ticket::factory()->create([
        'created_at' => now()->subDay(),
        'sla_first_response_breached' => false,
        'sla_resolution_breached' => true,
        'sla_policy_id' => 1,
    ]);
    Ticket::factory()->create([
        'created_at' => now()->subDay(),
        'sla_first_response_breached' => true,
        'sla_resolution_breached' => true,
        'sla_policy_id' => 1,
    ]);

    $result = $this->service->slaBreachTrends(7, 'day');

    expect($result)->toBeArray()
        ->and(count($result))->toBe(2)
        ->and($result[0])->toHaveKeys(['period', 'first_response_breaches', 'resolution_breaches', 'total_breaches']);
});

it('returns SLA breach trends grouped by week', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDays(2),
        'sla_first_response_breached' => true,
        'sla_policy_id' => 1,
    ]);

    $result = $this->service->slaBreachTrends(30, 'week');

    expect($result)->toBeArray();
});

it('returns SLA breach trends grouped by month', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDays(5),
        'sla_resolution_breached' => true,
        'sla_policy_id' => 1,
    ]);

    $result = $this->service->slaBreachTrends(90, 'month');

    expect($result)->toBeArray();
});

it('returns SLA breach rate by department', function () {
    $dept = Department::factory()->create(['name' => 'Support']);
    $policy = SlaPolicy::factory()->create();

    Ticket::factory()->create([
        'department_id' => $dept->id,
        'sla_policy_id' => $policy->id,
        'sla_first_response_breached' => true,
        'created_at' => now()->subDay(),
    ]);
    Ticket::factory()->create([
        'department_id' => $dept->id,
        'sla_policy_id' => $policy->id,
        'sla_first_response_breached' => false,
        'sla_resolution_breached' => false,
        'created_at' => now()->subDay(),
    ]);

    $result = $this->service->slaBreachByDepartment(7);

    expect($result)->toBeArray()
        ->and($result[0]['department'])->toBe('Support')
        ->and($result[0]['total'])->toBe(2)
        ->and($result[0]['breached'])->toBe(1)
        ->and($result[0]['breach_rate'])->toBe(50.0);
});

it('returns SLA breach rate by priority', function () {
    $policy = SlaPolicy::factory()->create();

    Ticket::factory()->create([
        'priority' => TicketPriority::High,
        'sla_policy_id' => $policy->id,
        'sla_first_response_breached' => true,
        'created_at' => now()->subDay(),
    ]);
    Ticket::factory()->create([
        'priority' => TicketPriority::Low,
        'sla_policy_id' => $policy->id,
        'sla_first_response_breached' => false,
        'sla_resolution_breached' => false,
        'created_at' => now()->subDay(),
    ]);

    $result = $this->service->slaBreachByPriority(7);

    expect($result)->toBeArray()->and(count($result))->toBe(2);

    $highEntry = collect($result)->firstWhere('priority', TicketPriority::High);
    expect($highEntry['breach_rate'])->toBe(100.0);

    $lowEntry = collect($result)->firstWhere('priority', TicketPriority::Low);
    expect($lowEntry['breach_rate'])->toBe(0.0);
});

it('returns SLA risk forecast with time windows', function () {
    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'first_response_at' => null,
        'first_response_due_at' => now()->addMinutes(30),
        'sla_first_response_breached' => false,
    ]);

    $result = $this->service->slaRiskForecast();

    expect($result)->toHaveKeys(['1h', '4h', '8h'])
        ->and($result['1h'])->toHaveKeys(['first_response_at_risk', 'resolution_at_risk', 'total_at_risk'])
        ->and($result['1h']['first_response_at_risk'])->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────
// First Response Time
// ──────────────────────────────────────────────────────────────────────

it('returns first response time distribution', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDays(2),
        'first_response_at' => now()->subDays(2)->addMinutes(30),
    ]);
    Ticket::factory()->create([
        'created_at' => now()->subDays(2),
        'first_response_at' => now()->subDays(2)->addHours(5),
    ]);

    $result = $this->service->firstResponseTimeDistribution(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(5);

    $bucketLabels = array_column($result, 'bucket');
    expect($bucketLabels)->toContain('<1h', '1-4h', '4-8h', '8-24h', '>24h');

    // Verify percentages sum to ~100
    $totalPercentage = array_sum(array_column($result, 'percentage'));
    expect($totalPercentage)->toBe(100.0);
});

it('returns first response time trend', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDays(2),
        'first_response_at' => now()->subDays(2)->addHours(2),
    ]);
    Ticket::factory()->create([
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHours(4),
    ]);

    $result = $this->service->firstResponseTimeTrend(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(2)
        ->and($result[0])->toHaveKeys(['date', 'avg_hours', 'count']);
});

it('returns first response time by agent', function () {
    $agent = $this->createAgent();

    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHours(2),
    ]);

    $result = $this->service->firstResponseTimeByAgent(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(1)
        ->and($result[0])->toHaveKeys(['group', 'count', 'avg', 'median', 'p90']);
});

it('returns first response time by department', function () {
    $dept = Department::factory()->create();

    Ticket::factory()->create([
        'department_id' => $dept->id,
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHours(3),
    ]);

    $result = $this->service->firstResponseTimeByDepartment(7);

    expect($result)->toBeArray()->and(count($result))->toBe(1);
});

it('returns first response time by priority', function () {
    Ticket::factory()->create([
        'priority' => TicketPriority::High,
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHour(),
    ]);

    $result = $this->service->firstResponseTimeByPriority(7);

    expect($result)->toBeArray()->and(count($result))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────
// Resolution Time
// ──────────────────────────────────────────────────────────────────────

it('returns resolution time distribution', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDays(3),
        'resolved_at' => now()->subDays(3)->addHours(2),
    ]);
    Ticket::factory()->create([
        'created_at' => now()->subDays(2),
        'resolved_at' => now()->subDays(2)->addHours(30),
    ]);

    $result = $this->service->resolutionTimeDistribution(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(6);

    $bucketLabels = array_column($result, 'bucket');
    expect($bucketLabels)->toContain('<4h', '4-8h', '8-24h', '1-3d', '3-7d', '>7d');
});

it('returns resolution time trend', function () {
    Ticket::factory()->create([
        'created_at' => now()->subDays(2),
        'resolved_at' => now()->subDays(2)->addHours(5),
    ]);

    $result = $this->service->resolutionTimeTrend(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(1)
        ->and($result[0])->toHaveKeys(['date', 'avg_hours', 'count']);
});

it('returns resolution time by agent', function () {
    $agent = $this->createAgent();

    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(8),
    ]);

    $result = $this->service->resolutionTimeByAgent(7);

    expect($result)->toBeArray()->and(count($result))->toBe(1);
});

it('returns resolution time by department', function () {
    $dept = Department::factory()->create();

    Ticket::factory()->create([
        'department_id' => $dept->id,
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(6),
    ]);

    $result = $this->service->resolutionTimeByDepartment(7);

    expect($result)->toBeArray()->and(count($result))->toBe(1);
});

it('returns resolution time by channel', function () {
    Ticket::factory()->create([
        'channel' => TicketChannel::Email,
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(4),
    ]);
    Ticket::factory()->create([
        'channel' => TicketChannel::Chat,
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(1),
    ]);

    $result = $this->service->resolutionTimeByChannel(7);

    expect($result)->toBeArray()->and(count($result))->toBe(2);
});

// ──────────────────────────────────────────────────────────────────────
// Agent Performance
// ──────────────────────────────────────────────────────────────────────

it('returns agent performance ranking with composite scores', function () {
    $agent1 = $this->createAgent(['name' => 'Agent A', 'email' => 'a@test.com']);
    $agent2 = $this->createAgent(['name' => 'Agent B', 'email' => 'b@test.com']);

    // Agent A: 2 tickets, 1 resolved, fast FRT
    Ticket::factory()->create([
        'assigned_to' => $agent1->id,
        'created_at' => now()->subDays(2),
        'first_response_at' => now()->subDays(2)->addHour(),
        'resolved_at' => now()->subDay(),
    ]);
    Ticket::factory()->create([
        'assigned_to' => $agent1->id,
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHour(),
    ]);

    // Agent B: 1 ticket, 1 resolved, slow FRT
    Ticket::factory()->create([
        'assigned_to' => $agent2->id,
        'created_at' => now()->subDays(2),
        'first_response_at' => now()->subDays(2)->addHours(10),
        'resolved_at' => now()->subDay(),
    ]);

    $result = $this->service->agentPerformanceRanking(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(2)
        ->and($result[0])->toHaveKeys([
            'agent_id', 'agent_name', 'total_tickets', 'resolved_tickets',
            'resolution_rate', 'avg_response_hours', 'csat_average',
            'composite_score', 'rank',
        ])
        ->and($result[0]['rank'])->toBe(1)
        ->and($result[1]['rank'])->toBe(2);
});

it('returns agent workload distribution', function () {
    $agent = $this->createAgent();

    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDay(),
    ]);
    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDays(2),
    ]);

    $result = $this->service->agentWorkloadDistribution(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(1)
        ->and($result[0])->toHaveKeys(['agent_name', 'data']);
});

it('returns agent response time percentiles', function () {
    $agent = $this->createAgent();

    // Create multiple tickets with varying response times
    foreach ([1, 2, 3, 5, 8, 12, 20] as $hours) {
        Ticket::factory()->create([
            'assigned_to' => $agent->id,
            'created_at' => now()->subDays(3),
            'first_response_at' => now()->subDays(3)->addHours($hours),
        ]);
    }

    $result = $this->service->agentResponseTimePercentiles($agent->id, 7);

    expect($result)->toHaveKeys(['p50', 'p75', 'p90', 'p95', 'p99'])
        ->and($result['p50'])->toBeLessThanOrEqual($result['p75'])
        ->and($result['p75'])->toBeLessThanOrEqual($result['p90'])
        ->and($result['p90'])->toBeLessThanOrEqual($result['p95'])
        ->and($result['p95'])->toBeLessThanOrEqual($result['p99']);
});

it('returns zeros for agent with no response data', function () {
    $result = $this->service->agentResponseTimePercentiles(999, 7);

    expect($result)->toBe(['p50' => 0, 'p75' => 0, 'p90' => 0, 'p95' => 0, 'p99' => 0]);
});

it('returns agent productivity metrics', function () {
    $agent = $this->createAgent();

    $ticket = Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDays(2),
        'resolved_at' => now()->subDay(),
    ]);

    $result = $this->service->agentProductivity(7);

    expect($result)->toBeArray();
    $agentData = collect($result)->firstWhere('agent_id', $agent->id);
    if ($agentData) {
        expect($agentData)->toHaveKeys(['agent_id', 'total_resolved', 'resolved_per_day']);
    }
});

// ──────────────────────────────────────────────────────────────────────
// Cohort Analysis
// ──────────────────────────────────────────────────────────────────────

it('returns tickets by tag with metrics', function () {
    $tag = Tag::factory()->create(['name' => 'billing']);

    $ticket = Ticket::factory()->create([
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(5),
    ]);
    $ticket->tags()->attach($tag->id);

    $result = $this->service->ticketsByTag(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(1)
        ->and($result[0]['tag'])->toBe('billing')
        ->and($result[0])->toHaveKeys(['tag', 'volume', 'avg_resolution_hours', 'breached', 'breach_rate']);
});

it('returns tickets by department', function () {
    $dept = Department::factory()->create(['name' => 'Engineering']);

    Ticket::factory()->create([
        'department_id' => $dept->id,
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(10),
    ]);

    $result = $this->service->ticketsByDepartment(7);

    expect($result)->toBeArray()
        ->and($result[0]['department'])->toBe('Engineering')
        ->and($result[0])->toHaveKeys(['department', 'volume', 'resolved', 'avg_resolution_hours', 'breached', 'breach_rate']);
});

it('returns tickets by channel', function () {
    Ticket::factory()->create([
        'channel' => TicketChannel::Email,
        'created_at' => now()->subDay(),
        'resolved_at' => now()->subDay()->addHours(3),
    ]);

    $result = $this->service->ticketsByChannel(7);

    expect($result)->toBeArray()
        ->and($result[0])->toHaveKeys(['channel', 'volume', 'resolved', 'avg_resolution_hours', 'breached', 'breach_rate']);
});

it('returns tickets by type', function () {
    Ticket::factory()->create([
        'ticket_type' => 'question',
        'created_at' => now()->subDay(),
    ]);

    $result = $this->service->ticketsByType(7);

    expect($result)->toBeArray()
        ->and($result[0])->toHaveKeys(['type', 'volume', 'resolved', 'avg_resolution_hours', 'breached', 'breach_rate']);
});

it('returns tickets by priority with daily trends', function () {
    Ticket::factory()->create([
        'priority' => TicketPriority::High,
        'created_at' => now()->subDay(),
    ]);
    Ticket::factory()->create([
        'priority' => TicketPriority::Low,
        'created_at' => now()->subDay(),
    ]);

    $result = $this->service->ticketsByPriority(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(2)
        ->and($result[0])->toHaveKeys(['priority', 'data']);
});

it('returns requester analysis', function () {
    $user = $this->createTestUser();

    Ticket::factory()->count(3)->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->id,
        'created_at' => now()->subDay(),
    ]);

    $result = $this->service->requesterAnalysis(7);

    expect($result)->toBeArray()
        ->and(count($result))->toBe(1)
        ->and($result[0]['ticket_count'])->toBe(3)
        ->and($result[0]['is_repeat'])->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────
// Period Comparison
// ──────────────────────────────────────────────────────────────────────

it('returns period comparison with percentage changes', function () {
    // Previous period: 3 tickets
    Ticket::factory()->count(3)->create([
        'created_at' => now()->subDays(45),
    ]);

    // Current period: 5 tickets
    Ticket::factory()->count(5)->create([
        'created_at' => now()->subDays(5),
    ]);

    $result = $this->service->periodComparison(30);

    expect($result)->toHaveKeys([
        'total_tickets',
        'resolved_tickets',
        'avg_first_response_hours',
        'avg_resolution_hours',
        'sla_compliance_rate',
        'csat_average',
    ])
        ->and($result['total_tickets'])->toHaveKeys(['current', 'previous', 'change_percent'])
        ->and($result['total_tickets']['current'])->toBe(5)
        ->and($result['total_tickets']['previous'])->toBe(3);

    // 5 vs 3 = +66.7%
    expect($result['total_tickets']['change_percent'])->toBe(66.7);
});

it('handles period comparison when previous period has zero tickets', function () {
    Ticket::factory()->count(2)->create([
        'created_at' => now()->subDays(2),
    ]);

    $result = $this->service->periodComparison(7);

    expect($result['total_tickets']['current'])->toBe(2)
        ->and($result['total_tickets']['previous'])->toBe(0)
        ->and($result['total_tickets']['change_percent'])->toBe(100.0);
});

// ──────────────────────────────────────────────────────────────────────
// Volume Forecast
// ──────────────────────────────────────────────────────────────────────

it('returns ticket volume forecast with trend', function () {
    // Create tickets over several days
    for ($i = 7; $i >= 1; $i--) {
        Ticket::factory()->count($i)->create([
            'created_at' => now()->subDays($i),
        ]);
    }

    $result = $this->service->ticketVolumeForecast(7);

    expect($result)->toHaveKeys(['historical', 'forecast', 'trend', 'slope'])
        ->and($result['forecast'])->toBeArray()
        ->and(count($result['forecast']))->toBe(7);
});

it('handles forecast with insufficient data', function () {
    Ticket::factory()->create(['created_at' => now()->subDay()]);

    $result = $this->service->ticketVolumeForecast(7);

    expect($result['trend'])->toBe('insufficient_data')
        ->and($result['forecast'])->toBeEmpty();
});

<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\EscalationService;

beforeEach(function () {
    $this->escalationService = app(EscalationService::class);
});

it('evaluates rules and matches tickets', function () {
    EscalationRule::factory()->create([
        'conditions' => [
            ['field' => 'status', 'value' => 'open'],
            ['field' => 'age_hours', 'value' => 1],
        ],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'high'],
        ],
    ]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'created_at' => now()->subHours(2),
    ]);

    $escalated = $this->escalationService->evaluateRules();
    expect($escalated)->toBeGreaterThanOrEqual(1);
});

it('skips inactive rules', function () {
    EscalationRule::factory()->inactive()->create([
        'conditions' => [
            ['field' => 'status', 'value' => 'open'],
        ],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'critical'],
        ],
    ]);

    Ticket::factory()->create(['status' => TicketStatus::Open]);

    $escalated = $this->escalationService->evaluateRules();
    expect($escalated)->toBe(0);
});

it('matches unassigned condition', function () {
    EscalationRule::factory()->create([
        'conditions' => [
            ['field' => 'assigned', 'value' => 'unassigned'],
        ],
        'actions' => [
            ['type' => 'change_priority', 'value' => 'urgent'],
        ],
    ]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'assigned_to' => null,
    ]);

    $escalated = $this->escalationService->evaluateRules();
    expect($escalated)->toBeGreaterThanOrEqual(1);
});

it('returns zero when no rules exist', function () {
    Ticket::factory()->create(['status' => TicketStatus::Open]);

    $escalated = $this->escalationService->evaluateRules();
    expect($escalated)->toBe(0);
});

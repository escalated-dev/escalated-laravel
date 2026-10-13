<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\EscalatedManager;
use Escalated\Laravel\Models\Automation;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AutomationRunner;
use Escalated\Laravel\Services\EscalationService;
use Escalated\Laravel\Services\WorkflowEngine;
use Illuminate\Support\Facades\Gate;

it('matches stream and complete account identity in escalation rules and automations', function (string $engine) {
    $match = Ticket::factory()->create(['ticket_type' => 'incident', 'account_type' => 'company', 'account_id' => 'north', 'priority' => 'low']);
    $others = collect([
        Ticket::factory()->create(['ticket_type' => 'question', 'account_type' => 'company', 'account_id' => 'north', 'priority' => 'low']),
        Ticket::factory()->create(['ticket_type' => 'incident', 'account_type' => 'company', 'account_id' => 'south', 'priority' => 'low']),
        Ticket::factory()->create(['ticket_type' => 'incident', 'account_type' => 'partner', 'account_id' => 'north', 'priority' => 'low']),
        Ticket::factory()->create(['ticket_type' => 'incident', 'priority' => 'low']),
    ]);
    $attributes = [
        'name' => 'Account incident routing', 'is_active' => true,
        'conditions' => [
            ['field' => 'ticket_type', 'value' => 'incident'],
            ['field' => 'account', 'value' => ['type' => 'company', 'id' => 'north']],
        ],
        'actions' => [['type' => 'change_priority', 'value' => 'urgent']],
    ];
    if ($engine === 'escalation') {
        EscalationRule::factory()->create($attributes);
        expect(app(EscalationService::class)->evaluateRules())->toBe(1);
    } else {
        Automation::create($attributes);
        expect(app(AutomationRunner::class)->run())->toBe(1);
    }
    expect($match->fresh()->priority)->toBe(TicketPriority::Urgent);
    foreach ($others as $ticket) {
        expect($ticket->fresh()->priority)->toBe(TicketPriority::Low);
    }
})->with(['escalation', 'automation']);

it('supports account identity and type conditions in workflows', function () {
    $ticket = Ticket::factory()->create(['ticket_type' => 'incident', 'account_type' => 'company', 'account_id' => 'north']);
    $engine = app(WorkflowEngine::class);
    $rules = ['all' => [
        ['field' => 'ticket_type', 'operator' => 'equals', 'value' => 'incident'],
        ['field' => 'account', 'operator' => 'equals', 'value' => ['type' => 'company', 'id' => 'north']],
    ]];
    expect($engine->evaluateConditions($rules, $ticket))->toBeTrue();
    $ticket->account_type = 'partner';
    expect($engine->evaluateConditions($rules, $ticket))->toBeFalse();
    $ticket->account_type = null;
    $ticket->account_id = null;
    expect($engine->evaluateConditions($rules, $ticket))->toBeFalse();
});

it('selects the most specific active SLA with deterministic fallback', function () {
    config(['escalated.sla.targeted' => true]);
    $department = Department::factory()->create();
    $default = SlaPolicy::factory()->default()->create();
    $type = SlaPolicy::factory()->create(['ticket_type' => 'incident']);
    $dept = SlaPolicy::factory()->create(['department_id' => $department->id]);
    $both = SlaPolicy::factory()->create(['department_id' => $department->id, 'ticket_type' => 'incident', 'first_response_hours' => ['medium' => 1]]);
    SlaPolicy::factory()->create(['department_id' => $department->id, 'ticket_type' => 'incident', 'is_active' => false]);
    SlaPolicy::factory()->create(['department_id' => $department->id, 'ticket_type' => 'incident']);
    $ticket = Ticket::factory()->create(['department_id' => $department->id, 'ticket_type' => 'incident', 'priority' => 'medium']);
    expect($ticket->fresh()->sla_policy_id)->toBe($both->id)
        ->and($ticket->fresh()->first_response_due_at->equalTo($ticket->created_at->copy()->addHour()))->toBeTrue()
        ->and(Ticket::factory()->create(['department_id' => $department->id, 'ticket_type' => 'question'])->fresh()->sla_policy_id)->toBe($dept->id)
        ->and(Ticket::factory()->create(['ticket_type' => 'incident'])->fresh()->sla_policy_id)->toBe($type->id)
        ->and(Ticket::factory()->create(['ticket_type' => 'task'])->fresh()->sla_policy_id)->toBe($default->id);
});

it('keeps targeted SLA selection off by default', function () {
    $default = SlaPolicy::factory()->default()->create();
    SlaPolicy::factory()->create(['ticket_type' => 'incident']);
    expect(Ticket::factory()->create(['ticket_type' => 'incident'])->fresh()->sla_policy_id)->toBe($default->id);
});

it('does not attach an unmatched targeted default or an inactive policy', function () {
    config(['escalated.sla.targeted' => true]);
    SlaPolicy::factory()->default()->create(['ticket_type' => 'incident']);
    SlaPolicy::factory()->default()->create(['is_active' => false]);
    expect(Ticket::factory()->create(['ticket_type' => 'question'])->fresh()->sla_policy_id)->toBeNull();
});

it('rejects malformed account rules instead of applying actions to all tickets', function (string $engine) {
    Ticket::factory()->create();
    $attributes = ['name' => 'Invalid account', 'is_active' => true, 'conditions' => [['field' => 'account', 'value' => ['id' => 'north']]], 'actions' => []];
    if ($engine === 'escalation') {
        EscalationRule::factory()->create($attributes);
        expect(app(EscalationService::class)->evaluateRules())->toBe(0);
    } else {
        Automation::create($attributes);
        expect(app(AutomationRunner::class)->run())->toBe(0);
    }
    expect(app(WorkflowEngine::class)->evaluateConditions($attributes['conditions'], Ticket::first()))->toBeFalse();
})->with(['escalation', 'automation']);

it('rejects cloud-only operation when local ticket access options are enabled', function (string $option, mixed $value) {
    config(['escalated.mode' => 'cloud', $option => $value]);
    $this->expectException(LogicException::class);
    app(EscalatedManager::class)->driver();
})->with([
    ['escalated.visibility', 'department'],
    ['escalated.accounts.enabled', true],
    ['escalated.accounts.scope_agents', true],
    ['escalated.sla.targeted', true],
]);

it('validates policy department and type targets through the admin endpoint', function () {
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    $department = Department::factory()->create();
    $payload = ['name' => 'Targeted', 'department_id' => $department->id, 'ticket_type' => 'incident', 'first_response_hours' => ['medium' => 1], 'resolution_hours' => ['medium' => 4]];
    $this->actingAs($this->createAdmin())->post(route('escalated.admin.sla-policies.store'), $payload)->assertRedirect();
    expect(SlaPolicy::where('name', 'Targeted')->first()->department_id)->toBe($department->id);
    $this->postJson(route('escalated.admin.sla-policies.store'), array_merge($payload, ['department_id' => 999999, 'ticket_type' => 'unknown']))->assertUnprocessable();
});

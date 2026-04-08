<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\SlaBreached;
use Escalated\Laravel\Events\SlaWarning;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\SlaService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->slaService = new SlaService;
});

it('attaches default SLA policy to ticket', function () {
    $policy = SlaPolicy::factory()->create(['is_default' => true]);
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Medium]);

    $this->slaService->attachDefaultPolicy($ticket);

    $ticket->refresh();
    expect($ticket->sla_policy_id)->toBe($policy->id);
    expect($ticket->first_response_due_at)->not->toBeNull();
    expect($ticket->resolution_due_at)->not->toBeNull();
});

it('does nothing if no default policy exists', function () {
    $ticket = Ticket::factory()->create();

    $this->slaService->attachDefaultPolicy($ticket);

    $ticket->refresh();
    expect($ticket->sla_policy_id)->toBeNull();
});

it('detects first response breaches', function () {
    Event::fake([SlaBreached::class]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'first_response_at' => null,
        'first_response_due_at' => now()->subHour(),
        'sla_first_response_breached' => false,
    ]);

    $breached = $this->slaService->checkBreaches();

    expect($breached)->toBe(1);
    Event::assertDispatched(SlaBreached::class);
});

it('detects resolution breaches', function () {
    Event::fake([SlaBreached::class]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'resolution_due_at' => now()->subHour(),
        'sla_resolution_breached' => false,
    ]);

    $breached = $this->slaService->checkBreaches();

    expect($breached)->toBe(1);
    Event::assertDispatched(SlaBreached::class);
});

it('does not re-breach already breached tickets', function () {
    Event::fake([SlaBreached::class]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'first_response_due_at' => now()->subHour(),
        'sla_first_response_breached' => true,
    ]);

    $breached = $this->slaService->checkBreaches();

    expect($breached)->toBe(0);
    Event::assertNotDispatched(SlaBreached::class);
});

it('sends warnings for upcoming breaches', function () {
    Event::fake([SlaWarning::class]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'first_response_at' => null,
        'first_response_due_at' => now()->addMinutes(15),
        'sla_first_response_breached' => false,
    ]);

    $warned = $this->slaService->checkWarnings(30);

    expect($warned)->toBe(1);
    Event::assertDispatched(SlaWarning::class);
});

it('calculates due date with calendar hours', function () {
    $policy = SlaPolicy::factory()->create([
        'is_default' => true,
        'business_hours_only' => false,
        'first_response_hours' => [
            'low' => 24, 'medium' => 8, 'high' => 4, 'urgent' => 2, 'critical' => 1,
        ],
        'resolution_hours' => [
            'low' => 72, 'medium' => 48, 'high' => 24, 'urgent' => 8, 'critical' => 4,
        ],
    ]);

    $ticket = Ticket::factory()->create([
        'priority' => TicketPriority::High,
        'created_at' => now(),
    ]);

    $this->slaService->attachPolicy($ticket, $policy);

    $ticket->refresh();
    expect((int) abs($ticket->first_response_due_at->diffInHours($ticket->created_at)))->toBe(4);
    expect((int) abs($ticket->resolution_due_at->diffInHours($ticket->created_at)))->toBe(24);
});

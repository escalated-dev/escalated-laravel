<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\TicketEscalated;
use Escalated\Laravel\Events\TicketPriorityChanged;
use Escalated\Laravel\Events\TicketResolved;
use Escalated\Laravel\Events\TicketStatusChanged;
use Escalated\Laravel\Models\Automation;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\RuleExecution;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AutomationRunner;
use Escalated\Laravel\Services\EscalationService;
use Illuminate\Support\Facades\Event;

/**
 * The escalation and automation sweeps run every few minutes. A rule acts on a
 * ticket once, re-arms when the ticket stops matching, and one bad ticket
 * cannot stop the sweep.
 */
function sweepRule(array $actions, array $conditions = [['field' => 'status', 'value' => 'open']], array $attributes = []): EscalationRule
{
    return EscalationRule::factory()->create([
        'conditions' => $conditions,
        'actions' => $actions,
        ...$attributes,
    ]);
}

function sweepAutomation(array $actions, array $conditions, array $attributes = []): Automation
{
    return Automation::create([
        'name' => 'Sweep automation',
        'conditions' => $conditions,
        'actions' => $actions,
        'active' => true,
        'position' => 0,
        ...$attributes,
    ]);
}

it('keeps sweeping when one ticket cannot be escalated', function () {
    // Matches every non-closed ticket; the first one cannot move to escalated.
    sweepRule([['type' => 'escalate']], [['field' => 'age_hours', 'value' => 0]]);

    $stuck = Ticket::factory()->create(['status' => TicketStatus::WaitingOnCustomer]);
    $later = Ticket::factory()->create(['status' => TicketStatus::Open]);

    app(EscalationService::class)->evaluateRules();

    expect($stuck->fresh()->status)->toBe(TicketStatus::WaitingOnCustomer)
        ->and($later->fresh()->status)->toBe(TicketStatus::Escalated);
});

it('does not throw for a ticket that is already escalated', function () {
    sweepRule([['type' => 'escalate']], [['field' => 'age_hours', 'value' => 0]], ['order' => 0]);
    $second = sweepRule([['type' => 'change_priority', 'value' => 'urgent']], [['field' => 'age_hours', 'value' => 0]], ['order' => 5]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Escalated, 'priority' => TicketPriority::Low]);

    expect(fn () => app(EscalationService::class)->evaluateRules())->not->toThrow(Throwable::class)
        ->and($ticket->fresh()->priority)->toBe(TicketPriority::Urgent)
        ->and($second->exists)->toBeTrue();
});

it('skips an invalid priority value without aborting the sweep', function () {
    sweepRule([['type' => 'change_priority', 'value' => 'enormous']], attributes: ['order' => 0]);
    sweepRule([['type' => 'escalate']], attributes: ['order' => 5]);

    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    app(EscalationService::class)->evaluateRules();

    expect($ticket->fresh()->status)->toBe(TicketStatus::Escalated);
});

it('fires an escalation rule once per ticket, not on every sweep', function () {
    Event::fake([TicketPriorityChanged::class]);
    $rule = sweepRule([['type' => 'change_priority', 'value' => 'high']]);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Low]);

    $service = app(EscalationService::class);
    expect($service->evaluateRules())->toBe(1);

    // An agent lowers it again; the rule has already acted on this ticket.
    $ticket->fresh()->update(['priority' => TicketPriority::Low]);

    expect($service->evaluateRules())->toBe(0)
        ->and($service->evaluateRules())->toBe(0)
        ->and($ticket->fresh()->priority)->toBe(TicketPriority::Low);

    Event::assertDispatchedTimes(TicketPriorityChanged::class, 1);
    expect(RuleExecution::where('rule_id', $rule->id)->where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('re-arms a rule once the ticket stops matching', function () {
    sweepRule([['type' => 'change_priority', 'value' => 'high']], [['field' => 'status', 'value' => 'open']]);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Low]);
    $service = app(EscalationService::class);

    expect($service->evaluateRules())->toBe(1);

    $ticket->fresh()->update(['status' => TicketStatus::InProgress, 'priority' => TicketPriority::Low]);
    expect($service->evaluateRules())->toBe(0);

    $ticket->fresh()->update(['status' => TicketStatus::Open]);
    expect($service->evaluateRules())->toBe(1)
        ->and($ticket->fresh()->priority)->toBe(TicketPriority::High);
});

it('fires again after repeat_after_hours while the ticket still matches', function () {
    $this->travelTo(now());
    sweepRule([['type' => 'change_priority', 'value' => 'high']], attributes: ['repeat_after_hours' => 2]);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Low]);
    $service = app(EscalationService::class);

    expect($service->evaluateRules())->toBe(1);
    $ticket->fresh()->update(['priority' => TicketPriority::Low]);

    $this->travel(1)->hours();
    expect($service->evaluateRules())->toBe(0);

    $this->travel(90)->minutes();
    expect($service->evaluateRules())->toBe(1)
        ->and($ticket->fresh()->priority)->toBe(TicketPriority::High);
});

it('dispatches TicketEscalated once per rule escalation, with the rule as the reason', function () {
    Event::fake([TicketEscalated::class]);
    sweepRule([['type' => 'escalate']], attributes: ['name' => 'Stale tickets']);
    Ticket::factory()->create(['status' => TicketStatus::Open]);

    app(EscalationService::class)->evaluateRules();

    Event::assertDispatchedTimes(TicketEscalated::class, 1);
    Event::assertDispatched(TicketEscalated::class, fn ($event) => $event->reason === 'Escalation rule: Stale tickets');
});

it('does not put the agent record into the reason of a manual escalation', function () {
    Event::fake([TicketEscalated::class]);
    $agent = $this->createAgent(['name' => 'Agent Smith', 'email' => 'smith@example.com']);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $ticket->transitionTo(TicketStatus::Escalated, $agent);

    Event::assertDispatchedTimes(TicketEscalated::class, 1);
    Event::assertDispatched(TicketEscalated::class, fn ($event) => $event->reason === null);
});

it('adds an automation note once, not on every run', function () {
    sweepAutomation(
        [['type' => 'add_note', 'value' => 'Still waiting']],
        [['field' => 'hours_since_created', 'operator' => '>', 'value' => 24]],
    );
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'created_at' => now()->subDays(2)]);

    $runner = app(AutomationRunner::class);
    $runner->run();
    $runner->run();
    $runner->run();

    expect(Reply::where('ticket_id', $ticket->id)->where('body', 'Still waiting')->count())->toBe(1);
});

it('resolves through the domain when an automation changes status', function () {
    Event::fake([TicketStatusChanged::class, TicketResolved::class]);
    sweepAutomation(
        [['type' => 'change_status', 'value' => 'resolved']],
        [['field' => 'hours_since_updated', 'operator' => '>', 'value' => 48]],
    );
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);
    Ticket::whereKey($ticket->id)->toBase()->update(['updated_at' => now()->subDays(3)]);

    app(AutomationRunner::class)->run();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull();
    Event::assertDispatched(TicketStatusChanged::class);
    Event::assertDispatched(TicketResolved::class);
});

it('forgets the fire log of a deleted rule', function () {
    $rule = sweepRule([['type' => 'change_priority', 'value' => 'high']]);
    Ticket::factory()->create(['status' => TicketStatus::Open]);
    app(EscalationService::class)->evaluateRules();
    expect(RuleExecution::where('rule_id', $rule->id)->count())->toBe(1);

    $rule->delete();

    expect(RuleExecution::where('rule_id', $rule->id)->count())->toBe(0);
});

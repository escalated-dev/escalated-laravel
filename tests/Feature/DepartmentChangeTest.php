<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\DepartmentChanged;
use Escalated\Laravel\Events\TicketUnassigned;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\EscalationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/*
 * Ticket::changeDepartment() dispatched DepartmentChanged with the Department
 * model where the event takes an int, so every route that changes a ticket's
 * department threw a TypeError after the column had already been written.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    $this->from = Department::factory()->create(['name' => 'Fulfillment', 'slug' => 'fulfillment']);
    $this->to = Department::factory()->create(['name' => 'Delivery', 'slug' => 'delivery']);
    $this->ticket = Ticket::factory()->create(['department_id' => $this->from->id, 'status' => TicketStatus::Open]);
});

function expectDepartmentChanged(Ticket $ticket, int $from, int $to): void
{
    Event::assertDispatched(DepartmentChanged::class, function (DepartmentChanged $event) use ($ticket, $from, $to) {
        return $event->ticket->is($ticket)
            && $event->oldDepartmentId === $from
            && $event->newDepartmentId === $to;
    });
    expect($ticket->fresh()->department_id)->toBe($to);
}

it('dispatches DepartmentChanged with ids when called with a model', function () {
    Event::fake([DepartmentChanged::class]);

    $this->ticket->changeDepartment($this->to, null);

    expectDepartmentChanged($this->ticket, $this->from->id, $this->to->id);
});

it('dispatches DepartmentChanged with ids when called with an id', function () {
    Event::fake([DepartmentChanged::class]);

    $this->ticket->changeDepartment($this->to->id, null);

    expectDepartmentChanged($this->ticket, $this->from->id, $this->to->id);
});

it('passes the causer through to the event', function () {
    Event::fake([DepartmentChanged::class]);
    $agent = $this->createAgent();

    $this->ticket->changeDepartment($this->to, $agent);

    Event::assertDispatched(DepartmentChanged::class, fn (DepartmentChanged $event) => $event->causer?->is($agent));
});

it('records a null old department when the ticket had none', function () {
    Event::fake([DepartmentChanged::class]);
    $ticket = Ticket::factory()->create(['department_id' => null]);

    $ticket->changeDepartment($this->to, null);

    Event::assertDispatched(DepartmentChanged::class, fn (DepartmentChanged $event) => $event->oldDepartmentId === null
        && $event->newDepartmentId === $this->to->id);
});

it('changes the department from the agent ticket screen', function () {
    Event::fake([DepartmentChanged::class]);

    $this->actingAs($this->createAgent())
        ->post(route('escalated.agent.tickets.department', $this->ticket->reference), ['department_id' => $this->to->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expectDepartmentChanged($this->ticket, $this->from->id, $this->to->id);
});

it('changes the department from the admin ticket screen', function () {
    Event::fake([DepartmentChanged::class]);

    $this->actingAs($this->createAdmin())
        ->post(route('escalated.admin.tickets.department', $this->ticket->reference), ['department_id' => $this->to->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expectDepartmentChanged($this->ticket, $this->from->id, $this->to->id);
});

it('changes the department through the API macro endpoint', function () {
    Event::fake([DepartmentChanged::class]);
    $agent = $this->createAgent();
    $token = ApiToken::createToken($agent, 'Test', ['agent'])['plainTextToken'];
    $macro = Macro::create([
        'name' => 'Send to Delivery',
        'actions' => [['type' => 'department', 'value' => $this->to->id]],
        'is_shared' => true,
        'created_by' => $agent->getKey(),
    ]);

    $this->postJson(route('escalated.api.tickets.macro', $this->ticket->reference), ['macro_id' => $macro->id], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    expectDepartmentChanged($this->ticket, $this->from->id, $this->to->id);
});

it('changes the department from an escalation rule action', function () {
    Event::fake([DepartmentChanged::class]);
    EscalationRule::factory()->create([
        'conditions' => [['field' => 'department_id', 'value' => $this->from->id]],
        'actions' => [['type' => 'change_department', 'value' => $this->to->id]],
    ]);

    expect(app(EscalationService::class)->evaluateRules())->toBe(1);

    expectDepartmentChanged($this->ticket, $this->from->id, $this->to->id);
});

it('keeps the default listeners compatible with the event payload', function () {
    // Unfaked: the webhook and activity listeners receive the real event.
    $this->ticket->changeDepartment($this->to, null);

    expect($this->ticket->fresh()->department_id)->toBe($this->to->id);
});

/*
 * The sibling change* methods: unassignTicket() handed a string (UUID/ULID)
 * assignee to TicketUnassigned, which only took ?int.
 */
it('dispatches TicketUnassigned for a string-keyed assignee', function () {
    Event::fake([TicketUnassigned::class]);
    $ulid = '01JABCDEFGHJKMNPQRSTVWXYZ0';
    $this->ticket->setRawAttributes(array_merge($this->ticket->getAttributes(), ['assigned_to' => $ulid]), true);

    $this->ticket->unassignTicket();

    Event::assertDispatched(TicketUnassigned::class, fn (TicketUnassigned $event) => $event->previousAgentId === $ulid);
});

<?php

/*
 * What the agent ticket page needs beyond the ticket itself: the agents it can
 * be reassigned to, the requester's other tickets, and snoozing from the agent
 * panel (until now only the admin panel could snooze).
 */

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\RoutePermissions;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    $this->app->bind(EscalatedUiRenderer::class, fn () => new class implements EscalatedUiRenderer
    {
        public function render(string $page, array $props = []): mixed
        {
            return response()->json($props);
        }
    });
});

function workspaceShowProps($test, $user, Ticket $ticket): array
{
    return $test->actingAs($user)
        ->getJson(route('escalated.agent.tickets.show', $ticket->reference))
        ->assertOk()
        ->json();
}

// --- Reassigning ------------------------------------------------------------

it('sends the agents and admins a ticket can be assigned to, by id and name', function () {
    $agent = $this->createAgent(['name' => 'Ada Agent']);
    $admin = $this->createAdmin(['name' => 'Al Admin', 'email' => 'al@example.com']);
    $this->createTestUser(['name' => 'Cass Customer', 'email' => 'cass@example.com']);
    $ticket = Ticket::factory()->create();

    $agents = workspaceShowProps($this, $agent, $ticket)['agents'];

    expect($agents)->toBe([
        ['id' => $agent->id, 'name' => 'Ada Agent'],
        ['id' => $admin->id, 'name' => 'Al Admin'],
    ]);
});

it('leaves out agents who cannot see the ticket under department visibility', function () {
    $agent = $this->createAgent(['name' => 'Ada Agent']);
    $other = $this->createAgent(['name' => 'Otto Other', 'email' => 'otto@example.com']);
    $department = Department::factory()->create();
    $department->agents()->attach($agent);
    $ticket = Ticket::factory()->create(['department_id' => $department->id]);
    config(['escalated.visibility' => 'department']);

    $names = array_column(workspaceShowProps($this, $agent, $ticket)['agents'], 'name');

    expect($names)->toContain('Ada Agent')->not->toContain('Otto Other');
});

// --- The requester's other tickets ------------------------------------------

it('sends the requester\'s other tickets, newest first, at most five', function () {
    $agent = $this->createAgent();
    $customer = $this->createTestUser(['name' => 'Cass Customer', 'email' => 'cass@example.com']);
    $type = $customer->getMorphClass();

    $older = collect(range(1, 6))->map(fn ($i) => Ticket::factory()
        ->forRequester($type, $customer->id)
        ->create(['subject' => "Earlier {$i}", 'created_at' => now()->subDays(10 - $i)]));
    Ticket::factory()->create(['subject' => 'Someone else']);
    $ticket = Ticket::factory()->forRequester($type, $customer->id)->create(['created_at' => now()]);

    $recent = workspaceShowProps($this, $agent, $ticket)['ticket']['requester_tickets'];

    expect(array_column($recent, 'subject'))->toBe(['Earlier 6', 'Earlier 5', 'Earlier 4', 'Earlier 3', 'Earlier 2'])
        ->and(array_keys($recent[0]))->toBe(['id', 'reference', 'subject', 'status', 'created_at', 'updated_at'])
        ->and($recent[0]['reference'])->toBe($older[5]->reference)
        ->and($recent[0]['status'])->toBe('open');
});

it('matches a guest requester\'s other tickets by email', function () {
    $agent = $this->createAgent();
    Ticket::factory()->create(['guest_email' => 'guest@example.com', 'guest_name' => 'Gus', 'subject' => 'Earlier guest ticket']);
    Ticket::factory()->create(['guest_email' => 'other@example.com', 'subject' => 'Another guest']);
    $ticket = Ticket::factory()->create(['guest_email' => 'guest@example.com', 'guest_name' => 'Gus']);

    $recent = workspaceShowProps($this, $agent, $ticket)['ticket']['requester_tickets'];

    expect(array_column($recent, 'subject'))->toBe(['Earlier guest ticket']);
});

it('only lists requester tickets the agent may see', function () {
    $agent = $this->createAgent();
    $customer = $this->createTestUser(['name' => 'Cass Customer', 'email' => 'cass@example.com']);
    $type = $customer->getMorphClass();
    $department = Department::factory()->create();
    $department->agents()->attach($agent);
    Ticket::factory()->forRequester($type, $customer->id)->create(['department_id' => $department->id, 'subject' => 'Visible']);
    Ticket::factory()->forRequester($type, $customer->id)->create(['department_id' => Department::factory()->create()->id, 'subject' => 'Hidden']);
    $ticket = Ticket::factory()->forRequester($type, $customer->id)->create(['department_id' => $department->id]);
    config(['escalated.visibility' => 'department']);

    $recent = workspaceShowProps($this, $agent, $ticket)['ticket']['requester_tickets'];

    expect(array_column($recent, 'subject'))->toBe(['Visible']);
});

it('sends an empty list for a ticket with no known requester', function () {
    $agent = $this->createAgent();
    Ticket::factory()->create();
    $ticket = Ticket::factory()->create();

    expect(workspaceShowProps($this, $agent, $ticket)['ticket']['requester_tickets'])->toBe([]);
});

// --- Snoozing from the agent panel --------------------------------------------

it('lets an agent snooze a ticket', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);
    $until = now()->addDay()->startOfMinute();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.snooze', $ticket->reference), ['snoozed_until' => $until->toIso8601String()])
        ->assertRedirect()
        ->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->snoozed_until->equalTo($until))->toBeTrue()
        ->and($ticket->snoozed_by)->toBe($agent->id);
});

it('rejects a snooze time in the past', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.snooze', $ticket->reference), ['snoozed_until' => now()->subHour()->toIso8601String()])
        ->assertSessionHasErrors('snoozed_until');

    expect($ticket->refresh()->snoozed_until)->toBeNull();
});

it('lets an agent unsnooze a ticket', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::InProgress]);
    $this->actingAs($agent)->post(route('escalated.agent.tickets.snooze', $ticket->reference), [
        'snoozed_until' => now()->addDay()->toIso8601String(),
    ]);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.unsnooze', $ticket->reference))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($ticket->refresh()->snoozed_until)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::InProgress);
});

it('does not let a customer snooze through the agent panel', function () {
    $customer = $this->createTestUser();
    $ticket = Ticket::factory()->create();

    $this->actingAs($customer)
        ->post(route('escalated.agent.tickets.snooze', $ticket->reference), ['snoozed_until' => now()->addDay()->toIso8601String()])
        ->assertForbidden();

    expect($ticket->refresh()->snoozed_until)->toBeNull();
});

it('does not let an agent snooze a ticket outside their departments', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['department_id' => Department::factory()->create()->id]);
    config(['escalated.visibility' => 'department']);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.snooze', $ticket->reference), ['snoozed_until' => now()->addDay()->toIso8601String()])
        ->assertNotFound();

    expect(Ticket::withoutGlobalScopes()->find($ticket->id)->snoozed_until)->toBeNull();
});

it('maps the agent snooze routes to the ticket edit permission', function () {
    expect(RoutePermissions::isMapped('escalated.agent.tickets.snooze'))->toBeTrue()
        ->and(RoutePermissions::isMapped('escalated.agent.tickets.unsnooze'))->toBeTrue();
});

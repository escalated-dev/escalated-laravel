<?php

/*
 * Per-agent unread tracking (ADR 2026-11-05-agent-panel-data, section 1). A
 * ticket is unread for an agent when the customer side did something the
 * agent has not seen since they last opened it; the agent's own work, other
 * agents' replies and notes never make it unread.
 */

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketRead;
use Escalated\Laravel\Support\ImportContext;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    config(['escalated.agent_inbox.cache_seconds' => 0]);
});

function unreadList($test, $user, array $query = []): array
{
    $rows = $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.index', $query))
        ->assertOk()
        ->json('props');
    $test->flushHeaders();

    return $rows;
}

/** @return array<string, bool> reference => is_unread */
function unreadFlags($test, $user): array
{
    return collect(unreadList($test, $user)['tickets']['data'])
        ->mapWithKeys(fn (array $row) => [$row['reference'] => $row['is_unread']])
        ->sortKeys()
        ->all();
}

function openTicket($test, $user, Ticket $ticket): void
{
    $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.show', $ticket->reference))
        ->assertOk();
    $test->flushHeaders();
}

function customerTicket($test, array $attributes = []): array
{
    $customer = TestUser::create(['name' => 'Customer', 'email' => 'customer'.uniqid().'@example.com', 'password' => 'x']);
    $ticket = Ticket::factory()->create(array_merge([
        'requester_type' => $customer->getMorphClass(),
        'requester_id' => $customer->id,
        'status' => TicketStatus::Open,
    ], $attributes));

    return [$ticket, $customer];
}

function publicReply(Ticket $ticket, $author, array $attributes = []): Reply
{
    return Reply::create(array_merge([
        'ticket_id' => $ticket->id,
        'body' => 'Hello',
        'is_internal_note' => false,
        'type' => 'reply',
        'author_type' => $author?->getMorphClass(),
        'author_id' => $author?->getKey(),
    ], $attributes));
}

it('shows a new ticket as unread until the agent opens it', function () {
    $agent = $this->createAgent();
    [$ticket] = customerTicket($this);

    expect(unreadFlags($this, $agent))->toBe([$ticket->reference => true]);

    openTicket($this, $agent, $ticket);

    expect(unreadFlags($this, $agent))->toBe([$ticket->reference => false]);
});

it('keeps read markers per agent', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    [$ticket] = customerTicket($this);

    openTicket($this, $agent, $ticket);

    expect(unreadFlags($this, $agent)[$ticket->reference])->toBeFalse()
        ->and(unreadFlags($this, $other)[$ticket->reference])->toBeTrue();
});

it('makes a read ticket unread again on a customer reply or a guest chat message', function (string $by) {
    $agent = $this->createAgent();
    [$ticket, $customer] = customerTicket($this);
    openTicket($this, $agent, $ticket);

    Carbon::setTestNow(now()->addMinute());
    match ($by) {
        'requester' => publicReply($ticket, $customer),
        'guest' => publicReply($ticket, null),
        'contact' => publicReply($ticket, Contact::findOrCreateByEmail('guest@example.com')),
    };
    Carbon::setTestNow();

    expect(unreadFlags($this, $agent)[$ticket->reference])->toBeTrue();
})->with(['requester', 'guest', 'contact']);

it('does not make a ticket unread for agent replies, notes or system replies', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    [$ticket] = customerTicket($this);
    openTicket($this, $agent, $ticket);

    Carbon::setTestNow(now()->addMinute());
    publicReply($ticket, $other);
    publicReply($ticket, $agent);
    publicReply($ticket, TestUser::create(['name' => 'C', 'email' => 'cust2@example.com', 'password' => 'x']), ['is_internal_note' => true]);
    publicReply($ticket, null, ['metadata' => ['workflow_id' => 1]]);
    publicReply($ticket, null, ['is_internal_note' => true, 'metadata' => ['system_note' => true]]);
    Carbon::setTestNow();

    expect(unreadFlags($this, $agent)[$ticket->reference])->toBeFalse();
});

it('leaves imported tickets read', function () {
    $agent = $this->createAgent();
    $ticket = ImportContext::suppress(fn () => Ticket::factory()->create());

    expect(unreadFlags($this, $agent)[$ticket->reference])->toBeFalse();
});

it('marks a ticket read and unread through the agent routes', function () {
    $agent = $this->createAgent();
    [$ticket] = customerTicket($this);

    $this->actingAs($agent)->postJson(route('escalated.agent.tickets.read', $ticket->reference))
        ->assertOk()->assertExactJson(['is_unread' => false]);
    expect(unreadFlags($this, $agent)[$ticket->reference])->toBeFalse();

    $this->actingAs($agent)->postJson(route('escalated.agent.tickets.unread', $ticket->reference))
        ->assertOk()->assertExactJson(['is_unread' => true]);
    expect(unreadFlags($this, $agent)[$ticket->reference])->toBeTrue();

    $this->actingAs($agent)
        ->from(route('escalated.agent.tickets.index'))
        ->post(route('escalated.agent.tickets.read', $ticket->reference))
        ->assertRedirect(route('escalated.agent.tickets.index'));
});

it('marks a ticket with no customer activity unread on request', function () {
    $agent = $this->createAgent();
    $ticket = ImportContext::suppress(fn () => Ticket::factory()->create());

    $this->actingAs($agent)->postJson(route('escalated.agent.tickets.unread', $ticket->reference))->assertOk();

    expect(unreadFlags($this, $agent)[$ticket->reference])->toBeTrue();
});

it('marks tickets read and unread in bulk', function () {
    $agent = $this->createAgent();
    [$a] = customerTicket($this);
    [$b] = customerTicket($this);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.bulk'), ['ticket_ids' => [$a->id, $b->id], 'action' => 'mark_read'])
        ->assertRedirect();
    expect(unreadFlags($this, $agent))->toBe([$a->reference => false, $b->reference => false]);

    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.bulk'), ['ticket_ids' => [$a->id], 'action' => 'mark_unread'])
        ->assertRedirect();
    expect(unreadFlags($this, $agent))->toBe([$a->reference => true, $b->reference => false]);
});

it('sends is_unread false on the ticket page, which the visit marked read', function () {
    $agent = $this->createAgent();
    [$ticket] = customerTicket($this);

    $props = $this->actingAs($agent)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.show', $ticket->reference))
        ->assertOk()
        ->json('props');

    expect($props['ticket']['is_unread'])->toBeFalse()
        ->and(TicketRead::where('user_id', $agent->id)->where('ticket_id', $ticket->id)->value('last_read_at'))->not->toBeNull();
});

it('counts unread open tickets per queue in inbox.unread', function () {
    $agent = $this->createAgent();
    [$mine] = customerTicket($this, ['assigned_to' => $agent->id]);
    customerTicket($this, ['assigned_to' => $agent->id]);
    [$unassigned] = customerTicket($this);
    customerTicket($this, ['status' => TicketStatus::Resolved, 'resolved_at' => now()]);

    openTicket($this, $agent, $mine);
    openTicket($this, $agent, $unassigned);

    expect(unreadList($this, $agent)['inbox']['unread'])->toBe(['mine' => 1, 'unassigned' => 0, 'all' => 1]);
});

it('only counts and marks tickets the agent may see', function () {
    config(['escalated.visibility' => 'department']);
    $sales = Department::factory()->create();
    $support = Department::factory()->create();
    $agent = $this->createAgent();
    $support->agents()->attach($agent->id);

    [$visible] = customerTicket($this, ['department_id' => $support->id]);
    [$hidden] = customerTicket($this, ['department_id' => $sales->id]);

    expect(unreadList($this, $agent)['inbox']['unread']['all'])->toBe(1);

    $this->actingAs($agent)->postJson(route('escalated.agent.tickets.read', $hidden->reference))->assertNotFound();
    $this->actingAs($agent)
        ->post(route('escalated.agent.tickets.bulk'), ['ticket_ids' => [$hidden->id, $visible->id], 'action' => 'mark_read']);

    expect(TicketRead::where('user_id', $agent->id)->pluck('ticket_id')->all())->toBe([$visible->id]);
});

it('removes read markers with the ticket', function () {
    $agent = $this->createAgent();
    [$ticket] = customerTicket($this);
    openTicket($this, $agent, $ticket);

    $ticket->forceDelete();

    expect(TicketRead::count())->toBe(0);
});

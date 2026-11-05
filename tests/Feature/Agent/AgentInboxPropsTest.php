<?php

/*
 * The agent panel's optional `inbox` and `queue` props, and the list filters
 * its rail, views and stat strip link to (shared frontend docs/agent-panel.md).
 *
 * Every number here must be what the agent would get by opening the list it
 * labels, so the tests check counts against the list and against visibility.
 */

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SavedView;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    config(['escalated.agent_inbox.cache_seconds' => 0]);
});

function inertiaGet($test, $user, string $url)
{
    return $test->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get($url);
}

function inboxTicket(array $attributes = []): Ticket
{
    return Ticket::factory()->create(array_merge(['priority' => TicketPriority::Low, 'status' => TicketStatus::Open], $attributes));
}

const BUILT_IN_VIEW_IDS = ['urgent', 'sla_at_risk', 'waiting_on_me', 'waiting_on_customer', 'recently_updated'];

it('sends inbox counts, built-in views, stats and the queue on the ticket list', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);

    inboxTicket(['assigned_to' => $agent->id, 'priority' => TicketPriority::Urgent]);
    inboxTicket(['assigned_to' => $agent->id, 'status' => TicketStatus::WaitingOnCustomer]);
    inboxTicket(['assigned_to' => $agent->id, 'status' => TicketStatus::Resolved, 'resolved_at' => now()]);
    inboxTicket(['assigned_to' => $agent->id, 'status' => TicketStatus::Resolved, 'resolved_at' => now()->subDays(2)]);
    inboxTicket(['assigned_to' => $agent->id, 'snoozed_until' => now()->addDay()]);
    inboxTicket();
    inboxTicket(['status' => TicketStatus::Closed, 'closed_at' => now()]);
    inboxTicket(['assigned_to' => $other->id]);

    $response = inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->assertOk();

    expect($response->json('component'))->toBe('Escalated/Agent/TicketIndex');

    $inbox = $response->json('props.inbox');
    expect(array_keys($inbox))->toBe(['counts', 'unread', 'views', 'stats'])
        // Unresolved, unsnoozed tickets in each queue.
        ->and($inbox['counts'])->toBe(['mine' => 2, 'unassigned' => 1, 'all' => 4])
        ->and(array_column($inbox['views'], 'id'))->toBe(BUILT_IN_VIEW_IDS)
        ->and(array_keys($inbox['views'][0]))->toBe(['id', 'name', 'count', 'filters'])
        ->and($inbox['views'][0])->toBe(['id' => 'urgent', 'name' => 'Urgent', 'count' => 1, 'filters' => ['priority' => 'urgent', 'open' => 1]])
        ->and(array_keys($inbox['stats']))->toBe(['open', 'urgent', 'sla_at_risk', 'resolved_today'])
        // The "all" queue: three tickets with status open, one urgent, one resolved today.
        ->and($inbox['stats'])->toBe(['open' => 3, 'urgent' => 1, 'sla_at_risk' => 0, 'resolved_today' => 1])
        ->and($response->json('props.queue'))->toBe(['key' => 'all', 'title' => 'All tickets']);

    $views = collect($inbox['views'])->keyBy('id');
    expect($views['waiting_on_customer']['count'])->toBe(1)
        ->and($views['waiting_on_customer']['filters'])->toBe(['assigned_to' => $agent->id, 'status' => 'waiting_on_customer'])
        ->and($views['waiting_on_me']['filters'])->toBe(['assigned_to' => $agent->id, 'status' => 'waiting_on_agent'])
        ->and($views['sla_at_risk']['filters'])->toBe(['sla_at_risk' => 1])
        ->and($views['recently_updated']['filters'])->toBe(['open' => 1, 'updated_within_hours' => 24, 'sort_by' => 'updated_at'])
        ->and($views['recently_updated']['count'])->toBe(4);
});

it('names and scopes the queue from the list query', function (string $query, string $key, string $title, array $stats) {
    $agent = $this->createAgent();

    inboxTicket(['assigned_to' => $agent->id, 'priority' => TicketPriority::Urgent]);
    inboxTicket(['assigned_to' => $agent->id, 'status' => TicketStatus::Resolved, 'resolved_at' => now()]);
    inboxTicket();
    inboxTicket(['status' => TicketStatus::Resolved, 'resolved_at' => now()]);
    inboxTicket(['priority' => TicketPriority::Urgent]);

    $query = str_replace('{me}', (string) $agent->id, $query);
    $response = inertiaGet($this, $agent, route('escalated.agent.tickets.index').$query)->assertOk();

    expect($response->json('props.queue'))->toBe(['key' => $key, 'title' => $title])
        ->and($response->json('props.inbox.stats'))->toBe($stats);
})->with([
    'mine' => ['?assigned_to={me}', 'mine', 'My inbox', ['open' => 1, 'urgent' => 1, 'sla_at_risk' => 0, 'resolved_today' => 1]],
    'mine, by alias' => ['?assigned=me', 'mine', 'My inbox', ['open' => 1, 'urgent' => 1, 'sla_at_risk' => 0, 'resolved_today' => 1]],
    'mine with a stat tile on' => ['?assigned_to={me}&priority=urgent', 'mine', 'My inbox', ['open' => 1, 'urgent' => 1, 'sla_at_risk' => 0, 'resolved_today' => 1]],
    'unassigned' => ['?unassigned=1', 'unassigned', 'Unassigned', ['open' => 2, 'urgent' => 1, 'sla_at_risk' => 0, 'resolved_today' => 1]],
    'all' => ['', 'all', 'All tickets', ['open' => 3, 'urgent' => 2, 'sla_at_risk' => 0, 'resolved_today' => 2]],
    'a built-in view by its filters' => ['?priority=urgent&open=1', 'view:urgent', 'Urgent', ['open' => 2, 'urgent' => 2, 'sla_at_risk' => 0, 'resolved_today' => 0]],
    'a built-in view by id' => ['?view=urgent', 'view:urgent', 'Urgent', ['open' => 2, 'urgent' => 2, 'sla_at_risk' => 0, 'resolved_today' => 0]],
]);

it('lists the agent\'s own and shared saved views with counts, but not other agents\' private views', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);

    $mine = SavedView::create(['user_id' => $agent->id, 'name' => 'High priority', 'filters' => ['priority' => 'high'], 'position' => 1]);
    $shared = SavedView::create(['user_id' => $other->id, 'name' => 'Waiting', 'filters' => ['status' => 'waiting_on_agent'], 'is_shared' => true, 'position' => 2]);
    SavedView::create(['user_id' => $other->id, 'name' => 'Private', 'filters' => ['priority' => 'low'], 'position' => 0]);

    inboxTicket(['priority' => TicketPriority::High]);
    inboxTicket(['priority' => TicketPriority::High]);
    inboxTicket(['priority' => TicketPriority::High, 'status' => TicketStatus::Closed, 'closed_at' => now()]);
    inboxTicket(['status' => TicketStatus::WaitingOnAgent]);

    $views = inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->json('props.inbox.views');
    $saved = array_values(array_filter($views, fn ($view) => ! in_array($view['id'], BUILT_IN_VIEW_IDS, true)));

    expect($saved)->toBe([
        ['id' => $mine->id, 'name' => 'High priority', 'count' => 2, 'filters' => ['priority' => 'high']],
        ['id' => $shared->id, 'name' => 'Waiting', 'count' => 1, 'filters' => ['status' => 'waiting_on_agent']],
    ]);
});

it('selects a saved view as the queue, by id or by its filters, and filters the list with it', function (string $by) {
    $agent = $this->createAgent();
    $view = SavedView::create(['user_id' => $agent->id, 'name' => 'High priority', 'filters' => ['priority' => 'high'], 'position' => 1]);

    $high = inboxTicket(['priority' => TicketPriority::High]);
    inboxTicket(['priority' => TicketPriority::Low]);

    $query = $by === 'id' ? '?view='.$view->id : '?priority=high';
    $response = inertiaGet($this, $agent, route('escalated.agent.tickets.index').$query)->assertOk();

    expect($response->json('props.queue'))->toBe(['key' => 'view:'.$view->id, 'title' => 'High priority'])
        ->and(array_column($response->json('props.tickets.data'), 'id'))->toBe([$high->id]);
})->with(['id', 'filters']);

it('does not let an agent select another agent\'s private view', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    $private = SavedView::create(['user_id' => $other->id, 'name' => 'Private', 'filters' => ['priority' => 'high'], 'position' => 1]);

    inboxTicket(['priority' => TicketPriority::High]);
    inboxTicket(['priority' => TicketPriority::Low]);

    $response = inertiaGet($this, $agent, route('escalated.agent.tickets.index').'?view='.$private->id)->assertOk();

    expect($response->json('props.queue'))->toBe(['key' => 'all', 'title' => 'All tickets'])
        ->and($response->json('props.tickets.total'))->toBe(2);
});

it('gives every built-in view the count its own list shows', function () {
    $agent = $this->createAgent();

    inboxTicket(['assigned_to' => $agent->id, 'priority' => TicketPriority::Urgent]);
    inboxTicket(['priority' => TicketPriority::Urgent, 'status' => TicketStatus::Closed, 'closed_at' => now()]);
    inboxTicket(['assigned_to' => $agent->id, 'status' => TicketStatus::WaitingOnAgent]);
    inboxTicket(['assigned_to' => $agent->id, 'status' => TicketStatus::WaitingOnCustomer]);
    inboxTicket(['sla_first_response_breached' => true]);
    inboxTicket(['resolution_due_at' => now()->addMinutes(30)]);
    $stale = inboxTicket();
    Ticket::whereKey($stale->id)->update(['updated_at' => now()->subDays(3)]);

    $views = inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->json('props.inbox.views');

    foreach ($views as $view) {
        $total = inertiaGet($this, $agent, route('escalated.agent.tickets.index', $view['filters']))->json('props.tickets.total');
        expect($view['count'])->toBe($total, "view {$view['id']} counts {$view['count']} but its list shows {$total}");
    }

    expect(collect($views)->pluck('count', 'id')->all())->toBe([
        'urgent' => 1, 'sla_at_risk' => 2, 'waiting_on_me' => 1, 'waiting_on_customer' => 1, 'recently_updated' => 5,
    ]);
});

it('counts only tickets the agent may see', function () {
    config(['escalated.visibility' => 'department']);

    $agent = $this->createAgent();
    $support = Department::factory()->create();
    $billing = Department::factory()->create();
    $support->agents()->attach($agent->id);

    inboxTicket(['department_id' => $support->id, 'assigned_to' => $agent->id, 'priority' => TicketPriority::Urgent]);
    inboxTicket(['department_id' => $support->id]);
    inboxTicket(['department_id' => $billing->id]);
    inboxTicket(['department_id' => $billing->id, 'priority' => TicketPriority::Urgent]);
    inboxTicket(['department_id' => $billing->id, 'status' => TicketStatus::Resolved, 'resolved_at' => now()]);
    // Department routing may have assigned the new tickets; only the first is the agent's.
    Ticket::query()->withoutGlobalScopes()->where('priority', '!=', 'urgent')->update(['assigned_to' => null]);
    Ticket::query()->withoutGlobalScopes()->where('department_id', $billing->id)->update(['assigned_to' => null]);

    $inbox = inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->json('props.inbox');

    expect($inbox['counts'])->toBe(['mine' => 1, 'unassigned' => 1, 'all' => 2])
        ->and($inbox['stats'])->toBe(['open' => 2, 'urgent' => 1, 'sla_at_risk' => 0, 'resolved_today' => 0])
        ->and(collect($inbox['views'])->firstWhere('id', 'urgent')['count'])->toBe(1);
});

it('filters the list to tickets whose SLA is breached or due within the hour', function () {
    $agent = $this->createAgent();

    $breached = inboxTicket(['sla_resolution_breached' => true]);
    $responseDue = inboxTicket(['first_response_due_at' => now()->addMinutes(20)]);
    $resolutionDue = inboxTicket(['resolution_due_at' => now()->addMinutes(50)]);
    inboxTicket(['first_response_due_at' => now()->addMinutes(20), 'first_response_at' => now()->subMinute()]);
    inboxTicket(['resolution_due_at' => now()->addHours(3)]);
    inboxTicket(['sla_resolution_breached' => true, 'status' => TicketStatus::Resolved, 'resolved_at' => now()]);
    inboxTicket();

    $response = inertiaGet($this, $agent, route('escalated.agent.tickets.index', ['sla_at_risk' => 1]))->assertOk();
    $ids = array_column($response->json('props.tickets.data'), 'id');
    sort($ids);

    expect($ids)->toBe([$breached->id, $responseDue->id, $resolutionDue->id])
        ->and($response->json('props.inbox.stats.sla_at_risk'))->toBe(3);
});

it('filters the list to tickets resolved today', function () {
    $agent = $this->createAgent();

    $today = inboxTicket(['status' => TicketStatus::Resolved, 'resolved_at' => now()->startOfDay()->addMinute()]);
    inboxTicket(['status' => TicketStatus::Resolved, 'resolved_at' => now()->subDay()->startOfDay()]);
    inboxTicket();

    $response = inertiaGet($this, $agent, route('escalated.agent.tickets.index', ['status' => 'resolved', 'resolved_today' => 1]))->assertOk();

    expect(array_column($response->json('props.tickets.data'), 'id'))->toBe([$today->id]);
});

it('rejects malformed values for the new filters', function (array $query) {
    $agent = $this->createAgent();

    $this->actingAs($agent)->get(route('escalated.agent.tickets.index', $query))->assertSessionHasErrors(array_keys($query));
})->with([
    [['sla_at_risk' => 'soon']],
    [['resolved_today' => 'maybe']],
    [['updated_within_hours' => 0]],
    [['updated_within_hours' => 'a day']],
]);

it('previews the latest customer-visible message, never an internal note', function () {
    $agent = $this->createAgent();
    $ticket = inboxTicket();

    $author = ['author_type' => $agent->getMorphClass(), 'author_id' => $agent->id];
    Reply::factory()->create($author + ['ticket_id' => $ticket->id, 'body' => 'Customer-visible answer', 'created_at' => now()->subMinutes(5)]);
    Reply::factory()->internalNote()->create($author + ['ticket_id' => $ticket->id, 'body' => 'Secret internal note', 'created_at' => now()]);

    $row = inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->json('props.tickets.data.0');

    expect($row['latest_reply']['body'])->toBe('Customer-visible answer')
        // The agent's own replies are not customer activity; the new ticket is.
        ->and($row['is_unread'])->toBeTrue();
});

it('sends inbox and the queue the agent last opened on the ticket page', function () {
    $agent = $this->createAgent();
    $ticket = inboxTicket(['assigned_to' => $agent->id]);
    inboxTicket();

    $show = inertiaGet($this, $agent, route('escalated.agent.tickets.show', $ticket->reference))->assertOk();
    expect($show->json('component'))->toBe('Escalated/Agent/TicketShow')
        ->and($show->json('props.queue'))->toBe(['key' => 'all', 'title' => 'All tickets'])
        ->and($show->json('props.inbox.counts'))->toBe(['mine' => 1, 'unassigned' => 1, 'all' => 2])
        ->and(array_keys($show->json('props.inbox')))->toBe(['counts', 'unread', 'views', 'stats']);

    inertiaGet($this, $agent, route('escalated.agent.tickets.index', ['assigned_to' => $agent->id]))->assertOk();

    $show = inertiaGet($this, $agent, route('escalated.agent.tickets.show', $ticket->reference))->assertOk();
    expect($show->json('props.queue'))->toBe(['key' => 'mine', 'title' => 'My inbox'])
        ->and($show->json('props.inbox.stats'))->toBe(['open' => 1, 'urgent' => 0, 'sla_at_risk' => 0, 'resolved_today' => 0]);
});

it('caches the inbox per agent for the configured seconds', function () {
    config(['escalated.agent_inbox.cache_seconds' => 30]);
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);

    inboxTicket(['assigned_to' => $agent->id]);
    expect(inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->json('props.inbox.counts.mine'))->toBe(1);

    inboxTicket(['assigned_to' => $agent->id]);
    inboxTicket(['assigned_to' => $other->id]);

    expect(inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->json('props.inbox.counts.mine'))->toBe(1)
        ->and(inertiaGet($this, $other, route('escalated.agent.tickets.index'))->json('props.inbox.counts.mine'))->toBe(1);

    $this->travel(31)->seconds();

    expect(inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->json('props.inbox.counts.mine'))->toBe(2);
});

it('builds the inbox with a bounded number of queries', function () {
    $agent = $this->createAgent();
    foreach (range(1, 3) as $i) {
        SavedView::create(['user_id' => $agent->id, 'name' => "View {$i}", 'filters' => ['priority' => 'high'], 'position' => $i]);
    }
    Ticket::factory()->count(10)->create();

    DB::connection()->enableQueryLog();
    inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->assertOk();
    $withViews = count(DB::connection()->getQueryLog());

    Ticket::factory()->count(20)->create();
    DB::connection()->flushQueryLog();
    inertiaGet($this, $agent, route('escalated.agent.tickets.index'))->assertOk();

    // The number of queries does not grow with the number of tickets.
    expect(count(DB::connection()->getQueryLog()))->toBe($withViews);
});

it('sends no inbox in cloud mode, where tickets are not stored locally', function () {
    $agent = $this->createAgent();
    $ticket = inboxTicket();
    config(['escalated.mode' => 'cloud']);

    $show = inertiaGet($this, $agent, route('escalated.agent.tickets.show', $ticket->reference))->assertOk();

    expect($show->json('props'))->not->toHaveKey('inbox')
        ->and($show->json('props.queue'))->toBe(['key' => 'all', 'title' => 'All tickets']);
});

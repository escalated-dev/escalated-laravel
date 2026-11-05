<?php

/*
 * The agent notifications feed (ADR 2026-11-05-agent-panel-data, section 4):
 * Escalated's database notifications, listed, counted and marked read through
 * agent routes, with tickets the agent can no longer view redacted.
 */

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\AgentNotificationCreated;
use Escalated\Laravel\Events\ChatTransferred;
use Escalated\Laravel\Events\SlaWarning;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\TicketAssignedNotification;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    Mail::fake();
});

function feed($test, $agent, array $query = []): array
{
    return $test->actingAs($agent)
        ->getJson(route('escalated.agent.notifications.index', $query))
        ->assertOk()
        ->json();
}

/** The feed items of one type (creating an assigned ticket also sends new_ticket). */
function feedOf($test, $agent, string $type): array
{
    return collect(feed($test, $agent)['data'])->where('type', $type)->values()->all();
}

function feedCustomer(): TestUser
{
    return TestUser::create(['name' => 'Customer', 'email' => 'c'.uniqid().'@example.com', 'password' => 'x']);
}

it('lists a ticket assignment with the contract shape', function () {
    Carbon::setTestNow('2026-11-05 09:12:00');
    $agent = $this->createAgent();
    $admin = $this->createAdmin();
    $ticket = Ticket::factory()->create(['subject' => 'Printer on fire']);

    app(AssignmentService::class)->assign($ticket, $agent->id, $admin);

    $body = feed($this, $agent);
    $id = DatabaseNotification::sole()->id;

    expect($body)->toBe([
        'data' => [[
            'id' => $id,
            'type' => 'ticket_assigned',
            'title' => "{$ticket->reference} was assigned to you",
            'body' => 'Printer on fire',
            'ticket' => ['id' => $ticket->id, 'reference' => $ticket->reference, 'subject' => 'Printer on fire'],
            'url' => route('escalated.agent.tickets.show', $ticket->reference),
            'created_at' => '2026-11-05T09:12:00+00:00',
            'read_at' => null,
        ]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1],
        'unread_count' => 1,
    ]);
    Carbon::setTestNow();
});

it('does not notify an agent who assigns a ticket to themselves', function () {
    $agent = $this->createAgent();

    app(AssignmentService::class)->assign(Ticket::factory()->create(), $agent->id, $agent);

    expect(feed($this, $agent)['data'])->toBe([]);
});

it('notifies the assignee of a customer reply but not of another agent\'s reply', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    $customer = feedCustomer();
    $ticket = Ticket::factory()->create(['assigned_to' => $agent->id, 'requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id]);

    Reply::create(['ticket_id' => $ticket->id, 'body' => 'From another agent', 'is_internal_note' => false, 'type' => 'reply', 'author_type' => $other->getMorphClass(), 'author_id' => $other->id]);
    expect(feedOf($this, $agent, 'ticket_reply'))->toBe([]);

    Reply::create(['ticket_id' => $ticket->id, 'body' => 'From the customer', 'is_internal_note' => false, 'type' => 'reply', 'author_type' => $customer->getMorphClass(), 'author_id' => $customer->id]);
    $items = feedOf($this, $agent, 'ticket_reply');

    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('ticket_reply')
        ->and($items[0]['title'])->toBe("New reply on {$ticket->reference}");
});

it('notifies the assignee of an SLA warning, in-app only', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['assigned_to' => $agent->id]);

    Mail::fake();
    SlaWarning::dispatch($ticket, 'first_response', 20);

    $items = feedOf($this, $agent, 'sla_warning');
    expect($items)->toHaveCount(1)
        ->and($items[0]['type'])->toBe('sla_warning')
        ->and($items[0]['title'])->toBe("SLA due soon on {$ticket->reference}");
    Mail::assertNothingOutgoing();
});

it('notifies the receiving agent of a chat transfer', function () {
    $from = $this->createAgent();
    $to = $this->createAgent(['email' => 'to@example.com']);
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Live, 'channel' => 'chat']);
    $session = ChatSession::factory()->active($from->id)->create(['ticket_id' => $ticket->id]);

    ChatTransferred::dispatch($session, $from->id, $to->id, null);

    expect(collect(feed($this, $to)['data'])->pluck('type')->all())->toBe(['chat_transferred'])
        ->and(feed($this, $from)['data'])->toBe([]);
});

it('pages newest first and filters to unread', function () {
    $agent = $this->createAgent();
    $admin = $this->createAdmin();
    foreach (range(1, 3) as $i) {
        Carbon::setTestNow("2026-11-05 09:0{$i}:00");
        app(AssignmentService::class)->assign(Ticket::factory()->create(['subject' => "Ticket {$i}"]), $agent->id, $admin);
    }
    Carbon::setTestNow();

    $page = feed($this, $agent, ['per_page' => 2]);
    expect(collect($page['data'])->pluck('body')->all())->toBe(['Ticket 3', 'Ticket 2'])
        ->and($page['meta'])->toBe(['current_page' => 1, 'last_page' => 2, 'per_page' => 2, 'total' => 3]);

    $this->actingAs($agent)->postJson(route('escalated.agent.notifications.read', $page['data'][0]['id']))
        ->assertOk()->assertExactJson(['unread_count' => 2]);

    expect(collect(feed($this, $agent, ['unread' => 1])['data'])->pluck('body')->all())->toBe(['Ticket 2', 'Ticket 1']);

    $this->actingAs($agent)->getJson(route('escalated.agent.notifications.index', ['per_page' => 51]))->assertUnprocessable();
});

it('marks all read', function () {
    $agent = $this->createAgent();
    $admin = $this->createAdmin();
    app(AssignmentService::class)->assign(Ticket::factory()->create(), $agent->id, $admin);
    app(AssignmentService::class)->assign(Ticket::factory()->create(), $agent->id, $admin);

    $this->actingAs($agent)->postJson(route('escalated.agent.notifications.read-all'))
        ->assertOk()->assertExactJson(['unread_count' => 0]);

    expect(feed($this, $agent)['unread_count'])->toBe(0)
        ->and(collect(feed($this, $agent)['data'])->pluck('read_at')->filter()->count())->toBe(2);
});

it('will not mark another user\'s notification read', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    app(AssignmentService::class)->assign(Ticket::factory()->create(), $other->id, $this->createAdmin());

    $this->actingAs($agent)
        ->postJson(route('escalated.agent.notifications.read', DatabaseNotification::sole()->id))
        ->assertNotFound();

    expect(DatabaseNotification::sole()->read_at)->toBeNull();
});

it('lists only Escalated notifications', function () {
    $agent = $this->createAgent();
    $agent->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\InvoicePaid', 'data' => ['amount' => 10]]);

    expect(feed($this, $agent))->toMatchArray(['data' => [], 'unread_count' => 0]);
});

it('redacts tickets the agent can no longer view', function () {
    config(['escalated.visibility' => 'department']);
    $agent = $this->createAgent();
    $support = Department::factory()->create();
    $billing = Department::factory()->create();
    $support->agents()->attach($agent->id);
    $ticket = Ticket::factory()->create(['department_id' => $support->id, 'subject' => 'Secret subject']);
    $agent->notify(new TicketAssignedNotification($ticket));

    Ticket::query()->withoutGlobalScopes()->whereKey($ticket->id)->update(['department_id' => $billing->id]);

    $item = feed($this, $agent)['data'][0];
    expect($item)->toMatchArray([
        'type' => 'ticket_assigned',
        'title' => 'A ticket you can no longer view',
        'body' => null,
        'ticket' => null,
        'url' => null,
    ])->and(json_encode($item))->not->toContain('Secret subject');
});

it('shares the unread count with agents only', function () {
    $agent = $this->createAgent();
    app(AssignmentService::class)->assign(Ticket::factory()->create(), $agent->id, $this->createAdmin());

    $shared = fn ($user, $route) => $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route($route))->assertOk()->json('props.escalated');

    expect($shared($agent, 'escalated.agent.tickets.index')['notifications'])->toBe(['unread_count' => 1])
        ->and($shared(feedCustomer(), 'escalated.customer.tickets.index'))->not->toHaveKey('notifications');
});

it('is off without the database channel', function () {
    config(['escalated.notifications.channels' => ['mail']]);
    $agent = $this->createAgent();

    $this->actingAs($agent)->getJson(route('escalated.agent.notifications.index'))->assertNotFound();
    $this->actingAs($agent)->postJson(route('escalated.agent.notifications.read-all'))->assertNotFound();

    $shared = $this->actingAs($agent)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.agent.tickets.index'))->json('props.escalated');
    expect($shared)->not->toHaveKey('notifications');
});

it('is for agents only', function () {
    $this->actingAs(feedCustomer())->getJson(route('escalated.agent.notifications.index'))->assertForbidden();
});

it('broadcasts a new notification on the agent channel when broadcasting is on', function () {
    config(['escalated.broadcasting.enabled' => true]);
    Event::fake([AgentNotificationCreated::class]);
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    $agent->notify(new TicketAssignedNotification($ticket));

    Event::assertDispatched(AgentNotificationCreated::class, function (AgentNotificationCreated $event) use ($agent, $ticket) {
        return $event->agentId === $agent->id
            && $event->broadcastAs() === 'notification.created'
            && $event->broadcastOn()[0]->name === 'private-escalated.agents.'.$agent->id
            && $event->item['type'] === 'ticket_assigned'
            && $event->item['ticket']['reference'] === $ticket->reference;
    });
});

it('does not broadcast while broadcasting is off', function () {
    Event::fake([AgentNotificationCreated::class]);
    $agent = $this->createAgent();

    $agent->notify(new TicketAssignedNotification(Ticket::factory()->create()));

    Event::assertNotDispatched(AgentNotificationCreated::class);
});

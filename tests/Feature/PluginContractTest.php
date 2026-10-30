<?php

use Escalated\Laravel\Bridge\ContextHandler;
use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Facades\Hook;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;

/*
 * domain-model/plugin-contract.md: UI extensions are shared as
 * `escalated.plugins`, canonical events reach plugins' actions, and plugin
 * ticket/reply queries only filter on the contract's keys.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    Route::get('/login', fn () => 'login')->name('login');
});

function fakeSdkBridge(array $manifests = []): MockInterface
{
    $bridge = Mockery::mock(PluginBridge::class);
    $bridge->shouldReceive('isBooted')->andReturn(true)->byDefault();
    $bridge->shouldReceive('getManifests')->andReturn($manifests)->byDefault();
    app()->instance(PluginBridge::class, $bridge);

    return $bridge;
}

// ---------------------------------------------------------------------------
// UI extensions
// ---------------------------------------------------------------------------

it('shares native plugin menus, widgets and slots as escalated.plugins', function () {
    escalated_register_menu_item(['label' => 'Billing', 'url' => '/support/admin/billing', 'icon' => 'cash', 'target' => 'admin', 'position' => 5]);
    escalated_register_menu_item(['label' => 'Queue', 'url' => '/support/agent/queue', 'target' => 'agent']);
    escalated_register_menu_item(['label' => 'Secret', 'url' => '/x', 'permission' => 'nobody-has-this']);
    escalated_register_dashboard_widget(['id' => 'acme.stats', 'title' => 'Stats', 'component' => 'StatsWidget', 'plugin' => 'acme', 'width' => 'half']);
    escalated_add_page_component('ticket.show', 'sidebar', ['component' => 'Panel', 'plugin' => 'acme', 'data' => ['a' => 1], 'position' => 3]);

    $response = $this->actingAs($this->createAdmin())->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk();

    $plugins = $response->json('props.escalated.plugins');
    expect(array_column($plugins['menuItems'], 'label'))->toBe(['Billing', 'Queue'])
        ->and($plugins['menuItems'][0])->toMatchArray(['route' => '/support/admin/billing', 'icon' => 'cash', 'position' => 5, 'section' => 'admin'])
        ->and($plugins['dashboardWidgets'][0])->toMatchArray(['id' => 'acme.stats', 'title' => 'Stats', 'component' => 'StatsWidget', 'plugin' => 'acme', 'width' => 'half'])
        ->and($plugins['pageComponents']['ticket.show.sidebar'][0])->toMatchArray(['plugin' => 'acme', 'component' => 'Panel', 'data' => ['a' => 1], 'position' => 3])
        ->and($response->json('props.escalated'))->not->toHaveKeys(['plugin_menu_items', 'plugin_dashboard_widgets', 'plugin_pages']);
});

it('keeps admin menu items from agents and sends {} with no extensions', function () {
    $this->actingAs($this->createAgent())->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk()
        ->assertJsonPath('props.escalated.plugins', []);

    escalated_register_menu_item(['label' => 'Billing', 'url' => '/support/admin/billing', 'target' => 'admin']);
    $this->actingAs($this->createAgent(['email' => 'a2@example.com']))->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk()
        ->assertJsonPath('props.escalated.plugins', []);

    $this->actingAs($this->createAdmin())->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk()
        ->assertJsonPath('props.escalated.plugins.menuItems.0.label', 'Billing');
});

it('sends escalated.plugins as a JSON object when empty', function () {
    $raw = $this->actingAs($this->createAgent())->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk()->getContent();

    expect($raw)->toContain('"plugins":{}');
});

it('shares SDK plugin manifest menus, widgets and slots keyed by slug', function () {
    fakeSdkBridge(['@acme/plugin-crm' => [
        'pages' => [
            ['route' => '/settings', 'component' => 'Settings', 'menu' => ['label' => 'CRM', 'icon' => 'users', 'position' => 7]],
            ['route' => 'hidden', 'component' => 'Hidden'],
        ],
        'widgets' => [['component' => 'CrmWidget', 'label' => 'CRM deals', 'order' => 2, 'size' => 'full']],
        'components' => [['page' => 'ticket.show', 'slot' => 'sidebar', 'component' => 'CrmPanel', 'props' => ['x' => 1], 'order' => 4]],
    ]]);

    $plugins = $this->actingAs($this->createAdmin())->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk()->json('props.escalated.plugins');

    expect($plugins['menuItems'])->toBe([[
        'label' => 'CRM', 'route' => '/support/admin/plugins/@acme/plugin-crm/settings', 'icon' => 'users',
        'position' => 7, 'section' => 'admin', 'plugin' => 'acme--plugin-crm',
    ]])->and($plugins['dashboardWidgets'])->toBe([[
        'id' => 'acme--plugin-crm.CrmWidget', 'title' => 'CRM deals', 'component' => 'CrmWidget',
        'plugin' => 'acme--plugin-crm', 'position' => 2, 'width' => 'full',
    ]])->and($plugins['pageComponents'])->toBe(['ticket.show.sidebar' => [[
        'plugin' => 'acme--plugin-crm', 'component' => 'CrmPanel', 'data' => ['x' => 1], 'position' => 4,
    ]]]);
});

// ---------------------------------------------------------------------------
// Canonical events
// ---------------------------------------------------------------------------

it('delivers ticket.created to native and SDK plugins when a ticket is created over HTTP', function () {
    $native = [];
    Hook::addAction('ticket.created', function ($payload) use (&$native) {
        $native[] = $payload;
    });
    $sdk = [];
    $bridge = fakeSdkBridge();
    $bridge->shouldReceive('dispatchAction')->andReturnUsing(function ($hook, $event) use (&$sdk) {
        $sdk[] = [$hook, $event];
    });

    $user = $this->createTestUser();
    $this->actingAs($user)->post(route('escalated.customer.tickets.store'), [
        'subject' => 'Plugin ticket', 'description' => 'Body', 'priority' => 'medium',
    ])->assertRedirect();

    $ticket = Ticket::where('subject', 'Plugin ticket')->firstOrFail();
    expect($native)->toHaveCount(1)
        ->and($native[0])->toMatchArray(['id' => $ticket->id, 'reference' => $ticket->reference, 'subject' => 'Plugin ticket', 'title' => 'Plugin ticket', 'status' => 'open', 'priority' => 'medium'])
        ->and($native[0]['ticket'])->toBe(collect($native[0])->except('ticket')->all())
        ->and($native[0])->toHaveKeys(['department_id', 'assigned_to', 'requester_id', 'metadata', 'created_at', 'updated_at']);

    $hooks = array_column($sdk, 0);
    expect($hooks)->toContain('ticket.created')
        ->and($sdk[array_search('ticket.created', $hooks)][1]['ticket']['id'])->toBe($ticket->id);
});

it('delivers reply.created with reply and ticket, and never a legacy name', function () {
    $seen = [];
    foreach (['reply.created', 'ticket.replied', 'note.created', 'internal_note.added'] as $name) {
        Hook::addAction($name, function ($payload) use (&$seen, $name) {
            $seen[] = [$name, $payload];
        });
    }

    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create(['requester_type' => $user->getMorphClass(), 'requester_id' => $user->id]);
    $this->actingAs($user)->post(route('escalated.customer.tickets.reply', $ticket->reference), ['body' => 'Hello plugin'])
        ->assertRedirect();

    $reply = Reply::where('body', 'Hello plugin')->firstOrFail();
    expect(array_column($seen, 0))->toBe(['reply.created'])
        ->and($seen[0][1])->toMatchArray(['id' => $reply->id, 'ticket_id' => $ticket->id, 'body' => 'Hello plugin', 'is_internal' => false])
        ->and($seen[0][1]['reply']['id'])->toBe($reply->id)
        ->and($seen[0][1]['ticket']['id'])->toBe($ticket->id);
});

it('does not fail the request when a plugin action throws', function () {
    Hook::addAction('ticket.created', fn () => throw new RuntimeException('plugin broke'));
    $bridge = fakeSdkBridge();
    $bridge->shouldReceive('dispatchAction')->andThrow(new RuntimeException('runtime broke'));

    $this->actingAs($this->createTestUser())->post(route('escalated.customer.tickets.store'), [
        'subject' => 'Still created', 'description' => 'Body', 'priority' => 'low',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Ticket::where('subject', 'Still created')->exists())->toBeTrue();
});

it('delivers status, priority and assignment changes with their extras', function () {
    $seen = [];
    foreach (['ticket.status_changed', 'ticket.priority_changed', 'ticket.assigned'] as $name) {
        Hook::addAction($name, function ($payload) use (&$seen, $name) {
            $seen[$name] = $payload;
        });
    }
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create(['status' => 'open', 'priority' => 'low']);

    $this->actingAs($agent)->post(route('escalated.agent.tickets.priority', $ticket->reference), ['priority' => 'high'])->assertRedirect();
    $this->actingAs($agent)->post(route('escalated.agent.tickets.assign', $ticket->reference), ['agent_id' => $agent->id])->assertRedirect();
    $this->actingAs($agent)->post(route('escalated.agent.tickets.status', $ticket->reference), ['status' => 'in_progress'])->assertRedirect();

    expect($seen['ticket.priority_changed'])->toMatchArray(['old_priority' => 'low', 'new_priority' => 'high'])
        ->and($seen['ticket.assigned'])->toMatchArray(['agent_id' => $agent->id])
        ->and($seen['ticket.status_changed'])->toMatchArray(['new_status' => 'in_progress'])
        ->and($seen['ticket.status_changed']['ticket']['id'])->toBe($ticket->id);
});

it('drops ctx.emit names that impersonate a canonical or lifecycle event', function () {
    $bridge = fakeSdkBridge();
    $bridge->shouldReceive('dispatchAction')->once()->with('acme.synced', ['n' => 1]);
    $handler = new ContextHandler;
    $handler->setBridge($bridge);
    $handler->setCurrentPlugin('acme');

    $handler->handle('ctx.emit', ['hook' => 'ticket.created', 'data' => ['id' => 1]]);
    $handler->handle('ctx.emit', ['hook' => 'plugin.activated', 'data' => []]);
    $handler->handle('ctx.emit', ['hook' => 'ticket.replied', 'data' => []]);
    $handler->handle('ctx.emit', ['hook' => 'acme.synced', 'data' => ['n' => 1]]);
});

// ---------------------------------------------------------------------------
// ctx.tickets.query / ctx.replies.query
// ---------------------------------------------------------------------------

it('maps contract ticket filter keys to columns', function () {
    $agent = $this->createAgent();
    $mine = Ticket::factory()->create(['priority' => 'high', 'assigned_to' => $agent->id]);
    Ticket::factory()->create(['priority' => 'low']);
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme');

    $rows = $handler->handle('ctx.tickets.query', ['filter' => ['assigned_to' => $agent->id, 'priority' => 'high']]);
    expect(array_column($rows, 'id'))->toBe([$mine->id])
        ->and(array_column($handler->handle('ctx.tickets.query', ['filter' => ['reference' => $mine->reference]]), 'id'))->toBe([$mine->id]);
});

it('rejects ticket filter keys outside the contract', function (string $key) {
    Ticket::factory()->create();
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme');

    expect(fn () => $handler->handle('ctx.tickets.query', ['filter' => [$key => 'x']]))
        ->toThrow(InvalidArgumentException::class);
})->with(['guest_token', 'subject', 'id) or 1=1 --', 'requester_type']);

it('rejects non-scalar ticket filter values', function () {
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme');

    expect(fn () => $handler->handle('ctx.tickets.query', ['filter' => ['id' => ['$gt' => 0]]]))
        ->toThrow(InvalidArgumentException::class);
});

it('maps is_internal to the note column and rejects other reply keys', function () {
    $ticket = Ticket::factory()->create();
    $note = Reply::create(['ticket_id' => $ticket->id, 'body' => 'note', 'is_internal_note' => true, 'type' => 'note']);
    Reply::create(['ticket_id' => $ticket->id, 'body' => 'public', 'is_internal_note' => false, 'type' => 'reply']);
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme');

    expect(array_column($handler->handle('ctx.replies.query', ['filter' => ['ticket_id' => $ticket->id, 'is_internal' => true]]), 'id'))
        ->toBe([$note->id])
        ->and(fn () => $handler->handle('ctx.replies.query', ['filter' => ['is_internal_note' => true]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $handler->handle('ctx.replies.query', ['filter' => ['body' => 'note']]))
        ->toThrow(InvalidArgumentException::class);
});

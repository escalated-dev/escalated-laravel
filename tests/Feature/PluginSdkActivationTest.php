<?php

use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Bridge\RouteRegistrar;
use Escalated\Laravel\Bridge\SdkPluginActivation;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Plugin;
use Escalated\Laravel\Models\PluginStoreRecord;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
 * domain-model/plugin-contract.md: SDK plugins are listed on Admin > Plugins,
 * activated and deactivated there like native ones, and only active SDK
 * plugins get hooks, routes, cron, badges and UI extensions. The runtime
 * loads every installed plugin, so these run the real stdio bridge against a
 * stand-in runtime that logs what the host sends it.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    Route::get('/login', fn () => 'login')->name('login');

    $this->runtimeLog = tempnam(sys_get_temp_dir(), 'esc-plugin-runtime-');
    config([
        'escalated.plugins.runtime_command' => escapeshellarg(PHP_BINARY).' '
            .escapeshellarg(__DIR__.'/../Fixtures/plugin-activation-runtime.php').' '.escapeshellarg($this->runtimeLog),
        'escalated.plugins.runtime_cwd' => __DIR__,
    ]);
});

afterEach(function () {
    app()->forgetInstance(PluginBridge::class);
    @unlink($this->runtimeLog);
});

/**
 * Boot the real bridge against the stand-in runtime. With $recorded, SDK
 * plugins were already recorded (an install that has used this version).
 */
function bootActivationRuntime(bool $recorded = true): PluginBridge
{
    if ($recorded) {
        EscalatedSettings::set(SdkPluginActivation::RECORDED_SETTING, now()->toIso8601String());
    }

    $bridge = new PluginBridge;
    $bridge->boot();
    expect($bridge->isBooted())->toBeTrue();
    app()->instance(PluginBridge::class, $bridge);

    return $bridge;
}

/**
 * What the host sent the runtime since the last call, minus pings.
 *
 * @return list<array{method: string, params?: array}>
 */
function runtimeSaw(): array
{
    $file = test()->runtimeLog;
    $lines = array_filter(explode("\n", (string) file_get_contents($file)));
    file_put_contents($file, '');

    return array_values(array_filter(array_map(fn ($line) => json_decode($line, true), $lines),
        fn ($entry) => $entry['method'] !== 'ping'));
}

function indexPlugins($test): array
{
    $response = $test->get(route('escalated.admin.plugins.index'))->assertOk();

    return collect($response->original->getData()['page']['props']['plugins'])->keyBy('slug')->all();
}

// ---------------------------------------------------------------------------
// Admin > Plugins
// ---------------------------------------------------------------------------

it('lists SDK plugins on Admin > Plugins by slug, inactive until activated', function () {
    bootActivationRuntime();
    $this->actingAs($this->createAdmin());

    $plugins = indexPlugins($this);

    expect($plugins)->toHaveKeys(['acme--plugin-crm', 'acme--plugin-quiet'])
        ->and($plugins['acme--plugin-crm'])->toMatchArray([
            'slug' => 'acme--plugin-crm', 'name' => '@acme/plugin-crm', 'version' => '1.2.3',
            'description' => 'CRM plugin', 'is_active' => false, 'activated_at' => null, 'source' => 'composer',
            'provides' => ['menu_items' => 1, 'widgets' => 1, 'page_components' => 1],
        ])
        ->and($plugins['acme--plugin-crm'])->toHaveKeys(['author', 'author_url', 'homepage']);
    $this->get(route('escalated.admin.plugins.index'))->assertInertia(fn ($page) => $page->where('can_upload', true));
});

it('records the SDK plugins already installed as active the first time it sees them', function () {
    bootActivationRuntime(recorded: false);

    expect(Plugin::where('is_active', true)->pluck('slug')->sort()->values()->all())
        ->toBe(['acme--plugin-crm', 'acme--plugin-quiet'])
        ->and(EscalatedSettings::get(SdkPluginActivation::RECORDED_SETTING))->not->toBeNull();

    // Once recorded, a plugin an admin deactivated stays inactive on the next boot.
    Plugin::where('slug', 'acme--plugin-quiet')->update(['is_active' => false]);
    app()->forgetInstance(PluginBridge::class);
    bootActivationRuntime(recorded: false);

    expect(Plugin::where('slug', 'acme--plugin-quiet')->value('is_active'))->toBeFalse();
});

it('activates an SDK plugin: row, the runtime\'s activate, then plugin.activated', function () {
    bootActivationRuntime();
    runtimeSaw();
    $this->actingAs($this->createAdmin());

    $this->post(route('escalated.admin.plugins.activate', 'acme--plugin-crm'))
        ->assertStatus(303)->assertRedirect(route('escalated.admin.plugins.index'))
        ->assertSessionHas('success');

    $row = Plugin::where('slug', 'acme--plugin-crm')->firstOrFail();
    expect($row->is_active)->toBeTrue()->and($row->activated_at)->not->toBeNull()->and($row->deactivated_at)->toBeNull()
        ->and(runtimeSaw())->toBe([
            ['method' => 'activate', 'params' => ['plugin' => '@acme/plugin-crm']],
            ['method' => 'action', 'params' => [
                'hook' => 'plugin.activated',
                'event' => ['plugin' => '@acme/plugin-crm', 'slug' => 'acme--plugin-crm'],
                'plugins' => ['@acme/plugin-crm'],
            ]],
        ])
        ->and(indexPlugins($this)['acme--plugin-crm']['is_active'])->toBeTrue();

    // Idempotent: activating it again succeeds and does nothing.
    runtimeSaw();
    $this->post(route('escalated.admin.plugins.activate', 'acme--plugin-crm'))->assertStatus(303)->assertSessionHas('success');
    expect(runtimeSaw())->toBe([])->and(Plugin::where('slug', 'acme--plugin-crm')->count())->toBe(1);
});

it('deactivates an SDK plugin: plugin.deactivated and the runtime\'s deactivate while active, then the row', function () {
    bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm');
    runtimeSaw();
    $this->actingAs($this->createAdmin());

    $this->post(route('escalated.admin.plugins.deactivate', 'acme--plugin-crm'))
        ->assertStatus(303)->assertRedirect(route('escalated.admin.plugins.index'))
        ->assertSessionHas('success');

    $row = Plugin::where('slug', 'acme--plugin-crm')->firstOrFail();
    expect($row->is_active)->toBeFalse()->and($row->deactivated_at)->not->toBeNull()
        ->and(runtimeSaw())->toBe([
            ['method' => 'action', 'params' => [
                'hook' => 'plugin.deactivated',
                'event' => ['plugin' => '@acme/plugin-crm', 'slug' => 'acme--plugin-crm'],
                'plugins' => ['@acme/plugin-crm'],
            ]],
            ['method' => 'deactivate', 'params' => ['plugin' => '@acme/plugin-crm']],
        ]);

    $this->post(route('escalated.admin.plugins.deactivate', 'acme--plugin-crm'))->assertStatus(303)->assertSessionHas('success');
    expect(runtimeSaw())->toBe([]);
});

it('refuses an unknown slug without creating a row: an error flash, or 404 for JSON', function () {
    bootActivationRuntime();
    $this->actingAs($this->createAdmin());

    $this->post(route('escalated.admin.plugins.activate', 'acme--plugin-nope'))
        ->assertStatus(303)->assertSessionHas('error');
    $this->postJson(route('escalated.admin.plugins.activate', 'acme--plugin-nope'))->assertNotFound();
    $this->postJson(route('escalated.admin.plugins.deactivate', 'acme--plugin-nope'))->assertNotFound();

    expect(Plugin::where('slug', 'acme--plugin-nope')->exists())->toBeFalse();
});

it('refuses to uninstall an SDK plugin, which npm manages', function () {
    bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm');
    $this->actingAs($this->createAdmin());

    $this->delete(route('escalated.admin.plugins.destroy', 'acme--plugin-crm'))->assertRedirect()->assertSessionHas('error');

    expect(Plugin::where('slug', 'acme--plugin-crm')->value('is_active'))->toBeTrue();
});

it('keeps SDK plugin activation from agents', function () {
    bootActivationRuntime();
    $this->actingAs($this->createAgent());

    $this->post(route('escalated.admin.plugins.activate', 'acme--plugin-crm'))->assertForbidden();

    expect(Plugin::where('slug', 'acme--plugin-crm')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Only active SDK plugins are reached
// ---------------------------------------------------------------------------

it('sends actions and filters only with active plugins named, and nothing when none is active', function () {
    bootActivationRuntime();
    runtimeSaw();

    $this->actingAs($this->createTestUser())->post(route('escalated.customer.tickets.store'), [
        'subject' => 'Nobody listens', 'description' => 'Body', 'priority' => 'low',
    ])->assertRedirect();
    expect(escalated_apply_filters('import.adapters', ['x']))->toBe(['x'])
        ->and(runtimeSaw())->toBe([]);

    activateSdkPlugins('@acme/plugin-crm');
    $this->post(route('escalated.customer.tickets.store'), [
        'subject' => 'CRM listens', 'description' => 'Body', 'priority' => 'low',
    ])->assertRedirect();
    escalated_apply_filters('import.adapters', ['x']);

    $saw = collect(runtimeSaw());
    $created = $saw->first(fn ($m) => $m['method'] === 'action' && $m['params']['hook'] === 'ticket.created');
    $filter = $saw->first(fn ($m) => $m['method'] === 'filter');
    expect($created['params']['plugins'])->toBe(['@acme/plugin-crm'])
        ->and($created['params']['event']['subject'])->toBe('CRM listens')
        ->and($filter['params']['plugins'])->toBe(['@acme/plugin-crm']);
});

it('refuses ctx calls from an inactive plugin the runtime still dispatched to', function () {
    $bridge = bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm');
    runtimeSaw();

    $bridge->dispatchAction('test.ctx', []);

    $answers = collect(runtimeSaw())->where('method', 'ctx-answer')->keyBy('plugin');
    expect($answers['@acme/plugin-crm']['answer'])->toBe(['result' => null])
        ->and($answers['@acme/plugin-quiet']['answer'])->toHaveKey('error')
        ->and(PluginStoreRecord::where('plugin', '@acme/plugin-crm')->exists())->toBeTrue()
        ->and(PluginStoreRecord::where('plugin', '@acme/plugin-quiet')->exists())->toBeFalse();
});

it('answers an inactive plugin\'s pages, endpoints and webhooks 404 without asking the runtime', function () {
    bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm');
    runtimeSaw();
    $this->actingAs($this->createAdmin());

    $this->get('/support/admin/plugins/acme--plugin-quiet/settings')->assertNotFound();
    $this->getJson('/support/api/plugins/acme--plugin-quiet/ping')->assertNotFound();
    $this->postJson('/support/webhooks/plugins/acme--plugin-quiet/hook')->assertNotFound();
    expect(runtimeSaw())->toBe([]);

    $this->getJson('/support/api/plugins/acme--plugin-crm/ping')->assertOk()->assertJsonPath('plugin', '@acme/plugin-crm');
    $this->postJson('/support/webhooks/plugins/acme--plugin-crm/hook')->assertOk();
    $this->withHeaders(['X-Inertia' => 'true'])->get('/support/admin/plugins/acme--plugin-crm/settings')
        ->assertOk()->assertJsonPath('component', 'Escalated/Plugin/Page')->assertJsonPath('props.plugin', '@acme/plugin-crm');
    expect(collect(runtimeSaw())->pluck('params.plugin')->unique()->values()->all())->toBe(['@acme/plugin-crm']);
});

it('mounts SDK plugin pages, endpoints and webhooks on the slug, not the raw name', function () {
    bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm');
    runtimeSaw();
    $this->actingAs($this->createAdmin());

    expect(route('escalated.plugin.acme--plugin-crm.page.settings', [], false))->toBe('/support/admin/plugins/acme--plugin-crm/settings');
    $this->getJson('/support/api/plugins/@acme/plugin-crm/ping')->assertNotFound();
    $this->postJson('/support/webhooks/plugins/@acme/plugin-crm/hook')->assertNotFound();
    $this->get('/support/admin/plugins/@acme/plugin-crm/settings')->assertNotFound();
    expect(runtimeSaw())->toBe([]);

    $menu = $this->withHeaders(['X-Inertia' => 'true'])->get(route('escalated.agent.dashboard'))
        ->assertOk()->json('props.escalated.plugins.menuItems');
    expect(array_column($menu, 'route'))->toBe(['/support/admin/plugins/acme--plugin-crm/settings']);
});

it('takes the plugin of a ctx call from the call it serves, not from the runtime\'s plugin param', function () {
    bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm', '@acme/plugin-quiet');
    runtimeSaw();

    // An endpoint call serves one plugin: whatever the runtime names, the
    // write is that plugin's.
    $this->actingAs($this->createAdmin())->postJson('/support/api/plugins/acme--plugin-crm/forge')->assertOk();
    expect(PluginStoreRecord::where('plugin', '@acme/plugin-crm')->count())->toBe(1)
        ->and(PluginStoreRecord::where('plugin', '@acme/plugin-quiet')->exists())->toBeFalse()
        ->and(PluginStoreRecord::where('plugin', 'nobody')->exists())->toBeFalse();
});

it('refuses a ctx call during a hook naming a plugin the hook was not sent to', function () {
    $bridge = bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-quiet');
    runtimeSaw();

    $bridge->dispatchAction('test.forge', []);

    $answers = collect(runtimeSaw())->where('method', 'ctx-answer')->keyBy('plugin');
    expect($answers['@acme/plugin-quiet']['answer'])->toBe(['result' => null])
        ->and($answers['nobody']['answer'])->toHaveKey('error')
        ->and(PluginStoreRecord::where('plugin', 'nobody')->exists())->toBeFalse();
});

it('runs cron only for active plugins', function () {
    bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm');
    runtimeSaw();

    $this->artisan('escalated:plugins:cron', ['--at' => '2026-10-29T09:00:00Z'])->assertSuccessful();

    expect(runtimeSaw())->toBe([['method' => 'cron', 'params' => ['plugin' => '@acme/plugin-crm', 'schedule' => '* * * * *']]]);
});

it('shares menus, slots and widgets with badges of active plugins only', function () {
    bootActivationRuntime();
    activateSdkPlugins('@acme/plugin-crm');
    runtimeSaw();

    $plugins = $this->actingAs($this->createAdmin())->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk()->json('props.escalated.plugins');

    expect(array_column($plugins['menuItems'], 'plugin'))->toBe(['acme--plugin-crm'])
        ->and(array_column($plugins['dashboardWidgets'], 'plugin'))->toBe(['acme--plugin-crm'])
        ->and($plugins['dashboardWidgets'][0]['badge'])->toBe(5)
        ->and(array_column($plugins['pageComponents']['ticket.show.sidebar'], 'plugin'))->toBe(['acme--plugin-crm'])
        ->and(collect(runtimeSaw())->where('method', 'badge')->pluck('params.plugin')->all())->toBe(['@acme/plugin-crm']);
});

// ---------------------------------------------------------------------------
// Native plugins fire the dotted lifecycle actions too
// ---------------------------------------------------------------------------

it('fires plugin.activated, plugin.deactivated and plugin.uninstalling for a native plugin, and a throwing one undoes nothing', function () {
    $dir = config('escalated.plugins.path', app_path('Plugins/Escalated')).'/native-life';
    File::ensureDirectoryExists($dir);
    file_put_contents($dir.'/plugin.json', json_encode(['name' => 'Native Life']));
    file_put_contents($dir.'/Plugin.php', '<?php // noop');
    $seen = [];
    foreach (['plugin.activated', 'plugin.deactivated', 'plugin.uninstalling'] as $hook) {
        escalated_add_action($hook, function ($payload) use (&$seen, $hook) {
            $seen[] = [$hook, $payload];
            throw new RuntimeException('plugin broke');
        });
    }
    $this->actingAs($this->createAdmin());

    try {
        $this->post(route('escalated.admin.plugins.activate', 'native-life'))->assertStatus(303)->assertSessionHas('success');
        expect(Plugin::where('slug', 'native-life')->value('is_active'))->toBeTrue();
        $this->delete(route('escalated.admin.plugins.destroy', 'native-life'))->assertSessionHas('success');
    } finally {
        File::deleteDirectory($dir);
    }

    $payload = ['plugin' => 'Native Life', 'slug' => 'native-life'];
    expect($seen)->toBe([['plugin.activated', $payload], ['plugin.uninstalling', $payload], ['plugin.deactivated', $payload]])
        ->and(Plugin::where('slug', 'native-life')->exists())->toBeFalse();
});

it('mounts neither of two SDK plugins whose names share a slug, and logs both names', function () {
    Log::spy();
    $registrar = new RouteRegistrar(Mockery::mock(PluginBridge::class), app(EscalatedUiRenderer::class));
    $manifest = ['endpoints' => [['method' => 'GET', 'path' => '/ping']]];

    $registrar->registerAll(['@twin/a' => $manifest, 'twin/a' => $manifest, 'solo' => $manifest]);
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('escalated.plugin.twin--a.endpoint.get.ping'))->toBeFalse()
        ->and(Route::has('escalated.plugin.solo.endpoint.get.ping'))->toBeTrue();
    Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, '@twin/a') && str_contains($message, 'twin/a'));
});

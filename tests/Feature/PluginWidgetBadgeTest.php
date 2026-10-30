<?php

use Escalated\Laravel\Bridge\PluginBridge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
 * domain-model/plugin-contract.md, Badges: a widget with a badge function
 * carries its answer as `badge`, asked as the viewer while
 * `escalated.plugins.dashboardWidgets` is built; a failing badge is null and a
 * widget without one has no `badge` key.
 */

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    Route::get('/login', fn () => 'login')->name('login');
});

function dashboardWidgetsFor($user): array
{
    $widgets = test()->actingAs($user)->withHeaders(['X-Inertia' => 'true'])
        ->get(route('escalated.agent.dashboard'))->assertOk()
        ->json('props.escalated.plugins.dashboardWidgets');

    return collect($widgets)->keyBy('component')->all();
}

it('asks SDK widget badges from the runtime as the viewer', function () {
    $bridge = Mockery::mock(PluginBridge::class);
    $bridge->shouldReceive('isBooted')->andReturn(true);
    $bridge->shouldReceive('getManifests')->andReturn(['@acme/plugin-crm' => ['widgets' => [
        ['component' => 'Inbox', 'label' => 'Inbox', 'order' => 1, 'hasBadge' => true],
        ['component' => 'Count', 'label' => 'Count', 'order' => 2, 'hasBadge' => true],
        ['component' => 'Broken', 'label' => 'Broken', 'order' => 3, 'hasBadge' => true],
        ['component' => 'Plain', 'label' => 'Plain', 'order' => 4, 'hasBadge' => false],
    ]]]);
    $asked = [];
    $bridge->shouldReceive('badge')->andReturnUsing(function (string $plugin, string $component) use (&$asked) {
        $asked[] = [$plugin, $component];

        return match ($component) {
            'Inbox' => 'for '.Auth::id(),
            'Count' => 7,
            default => throw new RuntimeException('badge failed'),
        };
    });
    app()->instance(PluginBridge::class, $bridge);

    $agent = $this->createAgent();
    $widgets = dashboardWidgetsFor($agent);

    expect($widgets['Inbox']['badge'])->toBe('for '.$agent->id)
        ->and($widgets['Count']['badge'])->toBe(7)
        ->and($widgets['Broken'])->toHaveKey('badge')
        ->and($widgets['Broken']['badge'])->toBeNull()
        ->and($widgets['Plain'])->not->toHaveKey('badge')
        ->and($asked)->toBe([
            ['@acme/plugin-crm', 'Inbox'], ['@acme/plugin-crm', 'Count'], ['@acme/plugin-crm', 'Broken'],
        ]);
});

it('does not ask the badge of a widget the viewer cannot see', function () {
    $bridge = Mockery::mock(PluginBridge::class);
    $bridge->shouldReceive('isBooted')->andReturn(true);
    $bridge->shouldReceive('getManifests')->andReturn(['@acme/plugin-crm' => ['widgets' => [
        ['component' => 'Secret', 'capability' => 'nobody-has-this', 'hasBadge' => true],
    ]]]);
    $bridge->shouldNotReceive('badge');
    app()->instance(PluginBridge::class, $bridge);

    expect(dashboardWidgetsFor($this->createAgent()))->toBe([]);
});

it('calls a native widget badge with the viewer', function () {
    escalated_register_dashboard_widget(['id' => 'acme.mine', 'title' => 'Mine', 'component' => 'Mine', 'plugin' => 'acme',
        'badge' => fn ($user) => 'mine:'.$user->id]);
    escalated_register_dashboard_widget(['id' => 'acme.fixed', 'title' => 'Fixed', 'component' => 'Fixed', 'plugin' => 'acme',
        'badge' => 3]);
    escalated_register_dashboard_widget(['id' => 'acme.broken', 'title' => 'Broken', 'component' => 'Broken', 'plugin' => 'acme',
        'badge' => fn () => throw new RuntimeException('nope')]);
    escalated_register_dashboard_widget(['id' => 'acme.plain', 'title' => 'Plain', 'component' => 'Plain', 'plugin' => 'acme']);

    $agent = $this->createAgent();
    $widgets = dashboardWidgetsFor($agent);

    expect($widgets['Mine']['badge'])->toBe('mine:'.$agent->id)
        ->and($widgets['Fixed']['badge'])->toBe(3)
        ->and($widgets['Broken'])->toHaveKey('badge')
        ->and($widgets['Broken']['badge'])->toBeNull()
        ->and($widgets['Plain'])->not->toHaveKey('badge');
});

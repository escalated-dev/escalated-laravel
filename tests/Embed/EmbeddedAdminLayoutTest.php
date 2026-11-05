<?php

use Escalated\Laravel\Database\Seeders\PermissionSeeder;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Http\Middleware\ApplyEmbedContext;
use Escalated\Laravel\Http\Middleware\EnforceRoutePermissions;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Escalated\Laravel\Http\Middleware\RequireTwoFactor;
use Escalated\Laravel\Models\Permission;
use Escalated\Laravel\Models\Role;
use Escalated\Laravel\Services\PluginUIService;
use Escalated\Laravel\Tests\EmbedTestCase;
use Escalated\Laravel\Tests\Fixtures\EmbedHostMiddleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * The admin embedded in a host admin panel (ADR 2026-11-05-embeddable-admin),
 * layout mode: routes under the host's prefix and middleware with the same
 * names, Escalated's admin gate kept, the embed flag and the admin menu shared.
 */
uses(EmbedTestCase::class);

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('mounts the admin under the host prefix with the host middleware', function () {
    $response = $this->actingAs($this->createAdmin())->get('/backoffice/helpdesk/tags');

    $response->assertOk()->assertHeader('X-Host-Admin', '1');
    expect(route('escalated.admin.tags.index', [], false))->toBe('/backoffice/helpdesk/tags');
});

it('no longer serves the standalone admin URLs', function () {
    $this->actingAs($this->createAdmin())->get('/support/admin/tags')->assertNotFound();
});

it('moves every admin route that lived under /support/admin, keeping its name', function () {
    $moved = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'escalated.admin.'))
        ->reject(fn ($route) => str_starts_with((string) $route->getName(), 'escalated.admin.two-factor.'));

    expect($moved)->not->toBeEmpty();
    foreach ($moved as $route) {
        expect($route->uri())->toStartWith('backoffice/helpdesk');
    }

    expect(route('escalated.admin.plugins.index', [], false))->toBe('/backoffice/helpdesk/plugins')
        ->and(route('escalated.admin.api-tokens.index', [], false))->toBe('/backoffice/helpdesk/api-tokens');
});

it('keeps the guards on the embedded routes and adds the embed context last', function () {
    $middleware = Route::getRoutes()->getByName('escalated.admin.tags.index')->gatherMiddleware();

    expect($middleware)->toContain('web', 'auth', EmbedHostMiddleware::class, EnsureIsAdmin::class, RequireTwoFactor::class, EnforceRoutePermissions::class)
        ->and(end($middleware))->toBe(ApplyEmbedContext::class);
});

it('leaves the agent panel, customer pages and two-factor routes where they were', function () {
    expect(route('escalated.agent.dashboard', [], false))->toBe('/support/agent')
        ->and(route('escalated.customer.tickets.index', [], false))->toStartWith('/support')
        ->and(Route::getRoutes()->getByName('escalated.admin.two-factor.index')->uri())->toBe('support/admin/settings/two-factor');
});

it('keeps Escalated\'s admin gate unless the host opts out of it', function () {
    $this->actingAs($this->createAgent())->get('/backoffice/helpdesk/tags')->assertForbidden();
});

it('requires the host middleware\'s authentication', function () {
    $this->getJson('/backoffice/helpdesk/tags')->assertUnauthorized();
});

it('shares the embed flag on embedded pages', function () {
    $this->actingAs($this->createAdmin())
        ->get('/backoffice/helpdesk/tags')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Escalated/Admin/Tags/Index', false)
            ->where('escalated.embed', [
                'enabled' => true,
                'mode' => 'layout',
                'layout' => 'Layouts/AdminLayout',
                'route' => 'escalated.admin.tags.index',
                'base_url' => '/backoffice/helpdesk',
                'allowed_origins' => [],
            ]));
});

it('marks other pages as not embedded but still shares the menu', function () {
    $admin = $this->createAdmin(['is_agent' => true]);

    $this->actingAs($admin)
        ->get('/support/agent')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.embed.enabled', false)
            ->where('escalated.embed.route', null)
            ->has('escalated.navigation.0'));
});

it('adds no framing headers in layout mode', function () {
    $response = $this->actingAs($this->createAdmin())->get('/backoffice/helpdesk/tags');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
});

it('shares the admin menu with the documented item shape, in order, with the active item', function () {
    $this->actingAs($this->createAdmin())
        ->get('/backoffice/helpdesk/tags')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.navigation.0', [
                'key' => 'reports',
                'label' => 'Reports',
                'route' => 'escalated.admin.reports',
                'url' => '/backoffice/helpdesk/reports',
                'icon' => 'reports',
                'position' => 10,
                'active' => false,
                'badge' => null,
                'plugin' => null,
                'children' => [],
            ])
            ->where('escalated.navigation.1.key', 'tickets')
            ->where('escalated.navigation', fn ($items) => collect($items)->firstWhere('active', true)['key'] === 'tags'
                && collect($items)->pluck('position')->all() === collect($items)->pluck('position')->sort()->values()->all()
                && collect($items)->last()['key'] === 'settings'));
});

it('leaves out screens whose feature is off', function () {
    $keys = collect(Escalated::adminNavigation($this->createAdmin()))->pluck('key');

    // Roles & permissions and newsletters are off by default.
    expect($keys)->not->toContain('roles')
        ->and($keys)->not->toContain('newsletters')
        ->and($keys)->toContain('tags', 'settings', 'api-tokens', 'plugins');
});

it('filters the menu by route permission while roles & permissions is on', function () {
    (new PermissionSeeder)->run();
    config(['escalated.access.roles_permissions' => true]);

    $admin = $this->createAdmin();
    $role = Role::create(['name' => 'Tag keeper '.Str::random(4)]);
    $role->permissions()->sync(Permission::whereIn('slug', ['tag.view', 'ticket.view'])->pluck('id')->all());
    $role->users()->attach($admin->getKey());

    $keys = collect(Escalated::adminNavigation($admin))->pluck('key')->all();

    expect($keys)->toContain('tags', 'tickets')
        ->and($keys)->not->toContain('settings')
        ->and($keys)->not->toContain('users')
        ->and($keys)->not->toContain('roles');
});

it('gives no menu to a user who may not use the admin', function () {
    $agent = $this->createAgent();

    expect(Escalated::adminNavigation($agent))->toBe([]);

    $this->actingAs($agent)
        ->get('/support/agent')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('escalated.navigation', []));
});

it('includes plugin admin menu items', function () {
    app(PluginUIService::class)->addMenuItem([
        'label' => 'CRM',
        'url' => '/backoffice/helpdesk/crm',
        'icon' => 'crm',
        'position' => 61,
        'target' => 'admin',
        'plugin' => 'crm',
        'badge' => 3,
    ]);

    $items = collect(Escalated::adminNavigation($this->createAdmin()));
    $crm = $items->firstWhere('label', 'CRM');

    expect($crm)->toMatchArray(['route' => null, 'url' => '/backoffice/helpdesk/crm', 'icon' => 'crm', 'badge' => 3, 'plugin' => 'crm'])
        ->and($items->search(fn ($i) => $i['label'] === 'CRM'))->toBe($items->search(fn ($i) => $i['key'] === 'tags') + 1);
});

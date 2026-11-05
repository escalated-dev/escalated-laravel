<?php

use Escalated\Laravel\Escalated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

/*
 * Embedding the admin in the host's own admin panel (ADR 2026-11-05) is
 * optional. With no embed configuration every Escalated route is exactly what
 * it was before the feature existed (same URI, methods and middleware), the
 * standalone admin renders as before, no embed or navigation props are shared
 * and no framing headers are added.
 *
 * tests/Fixtures/embed/default-routes.json was captured from the route table
 * before embedding was added.
 */
beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function escalatedRouteTable(): array
{
    $routes = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();
        if (! is_string($name) || ! str_starts_with($name, 'escalated.')) {
            continue;
        }
        $routes[$name] = [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            // Closure middleware (plugin routes) has no stable identity; its position does.
            'middleware' => array_map(fn ($m) => is_string($m) ? $m : 'Closure', array_values($route->gatherMiddleware())),
        ];
    }
    ksort($routes);

    return $routes;
}

it('is off by default', function () {
    expect(config('escalated.ui.embed.enabled'))->toBeFalse();
});

it('registers every Escalated route exactly as before', function () {
    $before = json_decode(file_get_contents(__DIR__.'/../Fixtures/embed/default-routes.json'), true);

    expect(escalatedRouteTable())->toBe($before);
});

it('serves the standalone admin at the usual URL with no embed or navigation props', function () {
    $this->actingAs($this->createAdmin())
        ->get('/support/admin/tags')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Escalated/Admin/Tags/Index', false)
            ->missing('escalated.embed')
            ->missing('escalated.navigation')
            ->has('escalated.is_admin'));
});

it('adds no framing headers to the admin', function () {
    $response = $this->actingAs($this->createAdmin())->get('/support/admin/tags');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('keeps the admin gate on the standalone admin', function () {
    $this->actingAs($this->createAgent())->get('/support/admin/tags')->assertForbidden();
});

it('lists the standalone admin from the navigation helper while embedding is off', function () {
    $items = Escalated::adminNavigation($this->createAdmin());

    expect($items[0])->toMatchArray(['key' => 'reports', 'url' => '/support/admin/reports'])
        ->and(collect($items)->pluck('url')->every(fn ($url) => str_starts_with($url, '/support/admin/')))->toBeTrue();
});

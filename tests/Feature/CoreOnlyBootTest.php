<?php

namespace Escalated\Laravel\Tests\Feature;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Tests\TestCase;

class CoreOnlyBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Set ui.enabled=false BEFORE the provider boots.
        // We set the entire escalated.ui array so mergeConfigFrom won't overwrite it.
        $app['config']->set('escalated.ui', ['enabled' => false]);
    }

    public function test_core_boots_without_ui(): void
    {
        // Service provider should boot without errors when UI is disabled
        $this->assertTrue(true);
    }

    public function test_ui_config_defaults_to_enabled(): void
    {
        // This test verifies the config FILE default, not the runtime value.
        // Our test class sets it to false, so we verify the config file
        // has the correct default by reading the raw config array.
        $rawConfig = require __DIR__.'/../../config/escalated.php';
        $this->assertTrue($rawConfig['ui']['enabled']);
    }

    public function test_api_routes_registered_when_ui_disabled(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->pluck('uri')
            ->toArray();

        $this->assertContains('support/api/v1/tickets', $routes);
    }

    public function test_ui_routes_not_registered_when_ui_disabled(): void
    {
        $routeNames = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->toArray();

        // Agent/admin/customer/guest routes should not be present
        $uiRoutes = array_filter($routeNames, function ($name) {
            return str_starts_with($name, 'escalated.agent.')
                || str_starts_with($name, 'escalated.admin.')
                || str_starts_with($name, 'escalated.customer.')
                || str_starts_with($name, 'escalated.guest.');
        });

        $this->assertEmpty($uiRoutes, 'UI routes should not be registered when ui.enabled=false');
    }

    public function test_renderer_throws_when_ui_disabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Escalated UI is disabled');

        app(EscalatedUiRenderer::class);
    }

    public function test_commands_available_when_ui_disabled(): void
    {
        $this->artisan('list')
            ->assertSuccessful();
    }

    public function test_no_direct_inertia_imports_in_controllers(): void
    {
        $controllerPath = __DIR__.'/../../src/Http/Controllers';
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllerPath)
        );

        $violations = [];
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $contents = file_get_contents($file->getPathname());
                if (str_contains($contents, 'use Inertia\Inertia;')
                    || str_contains($contents, 'Inertia::render(')) {
                    $violations[] = str_replace(
                        realpath($controllerPath).DIRECTORY_SEPARATOR,
                        '',
                        $file->getPathname()
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            'Controllers should not import Inertia directly. Violations: '.implode(', ', $violations)
        );
    }
}

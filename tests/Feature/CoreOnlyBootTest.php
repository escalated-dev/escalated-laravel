<?php

namespace Escalated\Laravel\Tests\Feature;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\EscalatedServiceProvider;
use Escalated\Laravel\Tests\TestCase;

class CoreOnlyBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('escalated.ui', ['enabled' => false]);
    }

    public function test_core_boots_without_ui(): void
    {
        $this->assertTrue(true);
    }

    public function test_ui_config_defaults_to_enabled(): void
    {
        $rawConfig = require __DIR__.'/../../config/escalated.php';
        $this->assertTrue($rawConfig['ui']['enabled']);
    }

    public function test_ui_enabled_returns_false_when_config_disabled(): void
    {
        $provider = app()->getProvider(EscalatedServiceProvider::class);
        $reflection = new \ReflectionMethod($provider, 'uiEnabled');

        $this->assertFalse($reflection->invoke($provider));
    }

    public function test_ui_enabled_returns_true_by_default(): void
    {
        // Temporarily restore the default
        config(['escalated.ui.enabled' => true]);

        $provider = app()->getProvider(EscalatedServiceProvider::class);
        $reflection = new \ReflectionMethod($provider, 'uiEnabled');

        $this->assertTrue($reflection->invoke($provider));

        // Restore test state
        config(['escalated.ui.enabled' => false]);
    }

    public function test_api_routes_registered_when_ui_disabled(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->pluck('uri')
            ->toArray();

        $this->assertContains('support/api/v1/tickets', $routes);
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

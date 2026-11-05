<?php

namespace Escalated\Laravel\Tests;

use Escalated\Laravel\Tests\Fixtures\EmbedHostMiddleware;
use Illuminate\Contracts\Http\Kernel as HttpKernel;

/**
 * Boots the package with the admin embedded in a host admin panel
 * (ADR 2026-11-05-embeddable-admin). Routes are registered at boot, so each
 * configuration needs its own base case; subclasses change embedConfig().
 */
abstract class EmbedTestCase extends TestCase
{
    /** @return array<string, mixed> merged over the embed config */
    protected function embedConfig(): array
    {
        return [
            'enabled' => true,
            'mode' => 'layout',
            'prefix' => 'backoffice/helpdesk',
            'middleware' => ['web', 'auth', EmbedHostMiddleware::class],
            'layout' => 'Layouts/AdminLayout',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A host-wide header, to prove iframe mode replaces it only where embedded.
        $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', EmbedHostMiddleware::class.':frame');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach ($this->embedConfig() as $key => $value) {
            $app['config']->set("escalated.ui.embed.{$key}", $value);
        }

        $app['config']->set('view.paths', [__DIR__.'/../resources/views', __DIR__.'/Fixtures/views']);
    }
}

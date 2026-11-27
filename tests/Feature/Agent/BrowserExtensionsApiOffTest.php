<?php

namespace Escalated\Laravel\Tests\Feature\Agent;

use Escalated\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * With the staff API off (the default) there is no browser extensions page,
 * so its URL is not shared and the agent rail shows no link to it.
 */
class BrowserExtensionsApiOffTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('escalated.api.enabled', false);
    }

    public function test_the_page_url_is_not_shared_while_the_api_is_off(): void
    {
        Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);

        $this->assertFalse(Route::has('escalated.agent.extensions.index'));

        $shared = $this->actingAs($this->createAgent())
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->get('/support/agent/tickets')
            ->assertOk()
            ->json('props.escalated');

        $this->assertArrayNotHasKey('browser_extensions_url', $shared);
    }
}

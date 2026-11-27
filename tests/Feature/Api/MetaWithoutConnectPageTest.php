<?php

namespace Escalated\Laravel\Tests\Feature\Api;

use Escalated\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;

/**
 * A headless install (escalated.ui.enabled off) has no connect page, so
 * /meta must not offer auth.exchange and connect_url is null.
 */
class MetaWithoutConnectPageTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('escalated.ui', ['enabled' => false]);
    }

    public function test_meta_omits_auth_exchange_without_the_connect_page(): void
    {
        $this->assertFalse(Route::has('escalated.agent.extension.connect'));

        $response = $this->getJson('/support/api/v1/meta')->assertOk();

        $this->assertNull($response->json('connect_url'));
        $this->assertNotContains('auth.exchange', $response->json('features'));
        $this->assertContains('auth.revoke', $response->json('features'));
    }
}

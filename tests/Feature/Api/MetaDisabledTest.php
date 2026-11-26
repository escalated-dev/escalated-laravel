<?php

namespace Escalated\Laravel\Tests\Feature\Api;

use Escalated\Laravel\Tests\TestCase;

/*
 * Routes are registered at boot, so the config is set before the app boots.
 */
class MetaDisabledTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('escalated.api.enabled', false);
    }

    public function test_meta_is_404_when_the_api_is_disabled(): void
    {
        $this->getJson('/support/api/v1/meta')->assertNotFound();
    }
}

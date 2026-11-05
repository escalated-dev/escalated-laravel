<?php

namespace Escalated\Laravel\Tests;

use Escalated\Laravel\Tests\Fixtures\EmbedAdminHostProvider;

/** A host that calls Escalated::embedAdmin() in its service provider's register(). */
abstract class EmbedApiTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), EmbedAdminHostProvider::class];
    }
}

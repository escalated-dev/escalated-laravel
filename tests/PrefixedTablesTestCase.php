<?php

namespace Escalated\Laravel\Tests;

/**
 * Boots the package with a table prefix other than the default `escalated_`,
 * which `escalated.table_prefix` promises to support.
 *
 * Every migration runs under the prefix before each test, so a migration that
 * names an `escalated_` table outright fails in setUp with "no such table".
 */
abstract class PrefixedTablesTestCase extends TestCase
{
    public const PREFIX = 'helpdesk_';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Pinned to in-memory SQLite whatever ESCALATED_TEST_DRIVER says. A
        // server-backed database is migrated once per run under the default
        // prefix, so a second prefix would find the wrong tables already
        // there. An in-memory database is migrated fresh for every test.
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('escalated.table_prefix', self::PREFIX);
        $app['config']->set('escalated.enable_newsletters', true);
    }
}

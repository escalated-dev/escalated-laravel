<?php

namespace Escalated\Laravel\Tests;

use Escalated\Laravel\Tests\Fixtures\TestUser;

/**
 * Boots the package with its tables on a connection that is NOT the host's
 * default, which is the whole point of `escalated.connection`.
 *
 * Two separate in-memory SQLite databases: `testing` is the host application's
 * (it owns `users`), and `escalated` is where the package's tables go. They
 * share no schema at all, so any query that resolves the wrong connection
 * fails loudly with "no such table" rather than quietly reading the right rows
 * from the wrong database.
 */
abstract class SeparateConnectionTestCase extends TestCase
{
    /**
     * Both connections are wrapped per test. Without the second one the
     * package's writes would leak between tests, because only the default is
     * rolled back.
     *
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['testing', 'escalated'];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.escalated', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('escalated.connection', 'escalated');
    }

    protected function defineDatabaseMigrations(): void
    {
        // The package's migrations follow escalated.connection on their own;
        // the fixture users table is the host's and stays on the default.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/migrations');
    }

    protected function createTestUser(array $attributes = []): TestUser
    {
        return TestUser::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ], $attributes));
    }
}

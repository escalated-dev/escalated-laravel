<?php

namespace Escalated\Laravel\Tests;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\EscalatedServiceProvider;
use Escalated\Laravel\Mail\GuestVerificationCode;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\GuestAccess;
use Escalated\Laravel\Services\GuestEmailVerification;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected array $connectionsToTransact = ['testing'];

    protected function tearDown(): void
    {
        // Testbench registers migration rollback before RefreshDatabase's
        // transaction cleanup. Dispose external-DB fixtures first, so the
        // production guard against dropping assigned tenant data stays intact.
        if ($this->app && Escalated::db()->getDriverName() !== 'sqlite') {
            foreach ($this->connectionsToTransact as $connection) {
                $this->app['db']->connection($connection)->rollBack(0);
            }
        }
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            EscalatedServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', TestDatabase::config());

        if (getenv('ESCALATED_TEST_SEPARATE_CONNECTION') === '1') {
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ]);
            $app['config']->set('database.connections.escalated', [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ]);
            $app['config']->set('escalated.connection', 'escalated');
            $this->connectionsToTransact = ['testing', 'escalated'];
        }

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('view.paths', [__DIR__.'/../resources/views']);
        $app['config']->set('escalated.mode', 'self-hosted');
        $app['config']->set('escalated.user_model', TestUser::class);
        $app['config']->set('escalated.routes.enabled', true);
        $app['config']->set('escalated.inbound_email.enabled', true);
        $app['config']->set('escalated.api.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
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

    protected function guestProof(string $email, string $purpose = 'ticket'): array
    {
        Mail::fake();
        $id = app(GuestEmailVerification::class)->challenge($email, $purpose);
        $mail = Mail::sent(GuestVerificationCode::class)->last();

        return ['verification_id' => $id, 'verification_code' => $mail->code];
    }

    protected function guestToken(Ticket $ticket, string $purpose = 'ticket'): string
    {
        $email = $ticket->guest_email ?? 'guest@example.com';
        $ticket->updateQuietly(['guest_email' => $email, 'guest_verified_email' => $email, 'guest_email_verified_at' => now()]);

        return app(GuestAccess::class)->issue($ticket, $purpose);
    }

    protected function createAgent(array $attributes = []): TestUser
    {
        return $this->createTestUser(array_merge([
            'name' => 'Agent',
            'email' => 'agent@example.com',
            'is_agent' => true,
        ], $attributes));
    }

    protected function createAdmin(array $attributes = []): TestUser
    {
        return $this->createTestUser(array_merge([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ], $attributes));
    }
}

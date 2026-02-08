<?php

namespace Escalated\Laravel;

use Escalated\Laravel\Console\Commands\CheckSlaCommand;
use Escalated\Laravel\Console\Commands\CloseResolvedCommand;
use Escalated\Laravel\Console\Commands\EvaluateEscalationsCommand;
use Escalated\Laravel\Console\Commands\InstallCommand;
use Escalated\Laravel\Console\Commands\PurgeActivitiesCommand;
use Escalated\Laravel\Events;
use Escalated\Laravel\Listeners;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class EscalatedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/escalated.php', 'escalated');

        $this->app->singleton(EscalatedManager::class, function ($app) {
            return new EscalatedManager();
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerEvents();
        $this->shareInertiaData();
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/escalated.php' => config_path('escalated.php'),
        ], 'escalated-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'escalated-migrations');

        // Frontend assets are provided by the @escalated-dev/escalated npm package.
        // Users install via: npm install @escalated-dev/escalated
        // Then resolve pages in their Inertia setup from node_modules.

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/escalated'),
        ], 'escalated-views');
    }

    protected function registerRoutes(): void
    {
        if (! config('escalated.routes.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/agent.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            CheckSlaCommand::class,
            EvaluateEscalationsCommand::class,
            CloseResolvedCommand::class,
            PurgeActivitiesCommand::class,
        ]);
    }

    protected function shareInertiaData(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('escalated', function () {
            $user = $this->app['auth']->user();

            return [
                'prefix' => config('escalated.routes.prefix', 'support'),
                'is_agent' => $user ? Gate::allows('escalated-agent', $user) : false,
                'is_admin' => $user ? Gate::allows('escalated-admin', $user) : false,
            ];
        });
    }

    protected function registerEvents(): void
    {
        Event::listen(Events\TicketCreated::class, [
            Listeners\SendNewTicketNotifications::class,
            Listeners\AutoAssignTicket::class,
            Listeners\AttachSlaPolicy::class,
        ]);

        Event::listen(Events\ReplyCreated::class, [
            Listeners\SendReplyNotifications::class,
            Listeners\RecordFirstResponse::class,
        ]);

        Event::listen(Events\TicketAssigned::class, [
            Listeners\SendAssignmentNotification::class,
        ]);

        Event::listen(Events\TicketStatusChanged::class, [
            Listeners\SendStatusChangeNotification::class,
        ]);

        Event::listen(Events\SlaBreached::class, [
            Listeners\SendSlaBreachNotification::class,
        ]);

        Event::listen(Events\TicketEscalated::class, [
            Listeners\SendEscalationNotification::class,
        ]);
    }
}

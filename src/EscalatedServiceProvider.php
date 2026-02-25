<?php

namespace Escalated\Laravel;

use Escalated\Laravel\Console\Commands\CheckSlaCommand;
use Escalated\Laravel\Console\Commands\CloseResolvedCommand;
use Escalated\Laravel\Console\Commands\EvaluateEscalationsCommand;
use Escalated\Laravel\Console\Commands\InstallCommand;
use Escalated\Laravel\Console\Commands\PluginCommand;
use Escalated\Laravel\Console\Commands\PollImapCommand;
use Escalated\Laravel\Console\Commands\PurgeActivitiesCommand;
use Escalated\Laravel\Events;
use Escalated\Laravel\Listeners;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Services\PluginService;
use Escalated\Laravel\Services\PluginUIService;
use Escalated\Laravel\Support\HookManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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

        // Register hook manager singleton
        $this->app->singleton('escalated.hooks', function ($app) {
            return new HookManager();
        });

        // Register plugin UI service singleton
        $this->app->singleton(PluginUIService::class, function ($app) {
            return new PluginUIService();
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'escalated');

        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerEvents();
        $this->loadPlugins();
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

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/escalated'),
        ], 'escalated-lang');
    }

    protected function registerRoutes(): void
    {
        if (! config('escalated.routes.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/agent.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');

        // REST API routes (token auth, no session)
        if (config('escalated.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            $this->registerApiTokenRoutes();
        }

        // Plugin admin routes
        if (config('escalated.plugins.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/plugins.php');
        }

        // Inbound email webhook routes (no auth required)
        if (config('escalated.inbound_email.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/inbound.php');
        }
    }

    protected function registerApiTokenRoutes(): void
    {
        $middleware = array_merge(
            config('escalated.routes.admin_middleware', ['web', 'auth']),
            [\Escalated\Laravel\Http\Middleware\EnsureIsAdmin::class]
        );

        \Illuminate\Support\Facades\Route::middleware($middleware)
            ->prefix(config('escalated.routes.prefix', 'support').'/admin')
            ->group(function () {
                \Illuminate\Support\Facades\Route::get('/api-tokens', [\Escalated\Laravel\Http\Controllers\Admin\ApiTokenController::class, 'index'])->name('escalated.admin.api-tokens.index');
                \Illuminate\Support\Facades\Route::post('/api-tokens', [\Escalated\Laravel\Http\Controllers\Admin\ApiTokenController::class, 'store'])->name('escalated.admin.api-tokens.store');
                \Illuminate\Support\Facades\Route::patch('/api-tokens/{id}', [\Escalated\Laravel\Http\Controllers\Admin\ApiTokenController::class, 'update'])->name('escalated.admin.api-tokens.update');
                \Illuminate\Support\Facades\Route::delete('/api-tokens/{id}', [\Escalated\Laravel\Http\Controllers\Admin\ApiTokenController::class, 'destroy'])->name('escalated.admin.api-tokens.destroy');
            });
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            PluginCommand::class,
            CheckSlaCommand::class,
            EvaluateEscalationsCommand::class,
            CloseResolvedCommand::class,
            PurgeActivitiesCommand::class,
            PollImapCommand::class,
        ]);
    }

    protected function loadPlugins(): void
    {
        if (! config('escalated.plugins.enabled', true)) {
            return;
        }

        try {
            $pluginService = $this->app->make(PluginService::class);
            $pluginService->loadActivePlugins();
        } catch (\Throwable $e) {
            // Plugins table may not exist yet or directory issues
            \Illuminate\Support\Facades\Log::debug('Escalated: Could not load plugins', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function shareInertiaData(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('escalated', function () {
            $user = $this->app['auth']->user();

            $data = [
                'prefix' => config('escalated.routes.prefix', 'support'),
                'is_agent' => $user ? Gate::allows('escalated-agent', $user) : false,
                'is_admin' => $user ? Gate::allows('escalated-admin', $user) : false,
            ];

            // Share guest tickets setting for frontend (check table exists first)
            try {
                if (Schema::hasTable(Escalated::table('settings'))) {
                    $data['guest_tickets_enabled'] = EscalatedSettings::guestTicketsEnabled();
                }
            } catch (\Throwable) {
                // Settings table may not exist yet
            }

            // Share plugin UI extensions if plugins are enabled
            if (config('escalated.plugins.enabled', true)) {
                try {
                    $pluginUI = $this->app->make(PluginUIService::class);
                    $data['plugin_menu_items'] = $pluginUI->getMenuItems();
                    $data['plugin_dashboard_widgets'] = $pluginUI->getDashboardWidgets();
                    $data['plugin_pages'] = $pluginUI->getCustomPages();
                } catch (\Throwable) {
                    // Plugin UI service may not be available
                }
            }

            return $data;
        });
    }

    protected function registerEvents(): void
    {
        Event::listen(Events\TicketCreated::class, Listeners\SendNewTicketNotifications::class);
        Event::listen(Events\TicketCreated::class, Listeners\AutoAssignTicket::class);
        Event::listen(Events\TicketCreated::class, Listeners\AttachSlaPolicy::class);

        Event::listen(Events\ReplyCreated::class, Listeners\SendReplyNotifications::class);
        Event::listen(Events\ReplyCreated::class, Listeners\RecordFirstResponse::class);

        Event::listen(Events\TicketAssigned::class, Listeners\SendAssignmentNotification::class);

        Event::listen(Events\TicketStatusChanged::class, Listeners\SendStatusChangeNotification::class);

        Event::listen(Events\SlaBreached::class, Listeners\SendSlaBreachNotification::class);

        Event::listen(Events\TicketEscalated::class, Listeners\SendEscalationNotification::class);

        // Webhook dispatch for all events
        $webhookEvents = [
            Events\TicketCreated::class,
            Events\TicketUpdated::class,
            Events\TicketStatusChanged::class,
            Events\TicketResolved::class,
            Events\TicketClosed::class,
            Events\TicketReopened::class,
            Events\TicketAssigned::class,
            Events\TicketUnassigned::class,
            Events\TicketEscalated::class,
            Events\TicketPriorityChanged::class,
            Events\DepartmentChanged::class,
            Events\ReplyCreated::class,
            Events\InternalNoteAdded::class,
            Events\SlaBreached::class,
            Events\SlaWarning::class,
            Events\TagAddedToTicket::class,
            Events\TagRemovedFromTicket::class,
        ];

        foreach ($webhookEvents as $event) {
            Event::listen($event, Listeners\DispatchWebhook::class);
        }
    }
}

<?php

namespace Escalated\Laravel;

use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Console\Commands\CheckSlaCommand;
use Escalated\Laravel\Console\Commands\CleanupAbandonedChatsCommand;
use Escalated\Laravel\Console\Commands\CloseIdleChatsCommand;
use Escalated\Laravel\Console\Commands\CloseResolvedCommand;
use Escalated\Laravel\Console\Commands\EvaluateEscalationsCommand;
use Escalated\Laravel\Console\Commands\ImportCommand;
use Escalated\Laravel\Console\Commands\InstallCommand;
use Escalated\Laravel\Console\Commands\PluginCommand;
use Escalated\Laravel\Console\Commands\PollImapCommand;
use Escalated\Laravel\Console\Commands\ProcessDelayedActionsCommand;
use Escalated\Laravel\Console\Commands\PurgeActivitiesCommand;
use Escalated\Laravel\Console\Commands\PurgeExpiredDataCommand;
use Escalated\Laravel\Console\Commands\RunAutomationsCommand;
use Escalated\Laravel\Console\Commands\WakeSnoozedTicketsCommand;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Http\Controllers\Admin\ApiTokenController;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Services\ImportService;
use Escalated\Laravel\Services\PluginService;
use Escalated\Laravel\Services\PluginUIService;
use Escalated\Laravel\Support\HookManager;
use Escalated\Laravel\UI\InertiaUiRenderer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class EscalatedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/escalated.php', 'escalated');

        $this->app->singleton(EscalatedManager::class, function ($app) {
            return new EscalatedManager;
        });

        // Register hook manager singleton
        $this->app->singleton('escalated.hooks', function ($app) {
            return new HookManager;
        });

        // Register plugin UI service singleton
        $this->app->singleton(PluginUIService::class, function ($app) {
            return new PluginUIService;
        });

        $this->app->singleton(ImportService::class);

        // Register the plugin bridge as a singleton.
        // The bridge manages the Node.js plugin runtime subprocess and is
        // reused for every request in the same PHP-FPM worker.
        $this->app->singleton(PluginBridge::class, function ($app) {
            return new PluginBridge;
        });

        // Register UI renderer — Inertia by default, swappable by the host app
        $this->app->singleton(
            EscalatedUiRenderer::class,
            function () {
                if ($this->uiEnabled()) {
                    return new InertiaUiRenderer;
                }

                throw new \RuntimeException(
                    'Escalated UI is disabled. Set escalated.ui.enabled=true or provide a custom EscalatedUiRenderer binding.'
                );
            }
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'escalated');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'escalated');

        $this->registerPublishing();
        $this->registerCoreRoutes();
        $this->registerCommands();
        $this->registerEvents();
        $this->loadPlugins();
        $this->bootPluginBridge();

        if ($this->uiEnabled()) {
            $this->registerUiRoutes();
            $this->shareInertiaData();
        }
    }

    /**
     * Whether the built-in UI layer is active.
     */
    protected function uiEnabled(): bool
    {
        return config('escalated.ui.enabled', true)
            && class_exists(Inertia::class);
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

    /**
     * Register routes that work without a UI: API, inbound email, plugin endpoints/webhooks.
     */
    protected function registerCoreRoutes(): void
    {
        if (! config('escalated.routes.enabled', true)) {
            return;
        }

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

        // Broadcasting channel authorization routes
        if (config('escalated.broadcasting.enabled', false)) {
            require __DIR__.'/../routes/channels.php';
        }

        // Widget routes (public, rate-limited)
        $this->loadRoutesFrom(__DIR__.'/../routes/widget.php');
    }

    /**
     * Register Inertia-backed web routes (agent, admin, customer, guest).
     */
    protected function registerUiRoutes(): void
    {
        if (! config('escalated.routes.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/agent.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/customer.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
    }

    protected function registerApiTokenRoutes(): void
    {
        $middleware = array_merge(
            config('escalated.routes.admin_middleware', ['web', 'auth']),
            [EnsureIsAdmin::class]
        );

        Route::middleware($middleware)
            ->prefix(config('escalated.routes.prefix', 'support').'/admin')
            ->group(function () {
                Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('escalated.admin.api-tokens.index');
                Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('escalated.admin.api-tokens.store');
                Route::patch('/api-tokens/{id}', [ApiTokenController::class, 'update'])->name('escalated.admin.api-tokens.update');
                Route::delete('/api-tokens/{id}', [ApiTokenController::class, 'destroy'])->name('escalated.admin.api-tokens.destroy');
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
            RunAutomationsCommand::class,
            PurgeExpiredDataCommand::class,
            ImportCommand::class,
            WakeSnoozedTicketsCommand::class,
            CloseIdleChatsCommand::class,
            CleanupAbandonedChatsCommand::class,
            ProcessDelayedActionsCommand::class,
        ]);
    }

    protected function bootPluginBridge(): void
    {
        if (! config('escalated.plugins.enabled', true)) {
            return;
        }

        try {
            $bridge = $this->app->make(PluginBridge::class);
            $bridge->boot();
        } catch (\Throwable $e) {
            // Bridge failures must not break the application.
            Log::warning('Escalated: Plugin bridge boot failed', [
                'error' => $e->getMessage(),
            ]);
        }
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
            Log::debug('Escalated: Could not load plugins', [
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
                    $data['show_powered_by'] = EscalatedSettings::getBool('show_powered_by', true);
                    $data['knowledge_base_enabled'] = EscalatedSettings::knowledgeBaseEnabled();
                    $data['knowledge_base_public'] = EscalatedSettings::knowledgeBasePublic();
                    $data['knowledge_base_feedback_enabled'] = EscalatedSettings::knowledgeBaseFeedbackEnabled();
                }
            } catch (\Throwable) {
                // Settings table may not exist yet
            }

            // Share agent type (full/light) for light agent restrictions
            if ($user) {
                try {
                    if (Schema::hasTable(Escalated::table('agent_profiles'))) {
                        $profile = AgentProfile::where('user_id', $user->getKey())->first();
                        $data['agent_type'] = $profile?->agent_type ?? 'full';
                    }
                } catch (\Throwable) {
                    // Agent profiles table may not exist yet
                }
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

        Event::listen(Events\TicketStatusChanged::class, Listeners\LogTicketStatusChange::class);
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

        // Cancel pending delayed actions when a ticket is resolved or closed
        Event::listen(Events\TicketStatusChanged::class, Listeners\CancelDelayedActionsOnClose::class);

        // Workflow engine for all ticket and chat events
        $workflowEvents = [
            Events\TicketCreated::class,
            Events\TicketUpdated::class,
            Events\ReplyCreated::class,
            Events\TicketStatusChanged::class,
            Events\TicketAssigned::class,
            Events\TicketEscalated::class,
            Events\SlaBreached::class,
            Events\SlaWarning::class,
            Events\ChatStarted::class,
            Events\ChatEnded::class,
        ];

        foreach ($workflowEvents as $event) {
            Event::listen($event, Listeners\ProcessWorkflows::class);
        }
    }
}

<?php

use Escalated\Laravel\Http\Controllers\Admin\ArticleCategoryController;
use Escalated\Laravel\Http\Controllers\Admin\ImportController;
use Escalated\Laravel\Http\Controllers\Admin\ArticleController;
use Escalated\Laravel\Http\Controllers\Admin\AuditLogController;
use Escalated\Laravel\Http\Controllers\Admin\AutomationController;
use Escalated\Laravel\Http\Controllers\Admin\CapacityController;
use Escalated\Laravel\Http\Controllers\Admin\BusinessHoursController;
use Escalated\Laravel\Http\Controllers\Admin\CannedResponseController;
use Escalated\Laravel\Http\Controllers\Admin\CustomObjectController;
use Escalated\Laravel\Http\Controllers\Admin\CsatSettingsController;
use Escalated\Laravel\Http\Controllers\Admin\DataRetentionController;
use Escalated\Laravel\Http\Controllers\Admin\EmailSettingsController;
use Escalated\Laravel\Http\Controllers\Admin\SsoSettingsController;
use Escalated\Laravel\Http\Controllers\Admin\TwoFactorController;
use Escalated\Laravel\Http\Controllers\Admin\CustomFieldController;
use Escalated\Laravel\Http\Controllers\Admin\DepartmentController;
use Escalated\Laravel\Http\Controllers\Admin\EscalationRuleController;
use Escalated\Laravel\Http\Controllers\Admin\MacroController;
use Escalated\Laravel\Http\Controllers\Admin\ReportController;
use Escalated\Laravel\Http\Controllers\Admin\RoleController;
use Escalated\Laravel\Http\Controllers\Admin\SettingsController;
use Escalated\Laravel\Http\Controllers\Admin\SkillController;
use Escalated\Laravel\Http\Controllers\Admin\SlaPolicyController;
use Escalated\Laravel\Http\Controllers\Admin\StatusController;
use Escalated\Laravel\Http\Controllers\Admin\TagController;
use Escalated\Laravel\Http\Controllers\Admin\WebhookController;
use Escalated\Laravel\Http\Controllers\Admin\SideConversationController;
use Escalated\Laravel\Http\Controllers\PresenceController;
use Escalated\Laravel\Http\Controllers\Admin\TicketController;
use Escalated\Laravel\Http\Controllers\Admin\TicketLinkController;
use Escalated\Laravel\Http\Controllers\Admin\TicketMergeController;
use Escalated\Laravel\Http\Controllers\BulkActionController;
use Escalated\Laravel\Http\Controllers\SatisfactionRatingController;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(config('escalated.routes.admin_middleware', ['web', 'auth']), [EnsureIsAdmin::class]))
    ->prefix(config('escalated.routes.prefix', 'support').'/admin')
    ->group(function () {
        Route::get('/reports', ReportController::class)->name('escalated.admin.reports');

        Route::get('/tickets', [TicketController::class, 'index'])->name('escalated.admin.tickets.index');
        Route::post('/tickets/bulk', BulkActionController::class)->name('escalated.admin.tickets.bulk');
        Route::get('/tickets/merge-search', [TicketMergeController::class, 'search'])->name('escalated.admin.tickets.merge-search');

        Route::middleware(ResolveTicketByReference::class)->group(function () {
            Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('escalated.admin.tickets.show');
            Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('escalated.admin.tickets.reply');
            Route::post('/tickets/{ticket}/note', [TicketController::class, 'note'])->name('escalated.admin.tickets.note');
            Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('escalated.admin.tickets.assign');
            Route::post('/tickets/{ticket}/status', [TicketController::class, 'status'])->name('escalated.admin.tickets.status');
            Route::post('/tickets/{ticket}/priority', [TicketController::class, 'priority'])->name('escalated.admin.tickets.priority');
            Route::post('/tickets/{ticket}/tags', [TicketController::class, 'tags'])->name('escalated.admin.tickets.tags');
            Route::post('/tickets/{ticket}/department', [TicketController::class, 'department'])->name('escalated.admin.tickets.department');
            Route::post('/tickets/{ticket}/macro', [TicketController::class, 'applyMacro'])->name('escalated.admin.tickets.macro');
            Route::post('/tickets/{ticket}/follow', [TicketController::class, 'follow'])->name('escalated.admin.tickets.follow');
            Route::post('/tickets/{ticket}/presence', [TicketController::class, 'presence'])->name('escalated.admin.tickets.presence');
            Route::post('/tickets/{ticket}/typing', [PresenceController::class, 'typing'])->name('escalated.admin.tickets.typing');
            Route::post('/tickets/{ticket}/replies/{reply}/pin', [TicketController::class, 'pin'])->name('escalated.admin.tickets.pin');
            Route::post('/tickets/{ticket}/merge', [TicketMergeController::class, 'merge'])->name('escalated.admin.tickets.merge');

            // Ticket Links
            Route::get('/tickets/{ticket}/links', [TicketLinkController::class, 'index'])->name('escalated.admin.tickets.links.index');
            Route::post('/tickets/{ticket}/links', [TicketLinkController::class, 'store'])->name('escalated.admin.tickets.links.store');
            Route::delete('/tickets/{ticket}/links/{link}', [TicketLinkController::class, 'destroy'])->name('escalated.admin.tickets.links.destroy');

            // Side Conversations
            Route::get('/tickets/{ticket}/side-conversations', [SideConversationController::class, 'index'])->name('escalated.admin.tickets.side-conversations.index');
            Route::post('/tickets/{ticket}/side-conversations', [SideConversationController::class, 'store'])->name('escalated.admin.tickets.side-conversations.store');
            Route::post('/tickets/{ticket}/side-conversations/{sideConversation}/reply', [SideConversationController::class, 'reply'])->name('escalated.admin.tickets.side-conversations.reply');
            Route::post('/tickets/{ticket}/side-conversations/{sideConversation}/close', [SideConversationController::class, 'close'])->name('escalated.admin.tickets.side-conversations.close');
        });

        Route::get('/settings', [SettingsController::class, 'index'])->name('escalated.admin.settings');
        Route::post('/settings', [SettingsController::class, 'update'])->name('escalated.admin.settings.update');

        Route::resource('departments', DepartmentController::class)
            ->names('escalated.admin.departments')
            ->except(['show']);

        Route::resource('sla-policies', SlaPolicyController::class)
            ->names('escalated.admin.sla-policies')
            ->except(['show']);

        Route::resource('escalation-rules', EscalationRuleController::class)
            ->names('escalated.admin.escalation-rules')
            ->except(['show']);

        Route::get('/tags', [TagController::class, 'index'])->name('escalated.admin.tags.index');
        Route::post('/tags', [TagController::class, 'store'])->name('escalated.admin.tags.store');
        Route::put('/tags/{tag}', [TagController::class, 'update'])->name('escalated.admin.tags.update');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('escalated.admin.tags.destroy');

        Route::get('/canned-responses', [CannedResponseController::class, 'index'])->name('escalated.admin.canned-responses.index');
        Route::post('/canned-responses', [CannedResponseController::class, 'store'])->name('escalated.admin.canned-responses.store');
        Route::put('/canned-responses/{cannedResponse}', [CannedResponseController::class, 'update'])->name('escalated.admin.canned-responses.update');
        Route::delete('/canned-responses/{cannedResponse}', [CannedResponseController::class, 'destroy'])->name('escalated.admin.canned-responses.destroy');

        Route::get('/macros', [MacroController::class, 'index'])->name('escalated.admin.macros.index');
        Route::post('/macros', [MacroController::class, 'store'])->name('escalated.admin.macros.store');
        Route::put('/macros/{macro}', [MacroController::class, 'update'])->name('escalated.admin.macros.update');
        Route::delete('/macros/{macro}', [MacroController::class, 'destroy'])->name('escalated.admin.macros.destroy');

        // Custom Fields
        Route::resource('custom-fields', CustomFieldController::class)
            ->names('escalated.admin.custom-fields')
            ->except(['show']);
        Route::post('/custom-fields/reorder', [CustomFieldController::class, 'reorder'])
            ->name('escalated.admin.custom-fields.reorder');

        // Statuses
        Route::resource('statuses', StatusController::class)
            ->names('escalated.admin.statuses')
            ->except(['show']);

        // Business Hours
        Route::resource('business-hours', BusinessHoursController::class)
            ->names('escalated.admin.business-hours')
            ->except(['show']);

        // Roles
        Route::resource('roles', RoleController::class)
            ->names('escalated.admin.roles')
            ->except(['show']);

        // Audit Log
        Route::get('/audit-log', [AuditLogController::class, 'index'])
            ->name('escalated.admin.audit-log');

        // Skills
        Route::resource('skills', SkillController::class)
            ->names('escalated.admin.skills')
            ->except(['show']);

        // Agent Capacity
        Route::get('/capacity', [CapacityController::class, 'index'])->name('escalated.admin.capacity.index');
        Route::put('/capacity/{capacity}', [CapacityController::class, 'update'])->name('escalated.admin.capacity.update');

        // Automations
        Route::resource('automations', AutomationController::class)
            ->names('escalated.admin.automations')
            ->except(['show']);

        // Webhooks
        Route::resource('webhooks', WebhookController::class)
            ->names('escalated.admin.webhooks')
            ->except(['show']);
        Route::get('/webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries'])
            ->name('escalated.admin.webhooks.deliveries');
        Route::post('/webhooks/deliveries/{delivery}/retry', [WebhookController::class, 'retry'])
            ->name('escalated.admin.webhooks.retry');

        // Knowledge Base
        Route::resource('kb-articles', ArticleController::class)
            ->names('escalated.admin.kb-articles')
            ->except(['show']);
        Route::resource('kb-categories', ArticleCategoryController::class)
            ->names('escalated.admin.kb-categories')
            ->except(['show', 'create', 'edit']);

        // Reports sub-pages
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard'])->name('escalated.admin.reports.dashboard');
        Route::get('/reports/agents', [ReportController::class, 'agents'])->name('escalated.admin.reports.agents');
        Route::get('/reports/sla', [ReportController::class, 'sla'])->name('escalated.admin.reports.sla');
        Route::get('/reports/csat', [ReportController::class, 'csat'])->name('escalated.admin.reports.csat');

        // CSAT Settings
        Route::get('/settings/csat', [CsatSettingsController::class, 'index'])->name('escalated.admin.settings.csat');
        Route::post('/settings/csat', [CsatSettingsController::class, 'update'])->name('escalated.admin.settings.csat.update');

        // SSO Settings
        Route::get('/settings/sso', [SsoSettingsController::class, 'index'])->name('escalated.admin.settings.sso');
        Route::post('/settings/sso', [SsoSettingsController::class, 'update'])->name('escalated.admin.settings.sso.update');

        // Two-Factor Authentication
        Route::get('/settings/two-factor', [TwoFactorController::class, 'index'])->name('escalated.admin.two-factor.index');
        Route::post('/settings/two-factor/setup', [TwoFactorController::class, 'setup'])->name('escalated.admin.two-factor.setup');
        Route::post('/settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('escalated.admin.two-factor.confirm');
        Route::post('/settings/two-factor/disable', [TwoFactorController::class, 'disable'])->name('escalated.admin.two-factor.disable');

        // Data Retention
        Route::get('/settings/data-retention', [DataRetentionController::class, 'index'])->name('escalated.admin.settings.data-retention');
        Route::post('/settings/data-retention', [DataRetentionController::class, 'update'])->name('escalated.admin.settings.data-retention.update');

        // Email Channel Settings
        Route::get('/settings/email', [EmailSettingsController::class, 'index'])->name('escalated.admin.settings.email');
        Route::post('/settings/email', [EmailSettingsController::class, 'update'])->name('escalated.admin.settings.email.update');

        // Custom Objects
        Route::resource('custom-objects', CustomObjectController::class)
            ->names('escalated.admin.custom-objects')
            ->except(['show']);
        Route::get('/custom-objects/{customObject}/records', [CustomObjectController::class, 'records'])->name('escalated.admin.custom-objects.records');
        Route::post('/custom-objects/{customObject}/records', [CustomObjectController::class, 'storeRecord'])->name('escalated.admin.custom-objects.records.store');
        Route::put('/custom-objects/{customObject}/records/{record}', [CustomObjectController::class, 'updateRecord'])->name('escalated.admin.custom-objects.records.update');
        Route::delete('/custom-objects/{customObject}/records/{record}', [CustomObjectController::class, 'destroyRecord'])->name('escalated.admin.custom-objects.records.destroy');

        // Import (admin-only — inherits EnsureIsAdmin middleware from parent group)
        Route::prefix('import')->name('escalated.admin.import.')->group(function () {
            Route::get('/', [ImportController::class, 'index'])->name('index');
            Route::get('/{platform}/connect', [ImportController::class, 'connect'])->name('connect');
            Route::post('/{platform}/test-connection', [ImportController::class, 'testConnection'])->name('test-connection');
            Route::get('/{platform}/mapping', [ImportController::class, 'mapping'])->name('mapping');
            Route::post('/{platform}/review', [ImportController::class, 'review'])->name('review');
            Route::post('/{platform}/start', [ImportController::class, 'start'])->name('start');
            Route::get('/progress/{jobId}', [ImportController::class, 'progress'])->name('progress');
            Route::get('/status/{jobId}', [ImportController::class, 'status'])->name('status');
            Route::post('/pause/{jobId}', [ImportController::class, 'pause'])->name('pause');
            Route::post('/resume/{jobId}', [ImportController::class, 'resume'])->name('resume');
            Route::post('/cancel/{jobId}', [ImportController::class, 'cancel'])->name('cancel');
        });
    });

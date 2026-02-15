<?php

use Escalated\Laravel\Http\Controllers\Admin\CannedResponseController;
use Escalated\Laravel\Http\Controllers\Admin\DepartmentController;
use Escalated\Laravel\Http\Controllers\Admin\EscalationRuleController;
use Escalated\Laravel\Http\Controllers\Admin\MacroController;
use Escalated\Laravel\Http\Controllers\Admin\ReportController;
use Escalated\Laravel\Http\Controllers\Admin\SettingsController;
use Escalated\Laravel\Http\Controllers\Admin\SlaPolicyController;
use Escalated\Laravel\Http\Controllers\Admin\TagController;
use Escalated\Laravel\Http\Controllers\Admin\TicketController;
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
            Route::post('/tickets/{ticket}/replies/{reply}/pin', [TicketController::class, 'pin'])->name('escalated.admin.tickets.pin');
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
    });

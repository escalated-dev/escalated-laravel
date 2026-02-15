<?php

use Escalated\Laravel\Http\Controllers\Agent\DashboardController;
use Escalated\Laravel\Http\Controllers\Agent\TicketController;
use Escalated\Laravel\Http\Controllers\BulkActionController;
use Escalated\Laravel\Http\Middleware\EnsureIsAgent;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(config('escalated.routes.admin_middleware', ['web', 'auth']), [EnsureIsAgent::class]))
    ->prefix(config('escalated.routes.prefix', 'support').'/agent')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('escalated.agent.dashboard');
        Route::get('/tickets', [TicketController::class, 'index'])->name('escalated.agent.tickets.index');
        Route::post('/tickets/bulk', BulkActionController::class)->name('escalated.agent.tickets.bulk');

        Route::middleware(ResolveTicketByReference::class)->group(function () {
            Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('escalated.agent.tickets.show');
            Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('escalated.agent.tickets.update');
            Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('escalated.agent.tickets.reply');
            Route::post('/tickets/{ticket}/note', [TicketController::class, 'note'])->name('escalated.agent.tickets.note');
            Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('escalated.agent.tickets.assign');
            Route::post('/tickets/{ticket}/status', [TicketController::class, 'status'])->name('escalated.agent.tickets.status');
            Route::post('/tickets/{ticket}/priority', [TicketController::class, 'priority'])->name('escalated.agent.tickets.priority');
            Route::post('/tickets/{ticket}/tags', [TicketController::class, 'tags'])->name('escalated.agent.tickets.tags');
            Route::post('/tickets/{ticket}/department', [TicketController::class, 'department'])->name('escalated.agent.tickets.department');
            Route::post('/tickets/{ticket}/macro', [TicketController::class, 'applyMacro'])->name('escalated.agent.tickets.macro');
            Route::post('/tickets/{ticket}/follow', [TicketController::class, 'follow'])->name('escalated.agent.tickets.follow');
            Route::post('/tickets/{ticket}/presence', [TicketController::class, 'presence'])->name('escalated.agent.tickets.presence');
            Route::post('/tickets/{ticket}/replies/{reply}/pin', [TicketController::class, 'pin'])->name('escalated.agent.tickets.pin');
        });
    });

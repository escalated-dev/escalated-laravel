<?php

use Escalated\Laravel\Http\Controllers\Api\AuthController;
use Escalated\Laravel\Http\Controllers\Api\DashboardController;
use Escalated\Laravel\Http\Controllers\Api\ReportController;
use Escalated\Laravel\Http\Controllers\Api\ResourceController;
use Escalated\Laravel\Http\Controllers\Api\TicketController;
use Escalated\Laravel\Http\Middleware\ApiRateLimit;
use Escalated\Laravel\Http\Middleware\AuthenticateApiToken;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

// Agent-level routes (require 'agent' ability)
Route::middleware([AuthenticateApiToken::class.':agent', ApiRateLimit::class])
    ->prefix(config('escalated.api.prefix', 'support/api/v1'))
    ->group(function () {
        Route::post('/auth/validate', [AuthController::class, 'validate'])->name('escalated.api.auth.validate');

        Route::get('/dashboard', DashboardController::class)->name('escalated.api.dashboard');

        Route::get('/tickets', [TicketController::class, 'index'])->name('escalated.api.tickets.index');
        Route::post('/tickets', [TicketController::class, 'store'])->name('escalated.api.tickets.store');

        Route::middleware(ResolveTicketByReference::class)->group(function () {
            Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('escalated.api.tickets.show');
            Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('escalated.api.tickets.reply');
            Route::patch('/tickets/{ticket}/status', [TicketController::class, 'status'])->name('escalated.api.tickets.status');
            Route::patch('/tickets/{ticket}/priority', [TicketController::class, 'priority'])->name('escalated.api.tickets.priority');
            Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('escalated.api.tickets.assign');
            Route::post('/tickets/{ticket}/follow', [TicketController::class, 'follow'])->name('escalated.api.tickets.follow');
            Route::post('/tickets/{ticket}/macro', [TicketController::class, 'applyMacro'])->name('escalated.api.tickets.macro');
            Route::post('/tickets/{ticket}/tags', [TicketController::class, 'tags'])->name('escalated.api.tickets.tags');
        });

        Route::get('/agents', [ResourceController::class, 'agents'])->name('escalated.api.agents');
        Route::get('/departments', [ResourceController::class, 'departments'])->name('escalated.api.departments');
        Route::get('/tags', [ResourceController::class, 'tags'])->name('escalated.api.tags');
        Route::get('/canned-responses', [ResourceController::class, 'cannedResponses'])->name('escalated.api.canned-responses');
        Route::get('/macros', [ResourceController::class, 'macros'])->name('escalated.api.macros');

        Route::get('/realtime/config', [ResourceController::class, 'realtimeConfig'])->name('escalated.api.realtime');
    });

// Admin-level routes (require 'admin' ability)
Route::middleware([AuthenticateApiToken::class.':admin', ApiRateLimit::class])
    ->prefix(config('escalated.api.prefix', 'support/api/v1'))
    ->group(function () {
        Route::middleware(ResolveTicketByReference::class)->group(function () {
            Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('escalated.api.tickets.destroy');
        });

        // Report API endpoints
        Route::get('/reports/summary', [ReportController::class, 'summary'])->name('escalated.api.reports.summary');
        Route::get('/reports/export/{type}', [ReportController::class, 'export'])->name('escalated.api.reports.export');
    });

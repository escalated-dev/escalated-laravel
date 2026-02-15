<?php

use Escalated\Laravel\Http\Controllers\Api\ApiAuthController;
use Escalated\Laravel\Http\Controllers\Api\ApiDashboardController;
use Escalated\Laravel\Http\Controllers\Api\ApiResourceController;
use Escalated\Laravel\Http\Controllers\Api\ApiTicketController;
use Escalated\Laravel\Http\Middleware\ApiRateLimit;
use Escalated\Laravel\Http\Middleware\AuthenticateApiToken;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

// Agent-level routes (require 'agent' ability)
Route::middleware([AuthenticateApiToken::class.':agent', ApiRateLimit::class])
    ->prefix(config('escalated.api.prefix', 'support/api/v1'))
    ->group(function () {
        Route::post('/auth/validate', [ApiAuthController::class, 'validate'])->name('escalated.api.auth.validate');

        Route::get('/dashboard', ApiDashboardController::class)->name('escalated.api.dashboard');

        Route::get('/tickets', [ApiTicketController::class, 'index'])->name('escalated.api.tickets.index');
        Route::post('/tickets', [ApiTicketController::class, 'store'])->name('escalated.api.tickets.store');

        Route::middleware(ResolveTicketByReference::class)->group(function () {
            Route::get('/tickets/{ticket}', [ApiTicketController::class, 'show'])->name('escalated.api.tickets.show');
            Route::post('/tickets/{ticket}/reply', [ApiTicketController::class, 'reply'])->name('escalated.api.tickets.reply');
            Route::patch('/tickets/{ticket}/status', [ApiTicketController::class, 'status'])->name('escalated.api.tickets.status');
            Route::patch('/tickets/{ticket}/priority', [ApiTicketController::class, 'priority'])->name('escalated.api.tickets.priority');
            Route::post('/tickets/{ticket}/assign', [ApiTicketController::class, 'assign'])->name('escalated.api.tickets.assign');
            Route::post('/tickets/{ticket}/follow', [ApiTicketController::class, 'follow'])->name('escalated.api.tickets.follow');
            Route::post('/tickets/{ticket}/macro', [ApiTicketController::class, 'applyMacro'])->name('escalated.api.tickets.macro');
            Route::post('/tickets/{ticket}/tags', [ApiTicketController::class, 'tags'])->name('escalated.api.tickets.tags');
        });

        Route::get('/agents', [ApiResourceController::class, 'agents'])->name('escalated.api.agents');
        Route::get('/departments', [ApiResourceController::class, 'departments'])->name('escalated.api.departments');
        Route::get('/tags', [ApiResourceController::class, 'tags'])->name('escalated.api.tags');
        Route::get('/canned-responses', [ApiResourceController::class, 'cannedResponses'])->name('escalated.api.canned-responses');
        Route::get('/macros', [ApiResourceController::class, 'macros'])->name('escalated.api.macros');

        Route::get('/realtime/config', [ApiResourceController::class, 'realtimeConfig'])->name('escalated.api.realtime');
    });

// Admin-level routes (require 'admin' ability)
Route::middleware([AuthenticateApiToken::class.':admin', ApiRateLimit::class])
    ->prefix(config('escalated.api.prefix', 'support/api/v1'))
    ->group(function () {
        Route::middleware(ResolveTicketByReference::class)->group(function () {
            Route::delete('/tickets/{ticket}', [ApiTicketController::class, 'destroy'])->name('escalated.api.tickets.destroy');
        });
    });

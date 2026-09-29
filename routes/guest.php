<?php

use Escalated\Laravel\Http\Controllers\Guest\TicketController;
use Escalated\Laravel\Http\Controllers\Guest\VerificationController;
use Escalated\Laravel\Http\Controllers\SatisfactionRatingController;
use Escalated\Laravel\Http\Middleware\GuestPrivacyHeaders;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', GuestPrivacyHeaders::class, 'throttle:escalated-guest-requests'])
    ->prefix(config('escalated.routes.prefix', 'support').'/guest')
    ->group(function () {
        Route::post('/verification', [VerificationController::class, 'store'])->middleware('throttle:escalated-guest-submissions')->name('escalated.guest.verification');
        Route::post('/lookup', [VerificationController::class, 'lookup'])->middleware('throttle:escalated-guest-replies')->name('escalated.guest.lookup');
        Route::get('/create', [TicketController::class, 'create'])->name('escalated.guest.tickets.create');
        Route::post('/', [TicketController::class, 'store'])->middleware('throttle:escalated-guest-submissions')->name('escalated.guest.tickets.store');
        Route::get('/{token}', [TicketController::class, 'show'])
            ->where('token', '[A-Za-z0-9_-]{1,4096}')
            ->name('escalated.guest.tickets.show');
        Route::post('/{token}/reply', [TicketController::class, 'reply'])
            ->middleware('throttle:escalated-guest-replies')
            ->where('token', '[A-Za-z0-9_-]{1,4096}')
            ->name('escalated.guest.tickets.reply');
        Route::post('/{token}/rate', [SatisfactionRatingController::class, 'storeGuest'])
            ->middleware('throttle:escalated-guest-replies')
            ->where('token', '[A-Za-z0-9_-]{1,4096}')
            ->name('escalated.guest.tickets.rate');
    });

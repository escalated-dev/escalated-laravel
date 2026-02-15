<?php

use Escalated\Laravel\Http\Controllers\Customer\TicketController;
use Escalated\Laravel\Http\Controllers\SatisfactionRatingController;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

Route::middleware(config('escalated.routes.middleware', ['web', 'auth']))
    ->prefix(config('escalated.routes.prefix', 'support'))
    ->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('escalated.customer.tickets.index');
        Route::get('/create', [TicketController::class, 'create'])->name('escalated.customer.tickets.create');
        Route::post('/', [TicketController::class, 'store'])->name('escalated.customer.tickets.store');

        Route::middleware(ResolveTicketByReference::class)
            ->where(['ticket' => '[A-Z]+-[0-9]+|[0-9]+'])
            ->group(function () {
                Route::get('/{ticket}', [TicketController::class, 'show'])->name('escalated.customer.tickets.show');
                Route::post('/{ticket}/reply', [TicketController::class, 'reply'])->name('escalated.customer.tickets.reply');
                Route::post('/{ticket}/close', [TicketController::class, 'close'])->name('escalated.customer.tickets.close');
                Route::post('/{ticket}/reopen', [TicketController::class, 'reopen'])->name('escalated.customer.tickets.reopen');
                Route::post('/{ticket}/rate', [SatisfactionRatingController::class, 'store'])->name('escalated.customer.tickets.rate');
            });
    });

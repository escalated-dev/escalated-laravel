<?php

use Escalated\Laravel\Http\Controllers\Customer\KnowledgeBaseController;
use Escalated\Laravel\Http\Controllers\Customer\TicketController;
use Escalated\Laravel\Http\Controllers\SatisfactionRatingController;
use Escalated\Laravel\Http\Middleware\RecordCustomerPresence;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

Route::middleware([...config('escalated.routes.middleware', ['web', 'auth']), RecordCustomerPresence::class])
    ->prefix(config('escalated.routes.prefix', 'support'))
    ->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('escalated.customer.tickets.index');
        Route::get('/create', [TicketController::class, 'create'])->name('escalated.customer.tickets.create');
        Route::post('/', [TicketController::class, 'store'])->name('escalated.customer.tickets.store');

        // Knowledge Base
        Route::get('/kb', [KnowledgeBaseController::class, 'index'])->name('escalated.customer.kb.index');
        Route::get('/kb/{slug}', [KnowledgeBaseController::class, 'show'])->name('escalated.customer.kb.show');
        Route::post('/kb/{slug}/feedback', [KnowledgeBaseController::class, 'feedback'])->name('escalated.customer.kb.feedback');

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

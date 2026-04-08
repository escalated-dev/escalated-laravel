<?php

use Escalated\Laravel\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:60,1'])
    ->prefix(config('escalated.routes.prefix', 'support').'/widget')
    ->group(function () {
        Route::get('/config', [WidgetController::class, 'config'])->name('escalated.widget.config');
        Route::get('/articles', [WidgetController::class, 'searchArticles'])->name('escalated.widget.articles.search');
        Route::get('/articles/{slug}', [WidgetController::class, 'showArticle'])->name('escalated.widget.articles.show');
        Route::post('/tickets', [WidgetController::class, 'createTicket'])->name('escalated.widget.tickets.store');
        Route::get('/tickets/{reference}', [WidgetController::class, 'ticketStatus'])->name('escalated.widget.tickets.status');
    });

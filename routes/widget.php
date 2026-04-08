<?php

use Escalated\Laravel\Http\Controllers\WidgetChatController;
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

        // Live Chat
        Route::get('/chat/availability', [WidgetChatController::class, 'availability'])->name('escalated.widget.chat.availability');
        Route::post('/chat/start', [WidgetChatController::class, 'start'])->name('escalated.widget.chat.start');
        Route::post('/chat/{sessionId}/message', [WidgetChatController::class, 'message'])->name('escalated.widget.chat.message');
        Route::post('/chat/{sessionId}/typing', [WidgetChatController::class, 'typing'])->name('escalated.widget.chat.typing');
        Route::post('/chat/{sessionId}/end', [WidgetChatController::class, 'end'])->name('escalated.widget.chat.end');
        Route::post('/chat/{sessionId}/rate', [WidgetChatController::class, 'rate'])->name('escalated.widget.chat.rate');
    });

<?php

use Escalated\Laravel\Http\Controllers\Guest\VerificationController;
use Escalated\Laravel\Http\Controllers\WidgetChatController;
use Escalated\Laravel\Http\Controllers\WidgetController;
use Escalated\Laravel\Http\Controllers\WidgetScriptController;
use Escalated\Laravel\Http\Middleware\EnsureFeatureEnabled;
use Escalated\Laravel\Http\Middleware\GuestPrivacyHeaders;
use Escalated\Laravel\Http\Middleware\WidgetCors;
use Escalated\Laravel\Http\Middleware\WidgetCsrf;
use Illuminate\Support\Facades\Route;

$widgetPrefix = config('escalated.routes.prefix', 'support').'/widget';

// The standalone widget bundle, loaded with a <script> tag on any site.
if (config('escalated.widget.serve_script', true)) {
    Route::get($widgetPrefix.'/embed.js', WidgetScriptController::class)->name('escalated.widget.script');
}

// WidgetCors runs first so CORS headers reach every answer, including 419,
// 422 and 429. WidgetCsrf replaces the host's CSRF middleware: requests
// from allowed embedding sites skip it, every other request still runs it.
Route::middleware([WidgetCors::class, 'web', WidgetCsrf::class, GuestPrivacyHeaders::class, 'throttle:escalated-guest-requests'])
    ->withoutMiddleware(WidgetCsrf::CSRF_MIDDLEWARE)
    ->prefix($widgetPrefix)
    ->group(function () {
        // Preflight requests are answered by WidgetCors; this route only
        // makes sure OPTIONS reaches it instead of the router's default.
        Route::options('/{path}', fn () => response()->noContent())->where('path', '.*')->name('escalated.widget.preflight');
        // Verification and lookup serve both tickets and chat; the controller
        // answers 404 when the purpose's feature is off.
        Route::post('/verification', [VerificationController::class, 'store'])->middleware('throttle:escalated-guest-submissions')->name('escalated.widget.verification');
        Route::post('/lookup', [VerificationController::class, 'lookup'])->middleware('throttle:escalated-guest-replies')->name('escalated.widget.lookup');

        Route::middleware(EnsureFeatureEnabled::class.':widget')->group(function () {
            Route::get('/config', [WidgetController::class, 'config'])->name('escalated.widget.config');
            Route::get('/articles', [WidgetController::class, 'searchArticles'])->name('escalated.widget.articles.search');
            Route::get('/articles/{slug}', [WidgetController::class, 'showArticle'])->name('escalated.widget.articles.show');
            Route::post('/tickets', [WidgetController::class, 'createTicket'])->middleware('throttle:escalated-guest-submissions')->name('escalated.widget.tickets.store');
            Route::get('/tickets/{reference}', [WidgetController::class, 'ticketStatus'])->name('escalated.widget.tickets.status');
        });

        // Live Chat. Availability always answers so the widget can hide the
        // chat tab; the rest answers 404 while chat_enabled is off.
        Route::get('/chat/availability', [WidgetChatController::class, 'availability'])->name('escalated.widget.chat.availability');
        Route::middleware(EnsureFeatureEnabled::class.':chat')->group(function () {
            Route::post('/chat/start', [WidgetChatController::class, 'start'])->middleware('throttle:escalated-guest-submissions')->name('escalated.widget.chat.start');
            Route::post('/chat/{sessionId}/message', [WidgetChatController::class, 'message'])->middleware('throttle:escalated-guest-replies')->name('escalated.widget.chat.message');
            Route::post('/chat/{sessionId}/messages', [WidgetChatController::class, 'message'])->middleware('throttle:escalated-guest-replies')->name('escalated.widget.chat.messages.store');
            Route::get('/chat/{sessionId}/messages', [WidgetChatController::class, 'messages'])->name('escalated.widget.chat.messages');
            Route::post('/chat/{sessionId}/typing', [WidgetChatController::class, 'typing'])->name('escalated.widget.chat.typing');
            Route::post('/chat/{sessionId}/end', [WidgetChatController::class, 'end'])->name('escalated.widget.chat.end');
            Route::post('/chat/{sessionId}/rate', [WidgetChatController::class, 'rate'])->middleware('throttle:escalated-guest-replies')->name('escalated.widget.chat.rate');
        });
    });

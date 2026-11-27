<?php

use Escalated\Laravel\Http\Controllers\Agent\BrowserExtensionController;
use Escalated\Laravel\Http\Controllers\Agent\ChatController;
use Escalated\Laravel\Http\Controllers\Agent\DashboardController;
use Escalated\Laravel\Http\Controllers\Agent\KnowledgeBaseSearchController;
use Escalated\Laravel\Http\Controllers\Agent\NotificationController;
use Escalated\Laravel\Http\Controllers\Agent\StatusController;
use Escalated\Laravel\Http\Controllers\Agent\TicketController;
use Escalated\Laravel\Http\Controllers\BulkActionController;
use Escalated\Laravel\Http\Controllers\PresenceController;
use Escalated\Laravel\Http\Middleware\DenyFraming;
use Escalated\Laravel\Http\Middleware\EnforceRoutePermissions;
use Escalated\Laravel\Http\Middleware\EnsureIsAgent;
use Escalated\Laravel\Http\Middleware\RequireTwoFactor;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(config('escalated.routes.admin_middleware', ['web', 'auth']), [EnsureIsAgent::class, RequireTwoFactor::class, EnforceRoutePermissions::class]))
    ->prefix(config('escalated.routes.prefix', 'support').'/agent')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('escalated.agent.dashboard');
        Route::match(['put', 'post'], '/status', StatusController::class)->name('escalated.agent.status');
        Route::get('/kb/search', KnowledgeBaseSearchController::class)->name('escalated.agent.kb.search');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('escalated.agent.notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('escalated.agent.notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('escalated.agent.notifications.read');
        Route::get('/tickets', [TicketController::class, 'index'])->name('escalated.agent.tickets.index');
        Route::post('/tickets/bulk', BulkActionController::class)->name('escalated.agent.tickets.bulk');

        // Live chat. The same actions as escalated.admin.chat.*, limited to
        // the chats the agent may run (see Agent\ChatController).
        Route::get('/chat/active', [ChatController::class, 'index'])->name('escalated.agent.chat.active');
        Route::get('/chat/queue', [ChatController::class, 'queue'])->name('escalated.agent.chat.queue');
        Route::post('/chat/{session}/accept', [ChatController::class, 'accept'])->name('escalated.agent.chat.accept');
        Route::post('/chat/{session}/end', [ChatController::class, 'end'])->name('escalated.agent.chat.end');
        Route::post('/chat/{session}/transfer', [ChatController::class, 'transfer'])->name('escalated.agent.chat.transfer');
        Route::post('/chat/{session}/message', [ChatController::class, 'message'])->name('escalated.agent.chat.message');
        Route::post('/chat/{session}/typing', [ChatController::class, 'typing'])->name('escalated.agent.chat.typing');

        // Browser extension sign-in (staff API contract v1), only while the
        // API that trades its codes for tokens is on. DenyFraming also
        // decorates the answers given before route middleware runs (the
        // sign-in redirect), see DenyFraming::decorateEarlyResponse.
        if (config('escalated.api.enabled', false)) {
            Route::middleware(DenyFraming::class)->group(function () {
                Route::get('/connect-extension', [BrowserExtensionController::class, 'show'])->name('escalated.agent.extension.connect');
                Route::post('/connect-extension/approve', [BrowserExtensionController::class, 'approve'])->name('escalated.agent.extension.approve');
                Route::post('/connect-extension/deny', [BrowserExtensionController::class, 'deny'])->name('escalated.agent.extension.deny');
            });
            Route::get('/browser-extensions', [BrowserExtensionController::class, 'index'])->name('escalated.agent.extensions.index');
            Route::delete('/browser-extensions/{token}', [BrowserExtensionController::class, 'destroy'])
                ->whereNumber('token')
                ->name('escalated.agent.extensions.destroy');
        }

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
            Route::post('/tickets/{ticket}/actions/{action}', [TicketController::class, 'customAction'])->name('escalated.agent.tickets.custom-action');
            Route::post('/tickets/{ticket}/snooze', [TicketController::class, 'snooze'])->name('escalated.agent.tickets.snooze');
            Route::post('/tickets/{ticket}/unsnooze', [TicketController::class, 'unsnooze'])->name('escalated.agent.tickets.unsnooze');
            Route::post('/tickets/{ticket}/follow', [TicketController::class, 'follow'])->name('escalated.agent.tickets.follow');
            Route::post('/tickets/{ticket}/read', [TicketController::class, 'markRead'])->name('escalated.agent.tickets.read');
            Route::post('/tickets/{ticket}/unread', [TicketController::class, 'markUnread'])->name('escalated.agent.tickets.unread');
            Route::post('/tickets/{ticket}/presence', [TicketController::class, 'presence'])->name('escalated.agent.tickets.presence');
            Route::post('/tickets/{ticket}/replies/{reply}/pin', [TicketController::class, 'pin'])->name('escalated.agent.tickets.pin');
            Route::post('/tickets/{ticket}/typing', [PresenceController::class, 'typing'])->name('escalated.agent.tickets.typing');
        });
    });

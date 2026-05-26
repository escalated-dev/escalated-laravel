<?php

use Escalated\Laravel\Http\Controllers\Api\MobileAuthController;
use Escalated\Laravel\Http\Controllers\Api\MobileGuestTicketController;
use Escalated\Laravel\Http\Controllers\Api\MobileKnowledgeBaseController;
use Escalated\Laravel\Http\Controllers\Api\MobileResourceController;
use Escalated\Laravel\Http\Controllers\Api\MobileTicketController;
use Escalated\Laravel\Http\Middleware\ApiRateLimit;
use Escalated\Laravel\Http\Middleware\AuthenticateApiToken;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

Route::prefix(config('escalated.api.mobile_prefix', 'support/api/v1/mobile'))
    ->middleware(ApiRateLimit::class)
    ->group(function () {
        Route::post('/auth/login', [MobileAuthController::class, 'login'])->name('escalated.api.mobile.auth.login');
        Route::post('/auth/register', [MobileAuthController::class, 'register'])->name('escalated.api.mobile.auth.register');

        Route::get('/departments', [MobileResourceController::class, 'departments'])->name('escalated.api.mobile.departments');

        Route::get('/kb/articles', [MobileKnowledgeBaseController::class, 'index'])->name('escalated.api.mobile.kb.index');
        Route::get('/kb/articles/{slug}', [MobileKnowledgeBaseController::class, 'show'])->name('escalated.api.mobile.kb.show');
        Route::post('/kb/articles/{slug}/rate', [MobileKnowledgeBaseController::class, 'rate'])->name('escalated.api.mobile.kb.rate');
        Route::get('/kb/categories', [MobileKnowledgeBaseController::class, 'categories'])->name('escalated.api.mobile.kb.categories');

        Route::post('/guest/tickets', [MobileGuestTicketController::class, 'store'])->name('escalated.api.mobile.guest.tickets.store');
        Route::get('/guest/tickets/{token}', [MobileGuestTicketController::class, 'show'])->name('escalated.api.mobile.guest.tickets.show');
        Route::post('/guest/tickets/{token}/replies', [MobileGuestTicketController::class, 'reply'])->name('escalated.api.mobile.guest.tickets.reply');

        Route::middleware(AuthenticateApiToken::class.':customer')->group(function () {
            Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('escalated.api.mobile.auth.logout');
            Route::post('/auth/refresh', [MobileAuthController::class, 'refresh'])->name('escalated.api.mobile.auth.refresh');
            Route::post('/auth/validate', [MobileAuthController::class, 'validateToken'])->name('escalated.api.mobile.auth.validate');
            Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('escalated.api.mobile.auth.me');
            Route::put('/auth/profile', [MobileAuthController::class, 'updateProfile'])->name('escalated.api.mobile.auth.profile');

            Route::get('/tickets', [MobileTicketController::class, 'index'])->name('escalated.api.mobile.tickets.index');
            Route::post('/tickets', [MobileTicketController::class, 'store'])->name('escalated.api.mobile.tickets.store');

            Route::middleware(ResolveTicketByReference::class)->group(function () {
                Route::get('/tickets/{ticket}', [MobileTicketController::class, 'show'])->name('escalated.api.mobile.tickets.show');
                Route::post('/tickets/{ticket}/replies', [MobileTicketController::class, 'reply'])->name('escalated.api.mobile.tickets.reply');
                Route::post('/tickets/{ticket}/close', [MobileTicketController::class, 'close'])->name('escalated.api.mobile.tickets.close');
                Route::post('/tickets/{ticket}/reopen', [MobileTicketController::class, 'reopen'])->name('escalated.api.mobile.tickets.reopen');
                Route::post('/tickets/{ticket}/rate', [MobileTicketController::class, 'rate'])->name('escalated.api.mobile.tickets.rate');
            });
        });
    });

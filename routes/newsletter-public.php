<?php

use Escalated\Laravel\Http\Controllers\Public\NewsletterTrackingController;
use Escalated\Laravel\Http\Controllers\Public\NewsletterUnsubscribeController;
use Escalated\Laravel\Http\Controllers\Public\NewsletterViewInBrowserController;
use Escalated\Laravel\Http\Middleware\EnsureNewslettersEnabled;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureNewslettersEnabled::class)->group(function () {
    Route::get('/o/{token}', [NewsletterTrackingController::class, 'open'])
        ->name('open')
        ->where('token', '[A-Za-z0-9._-]+');

    Route::get('/c/{token}', [NewsletterTrackingController::class, 'click'])
        ->name('click')
        ->where('token', '[A-Za-z0-9_-]+');

    Route::get('/u/{token}', [NewsletterUnsubscribeController::class, 'show'])
        ->name('unsubscribe.show')
        ->where('token', '[A-Za-z0-9_-]+');

    Route::post('/u/{token}', [NewsletterUnsubscribeController::class, 'store'])
        ->name('unsubscribe.store')
        ->where('token', '[A-Za-z0-9_-]+')
        ->withoutMiddleware(VerifyCsrfToken::class);

    Route::get('/v/{token}', [NewsletterViewInBrowserController::class, 'show'])
        ->name('view')
        ->where('token', '[A-Za-z0-9_-]+');
});

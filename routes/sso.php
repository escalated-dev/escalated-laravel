<?php

use Escalated\Laravel\Http\Controllers\SsoController;
use Escalated\Laravel\Http\Middleware\EnsureFeatureEnabled;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * Single sign-on (ADR 2026-10-29-configurable-access-features). Every route
 * answers 404 while escalated.access.sso is off, and the controller answers
 * 404 for a provider other than the one chosen in Admin > Settings > SSO.
 */
Route::middleware(['web', EnsureFeatureEnabled::class.':sso'])
    ->prefix(config('escalated.routes.prefix', 'support').'/sso')
    ->group(function () {
        Route::get('/login', [SsoController::class, 'login'])->name('escalated.sso.login');
        Route::get('/saml/metadata', [SsoController::class, 'metadata'])->name('escalated.sso.saml.metadata');

        // The IdP posts here cross-site, so there is no CSRF token to check;
        // the signed, single-use assertion is what authenticates the request.
        Route::post('/saml/acs', [SsoController::class, 'samlAcs'])
            ->withoutMiddleware(array_filter([
                VerifyCsrfToken::class,
                class_exists('Illuminate\Foundation\Http\Middleware\PreventRequestForgery')
                    ? 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery' : null,
                class_exists('Illuminate\Foundation\Http\Middleware\ValidateCsrfToken')
                    ? 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken' : null,
            ]))
            ->name('escalated.sso.saml.acs');

        Route::get('/oauth/callback', [SsoController::class, 'oauthCallback'])->name('escalated.sso.oauth.callback');

        Route::match(['get', 'post'], '/jwt/callback', [SsoController::class, 'jwtCallback'])
            ->withoutMiddleware(array_filter([
                VerifyCsrfToken::class,
                class_exists('Illuminate\Foundation\Http\Middleware\PreventRequestForgery')
                    ? 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery' : null,
                class_exists('Illuminate\Foundation\Http\Middleware\ValidateCsrfToken')
                    ? 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken' : null,
            ]))
            ->name('escalated.sso.jwt.callback');
    });

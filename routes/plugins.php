<?php

use Escalated\Laravel\Http\Controllers\Admin\PluginController;
use Escalated\Laravel\Http\Middleware\EnforceRoutePermissions;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Escalated\Laravel\Http\Middleware\RequireTwoFactor;
use Escalated\Laravel\Support\EmbedConfig;
use Illuminate\Support\Facades\Route;

// Under `{prefix}/admin`, or under the host's prefix and middleware while the
// admin is embedded in the host's admin panel (EmbedConfig; ADR 2026-11-05).
Route::middleware(EmbedConfig::adminMiddleware([EnsureIsAdmin::class, RequireTwoFactor::class, EnforceRoutePermissions::class]))
    ->prefix(EmbedConfig::adminPrefix())
    ->group(function () {
        Route::get('/plugins', [PluginController::class, 'index'])->name('escalated.admin.plugins.index');
        Route::post('/plugins/upload', [PluginController::class, 'upload'])->name('escalated.admin.plugins.upload');
        Route::post('/plugins/{slug}/activate', [PluginController::class, 'activate'])->name('escalated.admin.plugins.activate');
        Route::post('/plugins/{slug}/deactivate', [PluginController::class, 'deactivate'])->name('escalated.admin.plugins.deactivate');
        Route::delete('/plugins/{slug}', [PluginController::class, 'destroy'])->name('escalated.admin.plugins.destroy');
    });

<?php

use Escalated\Laravel\Http\Controllers\AdminPluginController;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(config('escalated.routes.admin_middleware', ['web', 'auth']), [EnsureIsAdmin::class]))
    ->prefix(config('escalated.routes.prefix', 'support').'/admin')
    ->group(function () {
        Route::get('/plugins', [AdminPluginController::class, 'index'])->name('escalated.admin.plugins.index');
        Route::post('/plugins/upload', [AdminPluginController::class, 'upload'])->name('escalated.admin.plugins.upload');
        Route::post('/plugins/{slug}/activate', [AdminPluginController::class, 'activate'])->name('escalated.admin.plugins.activate');
        Route::post('/plugins/{slug}/deactivate', [AdminPluginController::class, 'deactivate'])->name('escalated.admin.plugins.deactivate');
        Route::delete('/plugins/{slug}', [AdminPluginController::class, 'destroy'])->name('escalated.admin.plugins.destroy');
    });

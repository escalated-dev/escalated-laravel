<?php

use Escalated\Laravel\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signed', 'throttle:120,1'])
    ->get(config('escalated.routes.prefix', 'support').'/attachments/{attachment}', AttachmentController::class)
    ->whereNumber('attachment')
    ->name('escalated.attachments.download');

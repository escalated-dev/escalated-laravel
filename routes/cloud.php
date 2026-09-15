<?php

use Escalated\Laravel\Http\Controllers\Cloud\CloudWebhookController;
use Illuminate\Support\Facades\Route;

// Receives signed ticket webhooks from cloud.escalated.dev so agent actions
// taken in the cloud portal reach this install. Authenticated by the
// X-Escalated-Signature HMAC, not by a session; lives on the api group so
// CSRF does not apply.
Route::middleware('api')
    ->post('/escalated/cloud/webhook', CloudWebhookController::class)
    ->name('escalated.cloud.webhook');

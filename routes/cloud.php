<?php

use Escalated\Laravel\Http\Controllers\Cloud\CloudWebhookController;
use Illuminate\Support\Facades\Route;

// Receives signed ticket webhooks from cloud.escalated.dev so agent actions
// taken in the cloud portal reach this install. Authenticated by the
// X-Escalated-Signature HMAC, not by a session; lives on the api group so
// CSRF does not apply. Registered only once escalated.hosted.signing_secret
// is set; without it there is nothing to verify a request against.
if (filled(config('escalated.hosted.signing_secret'))) {
    Route::middleware('api')
        ->post('/escalated/cloud/webhook', CloudWebhookController::class)
        ->name('escalated.cloud.webhook');
}

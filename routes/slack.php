<?php

use Escalated\Laravel\Http\Controllers\SlackInboundController;
use Illuminate\Support\Facades\Route;

// Tenant identity comes exclusively from authenticated Slack routing, not the
// URL, headers or the host's ordinary authenticated-user tenant middleware.
Route::middleware(['api', 'throttle:'.max(1, min(10000, (int) config('escalated.slack.requests_per_minute', 600))).',1'])
    ->post(config('escalated.routes.prefix', 'support').'/inbound/slack/{app}', SlackInboundController::class)
    ->where('app', '[a-zA-Z0-9_-]{1,64}')->name('escalated.slack.inbound');

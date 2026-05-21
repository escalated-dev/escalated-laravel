<?php

use Escalated\Laravel\Http\Controllers\Webhooks\NewsletterEspWebhookController;
use Escalated\Laravel\Http\Middleware\EnsureNewslettersEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureNewslettersEnabled::class)->group(function () {
    Route::post('/postmark', [NewsletterEspWebhookController::class, 'postmark']);
    Route::post('/mailgun', [NewsletterEspWebhookController::class, 'mailgun']);
    Route::post('/ses', [NewsletterEspWebhookController::class, 'ses']);
    Route::post('/sendgrid', [NewsletterEspWebhookController::class, 'sendgrid']);
});

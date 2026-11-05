<?php

use Escalated\Laravel\Tests\EmbedIframeTestCase;
use Escalated\Laravel\Tests\Fixtures\EmbedHostMiddleware;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

/*
 * iframe mode (ADR 2026-11-05-embeddable-admin): only the configured origins
 * may frame the embedded admin, and only embedded responses change.
 */
uses(EmbedIframeTestCase::class);

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('lets only the allow-listed origins frame an embedded page', function () {
    $response = $this->actingAs($this->createAdmin())->get('/backoffice/helpdesk/tags');

    $response->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self' https://admin.example.com")
        ->and($response->headers->has('X-Frame-Options'))->toBeFalse();
});

it('leaves every other response\'s framing headers alone', function () {
    $response = $this->actingAs($this->createAdmin(['is_agent' => true]))->get('/support/agent');

    $response->assertOk();
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
});

it('shares the allow-list, without wildcards, for the postMessage bridge', function () {
    $this->actingAs($this->createAdmin())
        ->get('/backoffice/helpdesk/tags')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.embed.enabled', true)
            ->where('escalated.embed.mode', 'iframe')
            ->where('escalated.embed.allowed_origins', ['https://admin.example.com']));
});

it('keeps an existing host policy and adds frame-ancestors as a second one', function () {
    $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', EmbedHostMiddleware::class.':csp');

    $response = $this->actingAs($this->createAdmin())->get('/backoffice/helpdesk/tags');

    expect($response->headers->all('content-security-policy'))
        ->toBe(["script-src 'self'", "frame-ancestors 'self' https://admin.example.com"]);
});

it('allows only same-origin framing when no origins are configured', function () {
    config(['escalated.ui.embed.frame_ancestors' => []]);

    $response = $this->actingAs($this->createAdmin())->get('/backoffice/helpdesk/tags');

    expect($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self'");
});

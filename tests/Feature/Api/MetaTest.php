<?php

use Composer\InstalledVersions;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function metaAgentHeaders(?DateTimeInterface $expiresAt = null): array
{
    $user = TestUser::create([
        'name' => 'Meta Agent',
        'email' => 'meta-agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $result = ApiToken::createToken($user, 'Meta Token', ['agent'], $expiresAt);

    return ['Authorization' => 'Bearer '.$result['plainTextToken']];
}

it('serves public meta', function () {
    $response = $this->getJson('/support/api/v1/meta')->assertOk();

    expect($response->json())->not->toHaveKey('backend_version')
        ->and($response->json('api_version'))->toBe('1.0')
        ->and($response->json('backend'))->toBe('laravel')
        ->and($response->json('connect_url'))->toBeNull()
        ->and($response->json('web_url_template'))->toStartWith('http')
        ->and($response->json('web_url_template'))->toEndWith('/support/agent/tickets/{reference}');
});

it('serves meta', function () {
    $response = $this->getJson('/support/api/v1/meta', metaAgentHeaders())->assertOk();

    $expected = ltrim((string) (InstalledVersions::getPrettyVersion('escalated-dev/escalated-laravel') ?? 'dev'), 'v');

    expect($response->json('api_version'))->toBe('1.0')
        ->and($response->json('backend'))->toBe('laravel')
        ->and($response->json('backend_version'))->toBe($expected)
        ->and($response->json('features'))->toContain('tickets.updated_since', 'tickets.create', 'tickets.macro', 'tickets.last_customer_reply_at', 'canned_responses', 'macros', 'auth.exchange', 'auth.revoke')
        ->and($response->json('features'))->not->toContain('realtime');
});

it('lists realtime only when broadcasting is on', function () {
    config()->set('escalated.broadcasting.enabled', true);

    expect($this->getJson('/support/api/v1/meta')->json('features'))->toContain('realtime');
});

it('treats an invalid token as public', function () {
    $response = $this->getJson('/support/api/v1/meta', ['Authorization' => 'Bearer nonsense'])->assertOk();

    expect($response->json())->not->toHaveKey('backend_version');
});

it('treats an expired token as public', function () {
    $response = $this->getJson('/support/api/v1/meta', metaAgentHeaders(now()->subDay()))->assertOk();

    expect($response->json())->not->toHaveKey('backend_version');
});

it('sends cache headers on meta', function () {
    $response = $this->getJson('/support/api/v1/meta')->assertOk();

    expect($response->headers->get('Vary'))->toContain('Authorization')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

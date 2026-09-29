<?php

use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Bridge\PluginHttp;
use Escalated\Laravel\Bridge\RouteRegistrar;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->bridge = Mockery::mock(PluginBridge::class);
    $this->registrar = new RouteRegistrar($this->bridge, app(EscalatedUiRenderer::class));
    $this->manifest = json_decode(file_get_contents(__DIR__.'/../Fixtures/plugin-http-manifest.json'), true);
    $this->registrar->registerPlugin('http-fixture', $this->manifest);
    Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
    Gate::define('plugin.manage', fn () => true);
    $this->raw = " {\"message\": \"📦\", \"id\": \"body\"}\r\n";
});

it('registers a real SDK manifest and preserves exact webhook bytes and status', function () {
    $this->bridge->shouldReceive('callWebhook')->once()->withArgs(function ($plugin, $method, $path, $body, $headers, $transport) {
        expect($plugin)->toBe('http-fixture')->and($method)->toBe('POST')->and($path)->toBe('/events')
            ->and($body)->toBe(['message' => '📦', 'id' => 'body'])
            ->and($headers['x-slack-signature'])->toBe('v0=test')
            ->and($transport['query'])->toBe(['id' => 'query'])
            ->and($transport['params'])->toBe([])->and($transport['httpContract'])->toBe(1)
            ->and(base64_decode($transport['rawBodyBase64'], true))->toBe($this->raw)
            ->and($transport['clientIp'])->toBe('192.0.2.4');

        return true;
    })->andReturn(['__escalated_http' => 1, 'status' => 401, 'format' => 'json',
        'headers' => ['Cache-Control' => 'no-store'], 'body' => ['error' => 'Invalid signature']]);
    $this->call('POST', '/support/webhooks/plugins/http-fixture/events?id=query', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_SLACK_SIGNATURE' => 'v0=test',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.99', 'REMOTE_ADDR' => '192.0.2.4',
    ], $this->raw)->assertUnauthorized()->assertJsonPath('error', 'Invalid signature')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('keeps plugin endpoints authenticated and capability protected', function () {
    $path = '/support/api/plugins/http-fixture/echo/route-id';
    $this->postJson($path)->assertUnauthorized();
    $this->actingAs($this->createTestUser())->postJson($path)->assertForbidden();
    Gate::define('plugin.manage', fn () => false);
    $this->actingAs($this->createAdmin())->postJson($path)->assertForbidden();
    $this->bridge->shouldNotHaveReceived('callEndpoint');
});

it('separates route parameters query and body on authorized endpoints', function () {
    $this->bridge->shouldReceive('callEndpoint')->once()->withArgs(function ($plugin, $method, $path, $request) {
        expect($path)->toBe('/echo/{id}')->and($request['params'])->toBe(['id' => 'route-id'])
            ->and($request['query'])->toBe(['id' => 'query'])->and($request['body']['id'])->toBe('body')
            ->and(base64_decode($request['rawBodyBase64']))->toBe($this->raw);

        return true;
    })->andReturn(['status' => 401, 'ordinary' => true]);
    $this->actingAs($this->createAdmin())->call('POST', '/support/api/plugins/http-fixture/echo/route-id?id=query', [], [], [],
        ['CONTENT_TYPE' => 'application/json'], $this->raw)->assertOk()->assertJsonPath('status', 401);
});

it('keeps legacy manifest maps and explicit text responses working', function () {
    $this->registrar->registerPlugin('legacy', ['webhooks' => ['POST /challenge' => []]]);
    $this->bridge->shouldReceive('callWebhook')->andReturn(['__escalated_http' => 1, 'status' => 202,
        'format' => 'text', 'headers' => ['Retry-After' => '10'], 'body' => "challenge\n"]);
    $this->post('/support/webhooks/plugins/legacy/challenge')->assertStatus(202)
        ->assertContent("challenge\n")->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertHeader('Retry-After', '10');
});

it('rejects malformed response envelopes and unsafe response headers', function (array $changes) {
    Route::get('/plugin-response-test', fn () => PluginHttp::response(array_replace([
        '__escalated_http' => 1, 'status' => 200, 'format' => 'json', 'headers' => [], 'body' => [],
    ], $changes)));
    $this->getJson('/plugin-response-test')->assertStatus(502);
})->with([
    [['__escalated_http' => 2]], [['status' => '401']], [['status' => 199]], [['status' => 600]],
    [['format' => 'text', 'body' => []]], [['headers' => ['x-test' => "a\r\nSet-Cookie: b"]]],
    [['headers' => ['Set-Cookie' => 'session=1']]], [['headers' => ['content-length' => '0']]],
    [['headers' => ['X-Test' => 'a', 'x-test' => 'b']]],
]);

it('does not register plugin routes when tenant execution is disabled', function () {
    config(['escalated.tenancy.enabled' => true]);
    expect(fn () => $this->registrar->registerPlugin('other', $this->manifest))
        ->toThrow(AuthorizationException::class);
});

it('carries HTTP fields through the actual bridge JSON RPC peer', function (bool $legacy) {
    config(['escalated.plugins.runtime_command' => escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../Fixtures/plugin-http-runtime.php').($legacy ? ' --legacy' : ''),
        'escalated.plugins.runtime_cwd' => __DIR__]);
    $bridge = new PluginBridge;
    $bridge->boot();
    expect($bridge->isBooted())->toBeTrue();
    $this->actingAs($this->createAdmin());
    foreach (['/support/webhooks/plugins/http-fixture/events', '/support/api/plugins/http-fixture/echo/route'] as $path) {
        $response = $this->call('POST', $path.'?q=query', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_X_SLACK_SIGNATURE' => 'v0=signature', 'REMOTE_ADDR' => '192.0.2.5'], $this->raw);
        if ($legacy) {
            $response->assertStatus(503);
        } else {
            $response->assertUnauthorized()->assertJsonPath('httpContract', 1)
                ->assertJsonPath('rawBodyBase64', base64_encode($this->raw))->assertJsonPath('query.q', 'query')
                ->assertJsonPath('headers.x-slack-signature', 'v0=signature')->assertJsonPath('clientIp', '192.0.2.5');
        }
    }
    unset($bridge);
})->with([false, true]);

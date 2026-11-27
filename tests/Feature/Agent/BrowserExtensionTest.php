<?php

/*
 * The browser extension connect page and the agent's own extension token list
 * (staff API contract v1, "Connecting an extension" and "Connect page
 * requirements").
 */

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\ApiTokenCode;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

const BX_CONNECT_URL = '/support/agent/connect-extension';
const BX_APPROVE_URL = '/support/agent/connect-extension/approve';
const BX_DENY_URL = '/support/agent/connect-extension/deny';
const BX_ID = 'abcdefghijklmnopabcdefghijklmnop';
const BX_REDIRECT = 'https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/';
const BX_VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
const BX_CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function bxParams(array $overrides = []): array
{
    return array_merge([
        'redirect_uri' => BX_REDIRECT,
        'state' => 'xyz',
        'code_challenge' => BX_CHALLENGE,
        'code_challenge_method' => 'S256',
        'client_name' => 'Escalated for Chrome',
    ], $overrides);
}

function bxConnectUrl(array $params): string
{
    return BX_CONNECT_URL.'?'.http_build_query($params);
}

function bxAssertFramingDenied(TestResponse $response): void
{
    expect($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'none'")
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
}

/** @return array{0: string, 1: array<string, string>} the URL before the query, and the query */
function bxSplitLocation(TestResponse $response): array
{
    $location = (string) $response->headers->get('Location');
    [$base, $query] = array_pad(explode('?', $location, 2), 2, '');
    parse_str($query, $parts);

    return [$base, $parts];
}

function bxManifestProps(string $page): array
{
    $manifest = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/escalated-pages.json'), true, flags: JSON_THROW_ON_ERROR);

    return $manifest['props'][$page]['props'];
}

function bxSentProps(TestResponse $response): array
{
    $shared = ['errors', 'auth', 'flash', 'escalated', 'ziggy'];
    $sent = array_values(array_diff(array_keys($response->json('props')), $shared));
    sort($sent);

    return $sent;
}

// ---- GET the connect page ---------------------------------------------------

it('shows the connect page with who is asking and the params to post back', function () {
    $agent = $this->createAgent();

    $response = $this->actingAs($agent)->get(bxConnectUrl(bxParams()))->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Escalated/Agent/ConnectExtension', false)
        ->where('client_name', 'Escalated for Chrome')
        ->where('extension_id', BX_ID)
        ->where('origin_host', BX_ID.'.chromiumapp.org')
        ->where('approve_url', url(BX_APPROVE_URL))
        ->where('deny_url', url(BX_DENY_URL))
        ->where('params', [
            'redirect_uri' => BX_REDIRECT,
            'state' => 'xyz',
            'code_challenge' => BX_CHALLENGE,
            'code_challenge_method' => 'S256',
            'client_name' => 'Escalated for Chrome',
        ]));
    bxAssertFramingDenied($response);
    expect(ApiTokenCode::count())->toBe(0);
});

it('sends the connect page exactly the props the component declares', function () {
    $response = $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(bxConnectUrl(bxParams()))
        ->assertOk();

    expect($response->json('component'))->toBe('Escalated/Agent/ConnectExtension')
        ->and(bxSentProps($response))->toBe(bxManifestProps('Escalated/Agent/ConnectExtension'));
});

it('truncates client_name to 64 characters', function () {
    $long = str_repeat('é', 80);

    $this->actingAs($this->createAgent())
        ->get(bxConnectUrl(bxParams(['client_name' => $long])))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('client_name', str_repeat('é', 64))
            ->where('params.client_name', str_repeat('é', 64)));
});

it('shows the page when client_name is not valid UTF-8', function () {
    $this->actingAs($this->createAgent())
        ->get(BX_CONNECT_URL.'?'.http_build_query(bxParams(['client_name' => "ok\xC3"])))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('client_name', 'ok?'));
});

it('treats a state that is not UTF-8 as missing', function () {
    $response = $this->actingAs($this->createAgent())
        ->get(BX_CONNECT_URL.'?'.http_build_query(bxParams(['state' => "x\xFF"])));

    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)->and($query)->toBe(['error' => 'invalid_request']);
});

it('shows the page with an empty client_name when none is sent', function () {
    $params = bxParams();
    unset($params['client_name']);

    $this->actingAs($this->createAgent())
        ->get(bxConnectUrl($params))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('client_name', ''));
});

it('shows an error page and never redirects when redirect_uri is missing or invalid', function (mixed $redirectUri) {
    $params = bxParams();
    if ($redirectUri === null) {
        unset($params['redirect_uri']);
    } else {
        $params['redirect_uri'] = $redirectUri;
    }

    $response = $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(bxConnectUrl($params));

    $response->assertStatus(422);
    expect($response->headers->has('Location'))->toBeFalse()
        ->and($response->headers->has('X-Inertia-Location'))->toBeFalse()
        ->and($response->json('component'))->toBe('Escalated/Error')
        ->and($response->json('props.status'))->toBe(422)
        ->and(ApiTokenCode::count())->toBe(0);
    bxAssertFramingDenied($response);
})->with([
    'missing' => [null],
    'empty' => [''],
    'another site' => ['https://evil.example/'],
    'trailing newline' => [BX_REDIRECT."\n"],
    'trailing space' => [BX_REDIRECT.' '],
    'upper case' => ['https://ABCDEFGHIJKLMNOPABCDEFGHIJKLMNOP.chromiumapp.org/'],
    '31 letters' => ['https://abcdefghijklmnopabcdefghijklmno.chromiumapp.org/'],
    '33 letters' => ['https://abcdefghijklmnopabcdefghijklmnopa.chromiumapp.org/'],
    'letter outside a-p' => ['https://qbcdefghijklmnopabcdefghijklmnop.chromiumapp.org/'],
    'http' => ['http://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/'],
    'port' => ['https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org:443/'],
    'userinfo' => ['https://user@abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/'],
    'path' => [BX_REDIRECT.'callback'],
    'query' => [BX_REDIRECT.'?a=1'],
    'fragment' => [BX_REDIRECT.'#x'],
    'no trailing slash' => ['https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org'],
    'longer host' => ['https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org.evil.example/'],
    'subdomain' => ['https://x.abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/'],
    'array' => [[BX_REDIRECT]],
]);

it('renders the redirect_uri error page on a full page load too', function () {
    $response = $this->actingAs($this->createAgent())
        ->get(bxConnectUrl(bxParams(['redirect_uri' => 'https://evil.example/'])));

    $response->assertStatus(422)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Escalated/Error', false)->where('status', 422));
    expect($response->headers->has('Location'))->toBeFalse();
    bxAssertFramingDenied($response);
});

it('redirects with invalid_request when the other params are wrong', function (array $overrides, bool $withState = true) {
    $params = array_filter(bxParams($overrides), fn ($v) => $v !== null);

    $response = $this->actingAs($this->createAgent())->get(bxConnectUrl($params));

    $response->assertRedirect();
    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)
        ->and($query)->toBe($withState ? ['error' => 'invalid_request', 'state' => 'xyz'] : ['error' => 'invalid_request'])
        ->and(ApiTokenCode::count())->toBe(0);
    bxAssertFramingDenied($response);
})->with([
    'state missing' => [['state' => null], false],
    'state empty' => [['state' => ''], false],
    'challenge missing' => [['code_challenge' => null]],
    'challenge too short' => [['code_challenge' => substr(BX_CHALLENGE, 0, 42)]],
    'challenge too long' => [['code_challenge' => BX_CHALLENGE.'A']],
    'challenge padded' => [['code_challenge' => substr(BX_CHALLENGE, 0, 42).'=']],
    'challenge not base64url' => [['code_challenge' => substr(BX_CHALLENGE, 0, 42).'+']],
    'challenge with newline' => [['code_challenge' => substr(BX_CHALLENGE, 0, 42)."\n"]],
    'method missing' => [['code_challenge_method' => null]],
    'method plain' => [['code_challenge_method' => 'plain']],
    'method lower case' => [['code_challenge_method' => 's256']],
]);

it('encodes state in the redirect instead of copying it in', function () {
    $state = 'a&code=injected#frag';

    $response = $this->actingAs($this->createAgent())->post(BX_DENY_URL, bxParams(['state' => $state]));

    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)
        ->and($query)->toBe(['error' => 'access_denied', 'state' => $state])
        ->and((string) $response->headers->get('Location'))->not->toContain('#');
});

it('has no side effects on GET and never auto-approves', function () {
    $agent = $this->createAgent();

    $this->actingAs($agent)->post(BX_APPROVE_URL, bxParams())->assertRedirect();
    expect(ApiTokenCode::count())->toBe(1);

    $this->actingAs($agent)->get(bxConnectUrl(bxParams()))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Escalated/Agent/ConnectExtension', false));

    expect(ApiTokenCode::count())->toBe(1);
});

// ---- Approve and deny ---------------------------------------------------------

it('issues a one-time code on approve and redirects with it and the state', function () {
    $agent = $this->createAgent();

    $response = $this->actingAs($agent)->post(BX_APPROVE_URL, bxParams());

    $response->assertRedirect();
    bxAssertFramingDenied($response);
    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)
        ->and(array_keys($query))->toBe(['code', 'state'])
        ->and($query['state'])->toBe('xyz')
        ->and($query['code'])->toMatch('/\A[A-Za-z0-9_-]{43}\z/');

    $row = ApiTokenCode::sole();
    expect($row->code)->toBe(hash('sha256', $query['code']))
        ->and($row->tokenable_id)->toBe($agent->id)
        ->and($row->code_challenge)->toBe(BX_CHALLENGE)
        ->and($row->redirect_uri)->toBe(BX_REDIRECT)
        ->and($row->extension_id)->toBe(BX_ID)
        ->and($row->client_name)->toBe('Escalated for Chrome')
        ->and($row->expires_at->between(now()->addSeconds(55), now()->addSeconds(61)))->toBeTrue();

    // The code trades for an extension token.
    auth()->forgetGuards();
    $this->postJson('/support/api/v1/auth/exchange', [
        'code' => $query['code'],
        'code_verifier' => BX_VERIFIER,
        'redirect_uri' => BX_REDIRECT,
    ])->assertOk();

    expect(ApiToken::sole()->kind)->toBe('browser_extension')
        ->and(ApiToken::sole()->name)->toBe('Browser extension (Escalated for Chrome)');
});

it('issues the code with the truncated client_name', function () {
    $this->actingAs($this->createAgent())
        ->post(BX_APPROVE_URL, bxParams(['client_name' => str_repeat('x', 100)]))
        ->assertRedirect();

    expect(ApiTokenCode::sole()->client_name)->toBe(str_repeat('x', 64));
});

it('answers an Inertia approve with an external location', function () {
    $response = $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->post(BX_APPROVE_URL, bxParams());

    $response->assertStatus(409);
    $location = (string) $response->headers->get('X-Inertia-Location');
    expect($location)->toStartWith(BX_REDIRECT.'?code=')
        ->and($location)->toEndWith('&state=xyz')
        ->and(ApiTokenCode::count())->toBe(1);
    bxAssertFramingDenied($response);
});

it('redirects with access_denied on deny and issues no code', function () {
    $response = $this->actingAs($this->createAgent())->post(BX_DENY_URL, bxParams());

    $response->assertRedirect();
    bxAssertFramingDenied($response);
    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)
        ->and($query)->toBe(['error' => 'access_denied', 'state' => 'xyz'])
        ->and(ApiTokenCode::count())->toBe(0);
});

it('answers an Inertia deny with an external location', function () {
    $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->post(BX_DENY_URL, bxParams())
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', BX_REDIRECT.'?error=access_denied&state=xyz');
});

it('re-checks redirect_uri on approve and deny', function (string $url) {
    $response = $this->actingAs($this->createAgent())
        ->post($url, bxParams(['redirect_uri' => 'https://evil.example/']));

    $response->assertStatus(422);
    expect($response->headers->has('Location'))->toBeFalse()
        ->and(ApiTokenCode::count())->toBe(0);
    bxAssertFramingDenied($response);
})->with([BX_APPROVE_URL, BX_DENY_URL]);

it('re-checks the other params on approve and deny', function (string $url) {
    $response = $this->actingAs($this->createAgent())
        ->post($url, bxParams(['code_challenge_method' => 'plain']));

    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)
        ->and($query)->toBe(['error' => 'invalid_request', 'state' => 'xyz'])
        ->and(ApiTokenCode::count())->toBe(0);
})->with([BX_APPROVE_URL, BX_DENY_URL]);

it('approves a JSON post from the Inertia page', function () {
    $response = $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->postJson(BX_APPROVE_URL, bxParams());

    $response->assertStatus(409);
    expect((string) $response->headers->get('X-Inertia-Location'))->toStartWith(BX_REDIRECT.'?code=')
        ->and(ApiTokenCode::count())->toBe(1);
});

it('checks the posted redirect_uri as sent, before any trimming', function (string $redirect) {
    $response = $this->actingAs($this->createAgent())
        ->postJson(BX_APPROVE_URL, bxParams(['redirect_uri' => $redirect]));

    $response->assertStatus(422);
    expect(ApiTokenCode::count())->toBe(0);
})->with([
    'trailing newline' => [BX_REDIRECT."\n"],
    'leading space' => [' '.BX_REDIRECT],
]);

it('reads a urlencoded post as sent, before any trimming', function (string $redirect, int $status, int $codes) {
    $response = $this->actingAs($this->createAgent())->call(
        'POST',
        BX_APPROVE_URL,
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        http_build_query(bxParams(['redirect_uri' => $redirect])),
    );

    $response->assertStatus($status);
    expect(ApiTokenCode::count())->toBe($codes);
})->with([
    'valid' => [BX_REDIRECT, 302, 1],
    'trailing newline' => [BX_REDIRECT."\n", 422, 0],
]);

it('reads approve params from the body, not the query string', function () {
    $response = $this->actingAs($this->createAgent())
        ->post(BX_APPROVE_URL.'?'.http_build_query(bxParams()), []);

    $response->assertStatus(422);
    expect(ApiTokenCode::count())->toBe(0);
});

it('rejects GET on approve and deny', function (string $url) {
    $this->actingAs($this->createAgent())
        ->get($url.'?'.http_build_query(bxParams()))
        ->assertStatus(405);

    expect(ApiTokenCode::count())->toBe(0);
})->with([BX_APPROVE_URL, BX_DENY_URL]);

// ---- Who can approve -----------------------------------------------------------

it('refuses a signed-in user without agent access with a 403 and no redirect', function (string $method, string $url) {
    $user = $this->createTestUser(['email' => 'customer@example.com']);

    $response = $method === 'get'
        ? $this->actingAs($user)->get(bxConnectUrl(bxParams()))
        : $this->actingAs($user)->post($url, bxParams());

    $response->assertForbidden();
    expect($response->headers->has('Location'))->toBeFalse()
        ->and(ApiTokenCode::count())->toBe(0);
    bxAssertFramingDenied($response);
})->with([
    'page' => ['get', BX_CONNECT_URL],
    'approve' => ['post', BX_APPROVE_URL],
    'deny' => ['post', BX_DENY_URL],
]);

it('sends a signed-out user to sign in and back with every parameter intact', function () {
    Route::get('/login', fn () => 'login')->name('login');
    $url = bxConnectUrl(bxParams(['state' => 'a b&c']));

    $response = $this->get($url);

    $response->assertRedirect('/login');
    bxAssertFramingDenied($response);
    // Laravel re-orders and re-encodes the query; every value survives.
    $intended = (string) session('url.intended');
    parse_str((string) parse_url($intended, PHP_URL_QUERY), $query);
    $expected = bxParams(['state' => 'a b&c']);
    ksort($query);
    ksort($expected);
    expect(strtok($intended, '?'))->toBe(url(BX_CONNECT_URL))
        ->and($query)->toBe($expected);
});

// ---- Install patterns -------------------------------------------------------------

it('accepts an install pattern as a whole-string match', function () {
    config()->set('escalated.api.extension_redirect_patterns', [
        '#\Ahttps://(?<id>[a-z0-9]{8})\.extensions\.example\.test/\z#',
    ]);
    $redirect = 'https://abcd1234.extensions.example.test/';

    $this->actingAs($this->createAgent())
        ->get(bxConnectUrl(bxParams(['redirect_uri' => $redirect])))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('extension_id', 'abcd1234')
            ->where('origin_host', 'abcd1234.extensions.example.test'));

    $response = $this->actingAs($this->createAgent(['email' => 'two@example.com']))
        ->post(BX_APPROVE_URL, bxParams(['redirect_uri' => $redirect]));
    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe($redirect)
        ->and(array_keys($query))->toBe(['code', 'state'])
        ->and(ApiTokenCode::sole()->extension_id)->toBe('abcd1234')
        ->and(ApiTokenCode::sole()->redirect_uri)->toBe($redirect);
});

it('holds an install pattern to a whole-string match even with line anchors', function (string $redirect) {
    config()->set('escalated.api.extension_redirect_patterns', [
        '#^https://ext\.example\.test/$#m',
    ]);

    $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(bxConnectUrl(bxParams(['redirect_uri' => $redirect])))
        ->assertStatus(422);
})->with([
    'trailing newline' => ["https://ext.example.test/\n"],
    'second line' => ["https://evil.example/\nhttps://ext.example.test/"],
]);

it('takes the extension id from the host when an install pattern names no id', function () {
    config()->set('escalated.api.extension_redirect_patterns', ['#\Ahttps://ext\.example\.test/\z#']);

    $this->actingAs($this->createAgent())
        ->get(bxConnectUrl(bxParams(['redirect_uri' => 'https://ext.example.test/'])))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('extension_id', 'ext'));
});

it('refuses an install pattern extension id longer than 32 characters', function (string $url) {
    config()->set('escalated.api.extension_redirect_patterns', [
        '#\Ahttps://(?<id>[a-z0-9]+)\.extensions\.example\.test/\z#',
    ]);
    $redirect = 'https://'.str_repeat('a', 33).'.extensions.example.test/';

    $request = $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '']);
    $response = $url === BX_CONNECT_URL
        ? $request->get(bxConnectUrl(bxParams(['redirect_uri' => $redirect])))
        : $request->post($url, bxParams(['redirect_uri' => $redirect]));

    $response->assertStatus(422);
    expect($response->headers->has('Location'))->toBeFalse()
        ->and($response->json('component'))->toBe('Escalated/Error')
        ->and(ApiTokenCode::count())->toBe(0);
    bxAssertFramingDenied($response);
})->with([BX_CONNECT_URL, BX_APPROVE_URL, BX_DENY_URL]);

it('accepts an install pattern extension id of exactly 32 characters', function () {
    config()->set('escalated.api.extension_redirect_patterns', [
        '#\Ahttps://(?<id>[a-z0-9]+)\.extensions\.example\.test/\z#',
    ]);
    $redirect = 'https://'.str_repeat('a', 32).'.extensions.example.test/';

    $this->actingAs($this->createAgent())
        ->post(BX_APPROVE_URL, bxParams(['redirect_uri' => $redirect]))
        ->assertRedirect();

    expect(ApiTokenCode::sole()->extension_id)->toBe(str_repeat('a', 32));
});

it('refuses an install pattern redirect_uri longer than 255 characters', function (string $url) {
    config()->set('escalated.api.extension_redirect_patterns', [
        '#\Ahttps://(?<id>ext)\.example\.test/[a-z]*\z#',
    ]);
    $redirect = 'https://ext.example.test/';
    $redirect .= str_repeat('a', 256 - strlen($redirect));

    $request = $this->actingAs($this->createAgent())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '']);
    $response = $url === BX_CONNECT_URL
        ? $request->get(bxConnectUrl(bxParams(['redirect_uri' => $redirect])))
        : $request->post($url, bxParams(['redirect_uri' => $redirect]));

    $response->assertStatus(422);
    expect($response->headers->has('Location'))->toBeFalse()
        ->and($response->json('component'))->toBe('Escalated/Error')
        ->and(ApiTokenCode::count())->toBe(0);
    bxAssertFramingDenied($response);
})->with([BX_CONNECT_URL, BX_APPROVE_URL, BX_DENY_URL]);

it('accepts an install pattern redirect_uri of exactly 255 characters', function () {
    config()->set('escalated.api.extension_redirect_patterns', [
        '#\Ahttps://(?<id>ext)\.example\.test/[a-z]*\z#',
    ]);
    $redirect = 'https://ext.example.test/';
    $redirect .= str_repeat('a', 255 - strlen($redirect));

    $this->actingAs($this->createAgent())
        ->post(BX_APPROVE_URL, bxParams(['redirect_uri' => $redirect]))
        ->assertRedirect();

    expect(ApiTokenCode::sole()->redirect_uri)->toBe($redirect);
});

// ---- State length ----------------------------------------------------------------

it('treats a state longer than 512 characters as invalid_request', function (string $url) {
    $request = $this->actingAs($this->createAgent());
    $params = bxParams(['state' => str_repeat('s', 513)]);
    $response = $url === BX_CONNECT_URL ? $request->get(bxConnectUrl($params)) : $request->post($url, $params);

    $response->assertRedirect();
    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)
        ->and($query)->toBe(['error' => 'invalid_request'])
        ->and(ApiTokenCode::count())->toBe(0);
    bxAssertFramingDenied($response);
})->with([BX_CONNECT_URL, BX_APPROVE_URL, BX_DENY_URL]);

it('accepts a state of exactly 512 characters', function () {
    $state = str_repeat('s', 512);

    $response = $this->actingAs($this->createAgent())->post(BX_APPROVE_URL, bxParams(['state' => $state]));

    [$base, $query] = bxSplitLocation($response);
    expect($base)->toBe(BX_REDIRECT)
        ->and(array_keys($query))->toBe(['code', 'state'])
        ->and($query['state'])->toBe($state);
});

// ---- The agent's browser extensions list ---------------------------------------------

it('lists only the signed-in agent\'s browser extension tokens', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);

    $mine = ApiToken::createToken($agent, 'Browser extension (Chrome)', ['agent'], null, 'browser_extension', BX_ID)['token'];
    ApiToken::createToken($agent, 'Admin made token', ['agent']);
    ApiToken::createToken($other, 'Browser extension (Other)', ['agent'], null, 'browser_extension', BX_ID);

    $response = $this->actingAs($agent)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get('/support/agent/browser-extensions')
        ->assertOk();

    expect($response->json('component'))->toBe('Escalated/Agent/BrowserExtensions')
        ->and(bxSentProps($response))->toBe(bxManifestProps('Escalated/Agent/BrowserExtensions'))
        ->and($response->json('props.tokens'))->toHaveCount(1)
        ->and(array_keys($response->json('props.tokens.0')))->toBe(['id', 'name', 'extension_id', 'created_at', 'last_used_at', 'expires_at'])
        ->and($response->json('props.tokens.0.id'))->toBe($mine->id)
        ->and($response->json('props.tokens.0.name'))->toBe('Browser extension (Chrome)')
        ->and($response->json('props.tokens.0.extension_id'))->toBe(BX_ID)
        ->and($response->json('props.tokens.0.last_used_at'))->toBeNull()
        ->and($response->json('props.revoke_url_template'))->toBe(url('/support/agent/browser-extensions/__ID__'));
});

it('revokes the agent\'s own browser extension token and redirects back', function () {
    $agent = $this->createAgent();
    $token = ApiToken::createToken($agent, 'Browser extension', ['agent'], null, 'browser_extension', BX_ID)['token'];

    $this->actingAs($agent)
        ->from('/support/agent/browser-extensions')
        ->delete('/support/agent/browser-extensions/'.$token->id)
        ->assertRedirect('/support/agent/browser-extensions');

    expect(ApiToken::find($token->id))->toBeNull();
});

it('does not revoke another user\'s token or a token that is not an extension\'s', function () {
    $agent = $this->createAgent();
    $other = $this->createAgent(['email' => 'other@example.com']);
    $theirs = ApiToken::createToken($other, 'Browser extension', ['agent'], null, 'browser_extension', BX_ID)['token'];
    $adminMade = ApiToken::createToken($agent, 'Admin made token', ['agent'])['token'];

    $this->actingAs($agent)->delete('/support/agent/browser-extensions/'.$theirs->id)->assertNotFound();
    $this->actingAs($agent)->delete('/support/agent/browser-extensions/'.$adminMade->id)->assertNotFound();
    $this->actingAs($agent)->delete('/support/agent/browser-extensions/not-a-number')->assertNotFound();

    expect(ApiToken::find($theirs->id))->not->toBeNull()
        ->and(ApiToken::find($adminMade->id))->not->toBeNull();
});

it('shares the browser extensions page URL with agents only', function () {
    $shared = fn ($user, string $url) => $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get($url)->assertOk()->json('props.escalated');

    expect($shared($this->createAgent(), '/support/agent/tickets')['browser_extensions_url'])
        ->toBe(url('/support/agent/browser-extensions'))
        ->and($shared($this->createTestUser(['email' => 'customer@example.com']), route('escalated.customer.tickets.index')))
        ->not->toHaveKey('browser_extensions_url');
});

it('refuses the browser extensions list to a user without agent access', function () {
    $this->actingAs($this->createTestUser())->get('/support/agent/browser-extensions')->assertForbidden();
});

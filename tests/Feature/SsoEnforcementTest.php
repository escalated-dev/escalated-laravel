<?php

use Escalated\Laravel\Database\Seeders\PermissionSeeder;
use Escalated\Laravel\Http\Middleware\CheckPermission;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Escalated\Laravel\Tests\Support\SamlIdp;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * ADR 2026-10-29-configurable-access-features: SSO is an install-level switch
 * (escalated.access.sso). Off, the SSO settings screen and every sign-in route
 * answer 404. On, the provider chosen in the admin signs users in end to end,
 * with the checks the ADR lists for each provider.
 */

const SSO_JWT_SECRET = 'a-test-secret-that-is-long-enough-for-hs256-0123456789';

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    config(['app.url' => 'http://localhost']);
});

function ssoOn(string $provider, array $settings = []): void
{
    config(['escalated.access.sso' => true]);
    EscalatedSettings::set('sso_provider', $provider);
    foreach ($settings as $key => $value) {
        EscalatedSettings::set($key, $value);
    }
}

function ssoUser(string $email = 'person@example.com', array $attrs = []): TestUser
{
    return TestUser::create(array_merge([
        'name' => 'Person',
        'email' => $email,
        'password' => bcrypt('password'),
        'is_agent' => true,
    ], $attrs));
}

function samlOn(SamlIdp $idp): void
{
    ssoOn('saml', [
        'sso_entity_id' => $idp->entityId,
        'sso_login_url' => 'https://idp.example.test/sso',
        'sso_certificate' => $idp->certificate,
    ]);
}

/** Start a SAML sign-in and return the AuthnRequest ID it issued. */
function samlStart($test): string
{
    $location = $test->get(route('escalated.sso.login'))->assertRedirect()->headers->get('Location');
    expect($location)->toStartWith('https://idp.example.test/sso?SAMLRequest=');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    $xml = gzinflate(base64_decode($query['SAMLRequest']));
    preg_match('/ID="([^"]+)"/', $xml, $m);

    return $m[1];
}

function samlPost($test, string $xml)
{
    return $test->post(route('escalated.sso.saml.acs'), ['SAMLResponse' => base64_encode($xml)]);
}

function jwtOn(): void
{
    ssoOn('jwt', [
        'sso_login_url' => 'https://idp.example.test/jwt',
        'sso_jwt_secret' => SSO_JWT_SECRET,
        'sso_jwt_algorithm' => 'HS256',
    ]);
}

function jwtToken(array $claims = [], string $secret = SSO_JWT_SECRET, string $alg = 'HS256'): string
{
    return JWT::encode(array_merge([
        'email' => 'person@example.com',
        'name' => 'Person',
        'aud' => 'http://localhost',
        'exp' => time() + 120,
        'iat' => time(),
        'jti' => Str::random(20),
    ], $claims), $secret, $alg);
}

// --- Switch ------------------------------------------------------------------

it('is off by default and hides every SSO route', function () {
    expect(config('escalated.access.sso'))->toBeFalse();
    EscalatedSettings::set('sso_provider', 'jwt');
    $admin = ssoUser('admin@example.com', ['is_admin' => true]);

    $this->actingAs($admin)->get(route('escalated.admin.settings.sso'))->assertNotFound();
    $this->actingAs($admin)->post(route('escalated.admin.settings.sso.update'), ['sso_provider' => 'none'])->assertNotFound();

    auth()->logout();
    $this->get(route('escalated.sso.login'))->assertNotFound();
    $this->get(route('escalated.sso.saml.metadata'))->assertNotFound();
    $this->post(route('escalated.sso.saml.acs'))->assertNotFound();
    $this->get(route('escalated.sso.oauth.callback'))->assertNotFound();
    $this->get(route('escalated.sso.jwt.callback', ['jwt' => jwtToken()]))->assertNotFound();
});

it('answers 404 for sign-in while the provider is none', function () {
    ssoOn('none');

    $this->get(route('escalated.sso.login'))->assertNotFound();
});

it('shares the switch and gives the settings page its endpoints', function () {
    ssoOn('saml');
    $admin = ssoUser('admin@example.com', ['is_admin' => true]);

    $this->actingAs($admin)->get(route('escalated.admin.settings.sso'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.features.sso', true)
            ->where('endpoints.acs', route('escalated.sso.saml.acs'))
            ->where('endpoints.sp_entity_id', route('escalated.sso.saml.metadata'))
            ->where('endpoints.oauth_redirect', route('escalated.sso.oauth.callback'))
            ->where('endpoints.jwt_callback', route('escalated.sso.jwt.callback'))
            ->where('endpoints.login', route('escalated.sso.login')));
});

// --- SAML ----------------------------------------------------------------------

it('signs in with a signed SAML assertion for its own request', function () {
    $idp = new SamlIdp;
    samlOn($idp);
    $user = ssoUser();
    $id = samlStart($this);

    samlPost($this, $idp->response(route('escalated.sso.saml.acs'), route('escalated.sso.saml.metadata'), [
        'in_response_to' => $id,
        'attributes' => ['email' => 'Person@Example.com'],
    ]))->assertRedirect(route('escalated.agent.dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('publishes SP metadata', function () {
    samlOn(new SamlIdp);

    $this->get(route('escalated.sso.saml.metadata'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/samlmetadata+xml')
        ->assertSee(route('escalated.sso.saml.acs'), false);
});

it('refuses SAML responses that fail any check', function (array $options) {
    $idp = new SamlIdp;
    samlOn($idp);
    ssoUser();
    $id = samlStart($this);
    $tamper = $options['tamper'] ?? false;
    unset($options['tamper']);
    if (($options['signing_key'] ?? null) === 'other') {
        $options['signing_key'] = SamlIdp::keyPair()[0];
    }
    if (! array_key_exists('in_response_to', $options)) {
        $options['in_response_to'] = $id;
    } elseif ($options['in_response_to'] === 'unsolicited') {
        $options['in_response_to'] = null;
    }

    $xml = $idp->response(route('escalated.sso.saml.acs'), route('escalated.sso.saml.metadata'), $options);
    if ($tamper) {
        $xml = str_replace('person@example.com', 'boss@example.com', $xml);
    }

    samlPost($this, $xml)->assertForbidden();

    $this->assertGuest();
})->with([
    'unsigned assertion' => [['sign_assertion' => false]],
    'only the Response signed' => [['sign_assertion' => false, 'sign_response' => true]],
    'signed by another key' => [['signing_key' => 'other']],
    'tampered after signing' => [['tamper' => true]],
    'wrong issuer' => [['issuer' => 'https://evil.example.test']],
    'wrong audience' => [['audience' => 'https://other-sp.example.test']],
    'wrong destination' => [['destination' => 'https://other-sp.example.test/acs']],
    'wrong recipient' => [['recipient' => 'https://other-sp.example.test/acs']],
    'expired' => [['not_before' => time() - 3600, 'not_on_or_after' => time() - 1800]],
    'unknown InResponseTo' => [['in_response_to' => '_never-issued']],
    'unsolicited' => [['in_response_to' => 'unsolicited']],
]);

it('refuses every SAML response while no certificate is configured', function () {
    $idp = new SamlIdp;
    samlOn($idp);
    EscalatedSettings::set('sso_certificate', '');
    ssoUser();

    $this->get(route('escalated.sso.login'))->assertForbidden();
    samlPost($this, $idp->response(route('escalated.sso.saml.acs'), route('escalated.sso.saml.metadata')))->assertForbidden();
    $this->assertGuest();
});

it('refuses a SAML assertion the second time', function () {
    $idp = new SamlIdp;
    samlOn($idp);
    ssoUser();
    config(['escalated.sso.allow_idp_initiated' => true]);
    $xml = $idp->response(route('escalated.sso.saml.acs'), route('escalated.sso.saml.metadata'));

    samlPost($this, $xml)->assertRedirect();
    auth()->logout();

    samlPost($this, $xml)->assertForbidden();
    $this->assertGuest();
});

it('accepts an IdP-initiated response only when allowed', function () {
    $idp = new SamlIdp;
    samlOn($idp);
    $user = ssoUser();
    config(['escalated.sso.allow_idp_initiated' => true]);

    samlPost($this, $idp->response(route('escalated.sso.saml.acs'), route('escalated.sso.saml.metadata')))->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('refuses a SAML request ID the second time', function () {
    $idp = new SamlIdp;
    samlOn($idp);
    ssoUser();
    $id = samlStart($this);
    $acs = route('escalated.sso.saml.acs');
    $aud = route('escalated.sso.saml.metadata');

    samlPost($this, $idp->response($acs, $aud, ['in_response_to' => $id]))->assertRedirect();
    auth()->logout();

    samlPost($this, $idp->response($acs, $aud, ['in_response_to' => $id]))->assertForbidden();
});

// --- OAuth ---------------------------------------------------------------------

function oauthOn(array $responses, array &$history): void
{
    ssoOn('oauth', [
        'sso_oauth_authorize_url' => 'https://idp.example.test/authorize',
        'sso_oauth_token_url' => 'https://idp.example.test/token',
        'sso_oauth_userinfo_url' => 'https://idp.example.test/userinfo',
        'sso_oauth_client_id' => 'client-id',
        'sso_oauth_client_secret' => 'client-secret',
    ]);

    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    app()->instance('escalated.sso.http_client', new Client(['handler' => $stack]));
}

function oauthReplies(string $email = 'person@example.com'): array
{
    return [
        new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'at-123', 'token_type' => 'Bearer', 'expires_in' => 3600])),
        new PsrResponse(200, ['Content-Type' => 'application/json'], json_encode(['email' => $email, 'name' => 'Person', 'email_verified' => true])),
    ];
}

it('signs in through OAuth with state and PKCE', function () {
    $history = [];
    oauthOn(oauthReplies(), $history);
    $user = ssoUser();

    $location = $this->get(route('escalated.sso.login'))->assertRedirect()->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($location)->toStartWith('https://idp.example.test/authorize?')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['state'])->not->toBe('');

    $this->get(route('escalated.sso.oauth.callback', ['code' => 'auth-code', 'state' => $query['state']]))
        ->assertRedirect(route('escalated.agent.dashboard'));

    $this->assertAuthenticatedAs($user);
    parse_str((string) $history[0]['request']->getBody(), $tokenRequest);
    expect($tokenRequest['code'])->toBe('auth-code')
        ->and($tokenRequest['code_verifier'])->not->toBe('')
        ->and(rtrim(strtr(base64_encode(hash('sha256', $tokenRequest['code_verifier'], true)), '+/', '-_'), '='))->toBe($query['code_challenge']);
});

it('refuses an OAuth callback with a missing or wrong state', function () {
    $history = [];
    oauthOn(oauthReplies(), $history);
    ssoUser();

    $this->get(route('escalated.sso.login'));
    $this->get(route('escalated.sso.oauth.callback', ['code' => 'auth-code', 'state' => 'forged']))->assertForbidden();
    $this->assertGuest();

    $this->get(route('escalated.sso.oauth.callback', ['code' => 'auth-code']))->assertForbidden();
    $this->assertGuest();
    expect($history)->toBe([]);
});

// --- JWT -----------------------------------------------------------------------

it('signs in with a valid JWT', function () {
    jwtOn();
    $user = ssoUser();

    $this->get(route('escalated.sso.login'))
        ->assertRedirect('https://idp.example.test/jwt?return_to='.urlencode(route('escalated.sso.jwt.callback')));

    $this->get(route('escalated.sso.jwt.callback', ['jwt' => jwtToken()]))->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('refuses JWTs that fail any check', function (string $token) {
    jwtOn();
    ssoUser();
    expect(substr_count($token, '.'))->toBe(2);

    $this->get(route('escalated.sso.jwt.callback', ['jwt' => $token]))->assertForbidden();
    $this->assertGuest();
})->with([
    'bad signature' => fn () => jwtToken([], 'another-secret-that-is-also-long-enough-0123456789'),
    'alg none' => fn () => rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=').'.'
        .rtrim(strtr(base64_encode(json_encode(['email' => 'person@example.com', 'aud' => 'http://localhost', 'exp' => time() + 60, 'jti' => 'x'])), '+/', '-_'), '=').'.',
    'other algorithm' => fn () => jwtToken([], SSO_JWT_SECRET.SSO_JWT_SECRET, 'HS512'),
    'no exp' => fn () => JWT::encode(['email' => 'person@example.com', 'aud' => 'http://localhost', 'jti' => 'x1'], SSO_JWT_SECRET, 'HS256'),
    'expired' => fn () => jwtToken(['exp' => time() - 3600, 'iat' => time() - 7200]),
    'wrong audience' => fn () => jwtToken(['aud' => 'https://elsewhere.example.test']),
    'no jti' => fn () => JWT::encode(['email' => 'person@example.com', 'aud' => 'http://localhost', 'exp' => time() + 60], SSO_JWT_SECRET, 'HS256'),
]);

it('refuses a JWT the second time', function () {
    jwtOn();
    ssoUser();
    $token = jwtToken();

    $this->get(route('escalated.sso.jwt.callback', ['jwt' => $token]))->assertRedirect();
    auth()->logout();

    $this->get(route('escalated.sso.jwt.callback', ['jwt' => $token]))->assertForbidden();
    $this->assertGuest();
});

// --- Provisioning and role mapping --------------------------------------------

it('refuses an unknown email unless user creation is on', function () {
    jwtOn();

    $this->get(route('escalated.sso.jwt.callback', ['jwt' => jwtToken(['email' => 'new@example.com'])]))->assertForbidden();
    $this->assertGuest();

    config(['escalated.sso.create_users' => true]);
    $this->get(route('escalated.sso.jwt.callback', ['jwt' => jwtToken(['email' => 'new@example.com', 'name' => 'New Person'])]))
        ->assertRedirect();

    $created = TestUser::where('email', 'new@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('New Person');
    $this->assertAuthenticatedAs($created);
});

it('maps the role claim only with roles on and role sync on', function () {
    (new PermissionSeeder)->run();
    jwtOn();
    $user = ssoUser();

    $this->get(route('escalated.sso.jwt.callback', ['jwt' => jwtToken(['role' => 'light_agent'])]));
    expect(CheckPermission::userPermissions($user->getKey()))->toBe([]);

    auth()->logout();
    config(['escalated.access.roles_permissions' => true, 'escalated.sso.sync_roles' => true]);
    $this->get(route('escalated.sso.jwt.callback', ['jwt' => jwtToken(['role' => 'light_agent'])]));
    expect(CheckPermission::userPermissions($user->getKey()))->toContain('reply.create_internal')
        ->not->toContain('ticket.assign');

    auth()->logout();
    $this->get(route('escalated.sso.jwt.callback', ['jwt' => jwtToken(['role' => 'no-such-role'])]));
    expect(CheckPermission::userPermissions($user->getKey()))->toContain('reply.create_internal');
});

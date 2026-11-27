<?php

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\ApiTokenCode;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tests\Fixtures\TestTenantResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

const EXCHANGE_URL = '/support/api/v1/auth/exchange';
const EXCHANGE_VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
const EXCHANGE_CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';
const EXCHANGE_REDIRECT = 'https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/';
const EXCHANGE_EXTENSION = 'abcdefghijklmnopabcdefghijklmnop';

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function exchangeCode(Model $user, string $clientName = 'Chrome'): string
{
    return ApiTokenCode::issue($user, EXCHANGE_CHALLENGE, EXCHANGE_REDIRECT, $clientName, EXCHANGE_EXTENSION);
}

function exchangeBody(string $code, array $overrides = []): array
{
    return array_merge([
        'code' => $code,
        'code_verifier' => EXCHANGE_VERIFIER,
        'redirect_uri' => EXCHANGE_REDIRECT,
    ], $overrides);
}

it('answers a valid exchange with a bearer token', function () {
    $user = $this->createAgent(['name' => 'Sam Agent', 'email' => 'sam@example.com']);
    $code = exchangeCode($user);

    $response = $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertOk();

    expect(array_keys($response->json()))->toBe(['token', 'token_type', 'expires_at', 'user'])
        ->and($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('expires_at'))->toBeNull()
        ->and($response->json('user'))->toBe(['id' => $user->id, 'name' => 'Sam Agent', 'email' => 'sam@example.com'])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $token = ApiToken::sole();
    expect($token->name)->toBe('Browser extension (Chrome)')
        ->and($token->kind)->toBe('browser_extension')
        ->and($token->extension_id)->toBe(EXCHANGE_EXTENSION)
        ->and($token->abilities)->toBe(['agent'])
        ->and(ApiTokenCode::sole()->api_token_id)->toBe($token->id);

    $this->postJson('/support/api/v1/auth/validate', [], ['Authorization' => 'Bearer '.$response->json('token')])
        ->assertOk()
        ->assertJsonPath('abilities', ['agent'])
        ->assertJsonPath('token_name', 'Browser extension (Chrome)');
});

it('names the token without a client name when none was given', function () {
    $code = exchangeCode($this->createAgent(), '');

    $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertOk();

    expect(ApiToken::sole()->name)->toBe('Browser extension');
});

it('sets the token expiry from the extension expiry config', function () {
    config()->set('escalated.api.extension_token_expiry_days', 30);
    $code = exchangeCode($this->createAgent());

    $response = $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertOk();

    $expiresAt = ApiToken::sole()->expires_at;
    expect($expiresAt->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()))->toBeTrue()
        ->and($response->json('expires_at'))->toBe($expiresAt->toIso8601String());
});

it('answers an unknown code with the generic 400', function () {
    $this->postJson(EXCHANGE_URL, exchangeBody('not-a-real-code'))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    expect(ApiToken::count())->toBe(0);
});

it('rejects missing fields with 422 before looking up the code', function (string $field) {
    $code = exchangeCode($this->createAgent());
    $body = exchangeBody($code);
    unset($body[$field]);

    $this->postJson(EXCHANGE_URL, $body)
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => [$field]]);

    expect(ApiTokenCode::sole()->used_at)->toBeNull();
    $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertOk();
})->with(['code', 'code_verifier', 'redirect_uri']);

it('rejects non-string fields with 422 before looking up the code', function (string $field) {
    $code = exchangeCode($this->createAgent());

    $this->postJson(EXCHANGE_URL, exchangeBody($code, [$field => ['x']]))
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => [$field]]);

    $this->postJson(EXCHANGE_URL, exchangeBody($code, [$field => 12345]))->assertStatus(422);

    expect(ApiTokenCode::sole()->used_at)->toBeNull();
})->with(['code', 'code_verifier', 'redirect_uri']);

it('answers 422 to a request that does not ask for JSON', function () {
    $this->post(EXCHANGE_URL, [])->assertStatus(422)->assertJsonStructure(['message', 'errors']);
});

it('burns the code on a malformed verifier and answers the generic 400', function (string $verifier) {
    $code = exchangeCode($this->createAgent());

    $this->postJson(EXCHANGE_URL, exchangeBody($code, ['code_verifier' => $verifier]))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    expect(ApiTokenCode::sole()->used_at)->not->toBeNull();
    $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertStatus(400);
    expect(ApiToken::count())->toBe(0);
})->with([
    'too short' => [str_repeat('a', 42)],
    'too long' => [str_repeat('a', 129)],
    'bad character' => [str_repeat('a', 42).'+'],
    'trailing newline' => [EXCHANGE_VERIFIER."\n"],
]);

it('burns the code on a wrong verifier', function () {
    $code = exchangeCode($this->createAgent());

    $this->postJson(EXCHANGE_URL, exchangeBody($code, ['code_verifier' => str_repeat('b', 43)]))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertStatus(400);
    expect(ApiToken::count())->toBe(0);
});

it('answers 400 to the right code with a different redirect_uri and burns it', function (string $redirect) {
    $code = exchangeCode($this->createAgent());

    $this->postJson(EXCHANGE_URL, exchangeBody($code, ['redirect_uri' => $redirect]))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertStatus(400);
    expect(ApiToken::count())->toBe(0);
})->with([
    'other extension' => ['https://ponmlkjihgfedcbaponmlkjihgfedcba.chromiumapp.org/'],
    'no trailing slash' => ['https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org'],
    'upper case' => ['https://ABCDEFGHIJKLMNOPABCDEFGHIJKLMNOP.chromiumapp.org/'],
    'trailing newline' => [EXCHANGE_REDIRECT."\n"],
    'surrounding spaces' => [' '.EXCHANGE_REDIRECT.' '],
]);

it('answers 400 to the same code after 60 seconds', function () {
    $code = exchangeCode($this->createAgent());

    $this->travel(61)->seconds();

    $this->postJson(EXCHANGE_URL, exchangeBody($code))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    expect(ApiToken::count())->toBe(0);
});

it('revokes the first token when a code is replayed', function () {
    $code = exchangeCode($this->createAgent());

    $first = $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertOk()->json('token');

    $this->postJson(EXCHANGE_URL, exchangeBody($code))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    expect(ApiToken::count())->toBe(0);
    $this->postJson('/support/api/v1/auth/validate', [], ['Authorization' => 'Bearer '.$first])
        ->assertStatus(401);
});

it('mints nothing for a user who lost agent access after approving', function () {
    $user = $this->createAgent();
    $code = exchangeCode($user);
    $user->update(['is_agent' => false]);

    $this->postJson(EXCHANGE_URL, exchangeBody($code))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    expect(ApiToken::count())->toBe(0)
        ->and(ApiTokenCode::sole()->used_at)->not->toBeNull();
});

it('mints nothing when the approving user was deleted', function () {
    $user = $this->createAgent();
    $code = exchangeCode($user);
    $user->delete();

    $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertStatus(400);

    expect(ApiToken::count())->toBe(0);
});

it('maps a lost concurrent claim to the generic 400', function () {
    $code = exchangeCode($this->createAgent());
    ApiToken::creating(function () {
        throw new QueryException('testing', 'insert', [], new PDOException('SQLSTATE[HY000]: General error: 5 database is locked'));
    });

    $this->postJson(EXCHANGE_URL, exchangeBody($code))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);
});

it('does not hide other database errors', function () {
    $this->withoutExceptionHandling();
    $code = exchangeCode($this->createAgent());
    ApiToken::creating(function () {
        throw new QueryException('testing', 'insert', [], new PDOException('SQLSTATE[23000]: Integrity constraint violation'));
    });

    $this->postJson(EXCHANGE_URL, exchangeBody($code));
})->throws(QueryException::class);

it('rate limits exchanges to 10 a minute per IP', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson(EXCHANGE_URL, exchangeBody('unknown-'.$attempt))->assertStatus(400);
    }

    $this->postJson(EXCHANGE_URL, exchangeBody('unknown-11'))->assertStatus(429);
});

it('does not share its rate limit with meta', function () {
    foreach (range(1, 10) as $attempt) {
        $this->getJson('/support/api/v1/meta')->assertOk();
    }

    $this->postJson(EXCHANGE_URL, exchangeBody('unknown'))->assertStatus(400);
});

it('revokes the calling token', function () {
    $user = $this->createAgent();
    $plain = ApiToken::createToken($user, 'Browser extension (Chrome)', ['agent'], null, 'browser_extension', EXCHANGE_EXTENSION)['plainTextToken'];
    $other = ApiToken::createToken($user, 'Other', ['agent'])['token'];

    $this->postJson('/support/api/v1/auth/revoke', [], ['Authorization' => 'Bearer '.$plain])
        ->assertNoContent();

    $this->postJson('/support/api/v1/auth/validate', [], ['Authorization' => 'Bearer '.$plain])
        ->assertStatus(401);

    expect(ApiToken::find($other->id))->not->toBeNull();
});

it('revokes a token whatever its abilities and whether or not its owner is still an agent', function () {
    $admin = $this->createAdmin();
    $adminOnly = ApiToken::createToken($admin, 'Admin only', ['admin'])['plainTextToken'];

    $former = $this->createTestUser(['email' => 'former@example.com']);
    $formerToken = ApiToken::createToken($former, 'Former agent', ['agent'])['plainTextToken'];

    $this->postJson('/support/api/v1/auth/revoke', [], ['Authorization' => 'Bearer '.$adminOnly])->assertNoContent();
    $this->postJson('/support/api/v1/auth/revoke', [], ['Authorization' => 'Bearer '.$formerToken])->assertNoContent();

    expect(ApiToken::count())->toBe(0);
});

it('needs a valid token to revoke', function () {
    $this->postJson('/support/api/v1/auth/revoke')->assertStatus(401);
    $this->postJson('/support/api/v1/auth/revoke', [], ['Authorization' => 'Bearer nonsense'])->assertStatus(401);
});

it('stops an extension token once an admin revokes it', function () {
    $code = exchangeCode($this->createAgent());
    $plain = $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertOk()->json('token');
    $headers = ['Authorization' => 'Bearer '.$plain];

    $this->postJson('/support/api/v1/auth/validate', [], $headers)->assertOk();

    $this->actingAs($this->createAdmin())
        ->delete('/support/admin/api-tokens/'.ApiToken::sole()->id)
        ->assertRedirect();

    auth()->logout();
    $this->postJson('/support/api/v1/auth/validate', [], $headers)->assertStatus(401);
});

it('resolves the tenant before looking up the code', function () {
    $agent = $this->createAgent();
    $resolver = new TestTenantResolver;
    $resolver->members = ['a' => [$agent->id], 'b' => [$agent->id]];
    app()->instance(TenantResolver::class, $resolver);
    config(['escalated.tenancy.enabled' => true]);
    $context = app(TenantContext::class);

    $code = $context->run('a', fn () => exchangeCode($agent));

    $resolver->selected = 'b';
    $this->postJson(EXCHANGE_URL, exchangeBody($code))
        ->assertStatus(400)
        ->assertExactJson(['message' => 'Invalid or expired code.']);

    $resolver->selected = 'a';
    $plain = $this->postJson(EXCHANGE_URL, exchangeBody($code))->assertOk()->json('token');

    $token = $context->run('a', fn () => ApiToken::sole());
    expect($token->tenant_id)->toBe('a')
        ->and($context->current())->toBeNull();

    $this->postJson('/support/api/v1/auth/validate', [], ['Authorization' => 'Bearer '.$plain])->assertOk();
});

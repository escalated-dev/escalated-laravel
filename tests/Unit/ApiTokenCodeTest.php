<?php

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\ApiTokenCode;
use Illuminate\Database\Eloquent\Model;

const CODE_VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
const CODE_CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';
const CODE_REDIRECT = 'https://abcdefghijklmnopabcdefghijklmnop.chromiumapp.org/';
const CODE_EXTENSION = 'abcdefghijklmnopabcdefghijklmnop';

function issueCode(Model $user, int $ttl = 60): string
{
    return ApiTokenCode::issue($user, CODE_CHALLENGE, CODE_REDIRECT, 'Chrome', CODE_EXTENSION, $ttl);
}

it('issues a 43 character base64url code and stores only its hash', function () {
    $code = issueCode($this->createAgent());

    expect($code)->toMatch('/\A[A-Za-z0-9_-]{43}\z/');

    $row = ApiTokenCode::sole();
    expect($row->getRawOriginal('code'))->toBe(hash('sha256', $code))
        ->and($row->extension_id)->toBe(CODE_EXTENSION)
        ->and($row->client_name)->toBe('Chrome')
        ->and($row->used_at)->toBeNull()
        ->and($row->expires_at->isFuture())->toBeTrue();
});

it('consumes a code with the RFC 7636 verifier and exact redirect', function () {
    $code = issueCode($this->createAgent());

    $row = ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT);

    expect($row)->not->toBeNull()
        ->and($row->used_at)->not->toBeNull()
        ->and($row->extension_id)->toBe(CODE_EXTENSION);
});

it('burns the code on a wrong verifier', function () {
    $code = issueCode($this->createAgent());

    expect(ApiTokenCode::consume($code, str_repeat('a', 43), CODE_REDIRECT))->toBeNull()
        ->and(ApiTokenCode::sole()->used_at)->not->toBeNull()
        ->and(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT))->toBeNull();
});

it('burns the code on a wrong redirect uri', function () {
    $code = issueCode($this->createAgent());

    expect(ApiTokenCode::consume($code, CODE_VERIFIER, 'https://ponmlkjihgfedcbaponmlkjihgfedcba.chromiumapp.org/'))->toBeNull()
        ->and(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT))->toBeNull();
});

it('does not match a redirect uri that differs by a trailing newline', function () {
    $code = issueCode($this->createAgent());

    expect(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT."\n"))->toBeNull();
});

it('rejects an unknown code', function () {
    expect(ApiTokenCode::consume('nope', CODE_VERIFIER, CODE_REDIRECT))->toBeNull();
});

it('rejects an expired code', function () {
    $code = issueCode($this->createAgent());

    $this->travel(61)->seconds();

    expect(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT))->toBeNull();
});

it('honours a custom ttl', function () {
    $code = issueCode($this->createAgent(), 600);

    $this->travel(120)->seconds();

    expect(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT))->not->toBeNull();
});

it('rejects a second use and revokes the token the code issued', function () {
    $user = $this->createAgent();
    $code = ApiTokenCode::issue($user, CODE_CHALLENGE, CODE_REDIRECT, 'Chrome', CODE_EXTENSION);

    $row = ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT);
    $token = ApiToken::createToken($user, 'Browser extension (Chrome)', ['agent'], null, 'browser_extension', CODE_EXTENSION)['token'];
    $row->update(['api_token_id' => $token->id]);

    expect(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT))->toBeNull()
        ->and(ApiToken::find($token->id))->toBeNull()
        ->and(ApiTokenCode::count())->toBe(1);
});

it('stores kind and extension id on tokens', function () {
    $user = $this->createAgent();

    $token = ApiToken::createToken($user, 'Browser extension (Chrome)', ['agent'], null, 'browser_extension', CODE_EXTENSION)['token'];
    $plain = ApiToken::createToken($user, 'Plain', ['*'])['token'];

    expect($token->fresh()->kind)->toBe('browser_extension')
        ->and($token->fresh()->extension_id)->toBe(CODE_EXTENSION)
        ->and($plain->fresh()->kind)->toBeNull();
});

it('clamps client_name to 64 characters', function () {
    ApiTokenCode::issue($this->createAgent(), CODE_CHALLENGE, CODE_REDIRECT, str_repeat('é', 70), CODE_EXTENSION);

    expect(ApiTokenCode::sole()->client_name)->toBe(str_repeat('é', 64));
});

it('mints the token inside the claim and records it on the code', function () {
    $user = $this->createAgent();
    $code = issueCode($user);
    $seen = null;

    $row = ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT, function (ApiTokenCode $code) use ($user, &$seen) {
        $seen = $code;

        return ApiToken::createToken($user, 'Browser extension (Chrome)', ['agent'], null, 'browser_extension', $code->extension_id)['token'];
    });

    $token = ApiToken::sole();

    expect($seen)->toBeInstanceOf(ApiTokenCode::class)
        ->and($row->api_token_id)->toBe($token->id)
        ->and(ApiTokenCode::sole()->api_token_id)->toBe($token->id)
        ->and($token->extension_id)->toBe(CODE_EXTENSION);
});

it('burns the code and returns null when the callback mints nothing', function () {
    $code = issueCode($this->createAgent());

    expect(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT, fn () => null))->toBeNull()
        ->and(ApiTokenCode::sole()->used_at)->not->toBeNull()
        ->and(ApiTokenCode::sole()->api_token_id)->toBeNull()
        ->and(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT))->toBeNull();
});

it('does not call the callback when the verifier is wrong', function () {
    $code = issueCode($this->createAgent());
    $called = false;

    ApiTokenCode::consume($code, str_repeat('a', 43), CODE_REDIRECT, function () use (&$called) {
        $called = true;

        return null;
    });

    expect($called)->toBeFalse();
});

it('revokes the token a callback minted when the code is replayed', function () {
    $user = $this->createAgent();
    $code = issueCode($user);
    $mint = fn (ApiTokenCode $row) => ApiToken::createToken($user, 'Browser extension (Chrome)', ['agent'], null, 'browser_extension', $row->extension_id)['token'];

    ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT, $mint);
    expect(ApiToken::count())->toBe(1);

    expect(ApiTokenCode::consume($code, CODE_VERIFIER, CODE_REDIRECT, $mint))->toBeNull()
        ->and(ApiToken::count())->toBe(0);
});

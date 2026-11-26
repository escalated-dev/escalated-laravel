<?php

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Tests\Fixtures\TestUser;

it('registers a customer and returns a mobile token payload', function () {
    $this->postJson('/support/api/v1/mobile/auth/register', [
        'name' => 'Customer One',
        'email' => 'customer1@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(201)
        ->assertJsonPath('user.email', 'customer1@example.com')
        ->assertJsonPath('data.user.name', 'Customer One');

    expect(TestUser::where('email', 'customer1@example.com')->exists())->toBeTrue();
});

it('logs a customer in and validates the mobile token', function () {
    $user = TestUser::create([
        'name' => 'Existing Customer',
        'email' => 'existing@example.com',
        'password' => bcrypt('password123'),
    ]);

    $login = $this->postJson('/support/api/v1/mobile/auth/login', [
        'email' => 'existing@example.com',
        'password' => 'password123',
    ])->assertOk()
        ->json();

    $token = $login['token'];

    $this->postJson('/support/api/v1/mobile/auth/validate', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk()
        ->assertJsonPath('data.email', 'existing@example.com');
});

it('refreshes and revokes the previous mobile token', function () {
    $user = TestUser::create([
        'name' => 'Refresh Customer',
        'email' => 'refresh@example.com',
        'password' => bcrypt('password123'),
    ]);

    $result = ApiToken::createToken($user, 'Mobile Login', ['customer']);

    $refresh = $this->postJson('/support/api/v1/mobile/auth/refresh', [], [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertOk()
        ->json();

    $this->postJson('/support/api/v1/mobile/auth/validate', [], [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertStatus(401);

    $this->postJson('/support/api/v1/mobile/auth/validate', [], [
        'Authorization' => 'Bearer '.$refresh['token'],
    ])->assertOk()
        ->assertJsonPath('data.email', 'refresh@example.com');
});

function refreshMobileToken($test, string $plainText): ApiToken
{
    $refresh = $test->postJson('/support/api/v1/mobile/auth/refresh', [], [
        'Authorization' => 'Bearer '.$plainText,
    ])->assertOk()->json();

    return ApiToken::findByPlainText($refresh['token']);
}

function mobileRefreshUser(): TestUser
{
    return TestUser::create([
        'name' => 'Refresh User',
        'email' => 'refresh-'.uniqid().'@example.com',
        'password' => bcrypt('password123'),
    ]);
}

it('keeps the original expiry when refreshing a token with no configured expiry', function () {
    config(['escalated.api.token_expiry_days' => null]);
    $expiresAt = now()->addDays(10)->startOfSecond();
    $result = ApiToken::createToken(mobileRefreshUser(), 'Staff token', ['*'], $expiresAt);

    $new = refreshMobileToken($this, $result['plainTextToken']);

    expect($new->expires_at)->not->toBeNull()
        ->and($new->expires_at->timestamp)->toBe($expiresAt->timestamp);
});

it('never moves the expiry later than the original token', function () {
    config(['escalated.api.token_expiry_days' => 30]);
    $expiresAt = now()->addDays(5)->startOfSecond();
    $result = ApiToken::createToken(mobileRefreshUser(), 'Mobile Login', ['customer'], $expiresAt);

    $new = refreshMobileToken($this, $result['plainTextToken']);

    expect($new->expires_at->timestamp)->toBe($expiresAt->timestamp);
});

it('uses the configured expiry when it ends before the original token', function () {
    config(['escalated.api.token_expiry_days' => 30]);
    $result = ApiToken::createToken(mobileRefreshUser(), 'Mobile Login', ['customer'], now()->addDays(60));

    $new = refreshMobileToken($this, $result['plainTextToken']);

    expect(abs($new->expires_at->timestamp - now()->addDays(30)->timestamp))->toBeLessThan(5);
});

it('keeps a token without expiry unbounded only when no expiry is configured', function () {
    config(['escalated.api.token_expiry_days' => null]);
    $result = ApiToken::createToken(mobileRefreshUser(), 'Mobile Login', ['customer']);
    expect(refreshMobileToken($this, $result['plainTextToken'])->expires_at)->toBeNull();

    config(['escalated.api.token_expiry_days' => 30]);
    $result = ApiToken::createToken(mobileRefreshUser(), 'Mobile Login', ['customer']);
    $new = refreshMobileToken($this, $result['plainTextToken']);
    expect(abs($new->expires_at->timestamp - now()->addDays(30)->timestamp))->toBeLessThan(5);
});

it('keeps the abilities and name of the refreshed token', function () {
    config(['escalated.api.token_expiry_days' => null]);
    $user = mobileRefreshUser();
    $result = ApiToken::createToken($user, 'Staff token', ['*'], now()->addDays(10));

    $new = refreshMobileToken($this, $result['plainTextToken']);

    expect($new->abilities)->toBe(['*'])
        ->and($new->name)->toBe('Staff token')
        ->and($new->tokenable_id)->toBe($user->getKey());
});

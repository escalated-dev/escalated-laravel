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

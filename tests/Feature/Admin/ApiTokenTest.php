<?php

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function adminUser(array $attrs = []): TestUser
{
    return TestUser::create(array_merge([
        'name' => 'Admin',
        'email' => 'admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
        'is_agent' => true,
    ], $attrs));
}

function agentUser(array $attrs = []): TestUser
{
    return TestUser::create(array_merge([
        'name' => 'Agent Only',
        'email' => 'agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ], $attrs));
}

it('admin can list api tokens', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get('/support/admin/api-tokens')
        ->assertOk();
});

it('admin can create api token', function () {
    $admin = adminUser();
    $agent = agentUser();

    $response = $this->actingAs($admin)
        ->post('/support/admin/api-tokens', [
            'name' => 'Desktop App',
            'user_id' => $agent->getKey(),
            'abilities' => ['agent'],
            'expires_in_days' => 30,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('plain_text_token');

    expect(ApiToken::count())->toBe(1);
    expect(ApiToken::first()->name)->toBe('Desktop App');
    expect(ApiToken::first()->tokenable_id)->toBe($agent->getKey());
});

it('admin can revoke api token', function () {
    $admin = adminUser();
    $agent = agentUser();

    $result = ApiToken::createToken($agent, 'To Revoke', ['agent']);

    $this->actingAs($admin)
        ->delete('/support/admin/api-tokens/'.$result['token']->id)
        ->assertRedirect();

    expect(ApiToken::count())->toBe(0);
});

it('admin can update api token', function () {
    $admin = adminUser();
    $agent = agentUser();

    $result = ApiToken::createToken($agent, 'Old Name', ['agent']);

    $this->actingAs($admin)
        ->patch('/support/admin/api-tokens/'.$result['token']->id, [
            'name' => 'New Name',
            'abilities' => ['agent', 'admin'],
        ])
        ->assertRedirect();

    $result['token']->refresh();
    expect($result['token']->name)->toBe('New Name');
    expect($result['token']->abilities)->toBe(['agent', 'admin']);
});

it('non-admin cannot access api token management', function () {
    $agent = agentUser();

    $this->actingAs($agent)
        ->get('/support/admin/api-tokens')
        ->assertStatus(403);
});

it('creates token with no expiration', function () {
    $admin = adminUser();
    $agent = agentUser();

    $this->actingAs($admin)
        ->post('/support/admin/api-tokens', [
            'name' => 'Never Expires',
            'user_id' => $agent->getKey(),
            'abilities' => ['*'],
        ])
        ->assertRedirect();

    expect(ApiToken::first()->expires_at)->toBeNull();
});

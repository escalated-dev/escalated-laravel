<?php

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('validates a valid api token', function () {
    $user = createAgent();
    $result = ApiToken::createToken($user, 'Test Token', ['agent']);

    $this->postJson('/support/api/v1/auth/validate', [], [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertOk()
      ->assertJsonPath('user.name', 'Agent')
      ->assertJsonPath('token_name', 'Test Token');
});

it('rejects requests without a token', function () {
    $this->getJson('/support/api/v1/dashboard')
        ->assertStatus(401)
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('rejects invalid tokens', function () {
    $this->getJson('/support/api/v1/dashboard', [
        'Authorization' => 'Bearer invalid-token-here',
    ])->assertStatus(401)
      ->assertJsonPath('message', 'Invalid token.');
});

it('rejects expired tokens', function () {
    $user = createAgent();
    $result = ApiToken::createToken($user, 'Expired', ['agent'], now()->subDay());

    $this->getJson('/support/api/v1/dashboard', [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertStatus(401)
      ->assertJsonPath('message', 'Token has expired.');
});

it('tracks last used at on first request', function () {
    $user = createAgent();
    $result = ApiToken::createToken($user, 'Track Me', ['agent']);

    $this->getJson('/support/api/v1/dashboard', [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertOk();

    $result['token']->refresh();
    expect($result['token']->last_used_at)->not->toBeNull();
});

it('rate limits requests', function () {
    config()->set('escalated.api.rate_limit', 3);

    $user = createAgent();
    $result = ApiToken::createToken($user, 'Rate Limited', ['agent']);
    $headers = ['Authorization' => 'Bearer '.$result['plainTextToken']];

    // First 3 should pass
    for ($i = 0; $i < 3; $i++) {
        $this->getJson('/support/api/v1/dashboard', $headers)->assertOk();
    }

    // 4th should be rate limited
    $this->getJson('/support/api/v1/dashboard', $headers)
        ->assertStatus(429)
        ->assertJsonPath('message', 'Too many requests.');
});

it('hashes tokens with sha256', function () {
    $user = createAgent();
    $result = ApiToken::createToken($user, 'Hashed', ['agent']);

    expect($result['token']->token)->toBe(hash('sha256', $result['plainTextToken']));
    expect($result['token']->token)->not->toBe($result['plainTextToken']);
});

it('returns rate limit headers', function () {
    $user = createAgent();
    $result = ApiToken::createToken($user, 'Headers', ['agent']);

    $response = $this->getJson('/support/api/v1/dashboard', [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ]);

    $response->assertOk();
    $response->assertHeader('X-RateLimit-Limit');
    $response->assertHeader('X-RateLimit-Remaining');
});

it('denies agent-only token from admin routes', function () {
    $user = createAgent();
    $result = ApiToken::createToken($user, 'Agent Only', ['agent']);

    // Create a ticket to try to delete
    $ticket = \Escalated\Laravel\Models\Ticket::create([
        'reference' => 'ESC-99999',
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'subject' => 'Test',
        'description' => 'Test',
        'status' => \Escalated\Laravel\Enums\TicketStatus::Open,
        'priority' => \Escalated\Laravel\Enums\TicketPriority::Medium,
    ]);

    $this->deleteJson('/support/api/v1/tickets/ESC-99999', [], [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertStatus(403);
});

it('allows wildcard token on admin routes', function () {
    $user = TestUser::create([
        'name' => 'Super Admin',
        'email' => 'superadmin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
        'is_admin' => true,
    ]);

    $result = ApiToken::createToken($user, 'Wildcard', ['*']);

    $ticket = \Escalated\Laravel\Models\Ticket::create([
        'reference' => 'ESC-99998',
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->getKey(),
        'subject' => 'Test',
        'description' => 'Test',
        'status' => \Escalated\Laravel\Enums\TicketStatus::Open,
        'priority' => \Escalated\Laravel\Enums\TicketPriority::Medium,
    ]);

    $this->deleteJson('/support/api/v1/tickets/ESC-99998', [], [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertOk();
});

it('rejects non-agent user even with valid token', function () {
    $user = TestUser::create([
        'name' => 'Regular User',
        'email' => 'regular-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => false,
        'is_admin' => false,
    ]);

    $result = ApiToken::createToken($user, 'No Access', ['agent']);

    $this->getJson('/support/api/v1/tickets', [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertStatus(403)
      ->assertJsonPath('message', 'User no longer has agent access.');
});

// Helpers
function createAgent(array $attrs = []): TestUser
{
    return TestUser::create(array_merge([
        'name' => 'Agent',
        'email' => 'agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ], $attrs));
}

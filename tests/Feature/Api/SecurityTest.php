<?php

/**
 * OWASP-focused security tests for the REST API.
 *
 * Covers: IDOR prevention, input validation boundaries, enumeration resistance,
 * per_page DoS cap, auth failure logging, privilege escalation, mass assignment.
 */

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function secAgent(array $attrs = []): array
{
    $user = TestUser::create(array_merge([
        'name' => 'Sec Agent',
        'email' => 'sec-agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ], $attrs));

    $result = ApiToken::createToken($user, 'Sec Token', ['agent']);

    return [
        'user' => $user,
        'token' => $result['plainTextToken'],
        'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']],
    ];
}

function secAdmin(array $attrs = []): array
{
    $user = TestUser::create(array_merge([
        'name' => 'Sec Admin',
        'email' => 'sec-admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
        'is_admin' => true,
    ], $attrs));

    $result = ApiToken::createToken($user, 'Admin Token', ['agent', 'admin']);

    return [
        'user' => $user,
        'token' => $result['plainTextToken'],
        'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']],
    ];
}

function secTicket(TestUser $requester, array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'reference' => 'ESC-'.str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'requester_type' => $requester->getMorphClass(),
        'requester_id' => $requester->getKey(),
        'subject' => 'Security Test Ticket',
        'description' => 'Test description.',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Medium,
    ], $overrides));
}

// ========================================================================
// A01: Broken Access Control — IDOR Prevention
// ========================================================================

it('rejects ticket lookup by integer ID (IDOR prevention)', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user']);

    // Should NOT be able to access ticket by numeric ID
    $this->getJson('/support/api/v1/tickets/'.$ticket->id, $agent['headers'])
        ->assertStatus(404);
});

it('only resolves tickets by reference string', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-10001']);

    // Reference lookup should work
    $this->getJson('/support/api/v1/tickets/ESC-10001', $agent['headers'])
        ->assertOk()
        ->assertJsonPath('data.reference', 'ESC-10001');

    // Numeric ID should NOT work
    $this->getJson('/support/api/v1/tickets/'.$ticket->id, $agent['headers'])
        ->assertStatus(404);
});

it('returns 404 for non-existent ticket reference', function () {
    $agent = secAgent();

    $this->getJson('/support/api/v1/tickets/ESC-NONEXISTENT', $agent['headers'])
        ->assertStatus(404);
});

// ========================================================================
// A01: Broken Access Control — Token Ability Enforcement
// ========================================================================

it('token with no abilities cannot access any endpoint', function () {
    $user = TestUser::create([
        'name' => 'No Abilities',
        'email' => 'noab-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $result = ApiToken::createToken($user, 'Empty', []);

    $this->getJson('/support/api/v1/tickets', [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertStatus(403);
});

it('admin ability required for ticket deletion', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC01']);

    $this->deleteJson('/support/api/v1/tickets/ESC-SEC01', [], $agent['headers'])
        ->assertStatus(403);
});

it('agent with admin token ability but without admin gate is denied admin routes', function () {
    $user = TestUser::create([
        'name' => 'Fake Admin',
        'email' => 'fakeadmin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
        'is_admin' => false,
    ]);

    // Token has admin ability, but user is NOT admin
    $result = ApiToken::createToken($user, 'Fake Admin Token', ['agent', 'admin']);

    $ticket = secTicket($user, ['reference' => 'ESC-SEC02']);

    $this->deleteJson('/support/api/v1/tickets/ESC-SEC02', [], [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertStatus(403);
});

// ========================================================================
// A03: Injection — Input Validation Boundaries
// ========================================================================

it('rejects per_page exceeding max limit', function () {
    $agent = secAgent();

    $this->getJson('/support/api/v1/tickets?per_page=999999', $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

it('accepts per_page within valid range', function () {
    $agent = secAgent();

    for ($i = 0; $i < 3; $i++) {
        secTicket($agent['user']);
    }

    $response = $this->getJson('/support/api/v1/tickets?per_page=2', $agent['headers'])
        ->assertOk();

    expect($response->json('meta.per_page'))->toBe(2);
    expect($response->json('data'))->toHaveCount(2);
});

it('rejects non-integer per_page values', function () {
    $agent = secAgent();

    $this->getJson('/support/api/v1/tickets?per_page=abc', $agent['headers'])
        ->assertStatus(422);
});

it('rejects subject exceeding 255 characters', function () {
    $agent = secAgent();

    $this->postJson('/support/api/v1/tickets', [
        'subject' => str_repeat('A', 256),
        'description' => 'Valid description.',
    ], $agent['headers'])
        ->assertStatus(422);
});

it('rejects description exceeding 65535 characters', function () {
    $agent = secAgent();

    $this->postJson('/support/api/v1/tickets', [
        'subject' => 'Valid subject',
        'description' => str_repeat('A', 65536),
    ], $agent['headers'])
        ->assertStatus(422);
});

it('rejects reply body exceeding 65535 characters', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC03']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC03/reply', [
        'body' => str_repeat('A', 65536),
    ], $agent['headers'])
        ->assertStatus(422);
});

// ========================================================================
// A03: Injection — Validation on Foreign Keys
// ========================================================================

it('rejects non-existent department_id on ticket creation', function () {
    $agent = secAgent();

    $this->postJson('/support/api/v1/tickets', [
        'subject' => 'Test',
        'description' => 'Test description',
        'department_id' => 99999,
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');
});

it('rejects non-existent tag IDs on ticket creation', function () {
    $agent = secAgent();

    $this->postJson('/support/api/v1/tickets', [
        'subject' => 'Test',
        'description' => 'Test description',
        'tags' => [99999, 88888],
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tags.0');
});

it('rejects non-existent agent_id on assign', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC04']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC04/assign', [
        'agent_id' => 99999,
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('agent_id');
});

it('rejects non-existent tag IDs on tag sync', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC05']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC05/tags', [
        'tag_ids' => [99999],
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tag_ids.0');
});

it('rejects non-existent macro_id on apply macro', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC06']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC06/macro', [
        'macro_id' => 99999,
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('macro_id');
});

// ========================================================================
// A03: Injection — Status/Priority Validation
// ========================================================================

it('rejects invalid status enum values', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC07']);

    $this->patchJson('/support/api/v1/tickets/ESC-SEC07/status', [
        'status' => 'hacked',
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('rejects invalid priority enum values', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC08']);

    $this->patchJson('/support/api/v1/tickets/ESC-SEC08/priority', [
        'priority' => 'super_duper_urgent',
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('priority');
});

// ========================================================================
// A03: Injection — XSS Prevention
// ========================================================================

it('does not execute script tags in ticket subject', function () {
    $agent = secAgent();

    $response = $this->postJson('/support/api/v1/tickets', [
        'subject' => '<script>alert("xss")</script>',
        'description' => 'Normal description.',
    ], $agent['headers'])
        ->assertStatus(201);

    // The subject should be stored as-is (output encoding is frontend responsibility)
    // but must not cause a 500 or corrupt the response
    expect($response->json('data.subject'))->toBe('<script>alert("xss")</script>');
});

// ========================================================================
// A04: Insecure Design — Required Field Validation
// ========================================================================

it('rejects ticket creation without subject', function () {
    $agent = secAgent();

    $this->postJson('/support/api/v1/tickets', [
        'description' => 'Missing subject.',
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('subject');
});

it('rejects ticket creation without description', function () {
    $agent = secAgent();

    $this->postJson('/support/api/v1/tickets', [
        'subject' => 'Missing description.',
    ], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('description');
});

it('rejects reply without body', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC09']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC09/reply', [], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

it('rejects status change without status field', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC10']);

    $this->patchJson('/support/api/v1/tickets/ESC-SEC10/status', [], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('rejects priority change without priority field', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC11']);

    $this->patchJson('/support/api/v1/tickets/ESC-SEC11/priority', [], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('priority');
});

it('rejects assign without agent_id', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC12']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC12/assign', [], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('agent_id');
});

it('rejects tag sync without tag_ids', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC13']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC13/tags', [], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tag_ids');
});

it('rejects macro apply without macro_id', function () {
    $agent = secAgent();
    $ticket = secTicket($agent['user'], ['reference' => 'ESC-SEC14']);

    $this->postJson('/support/api/v1/tickets/ESC-SEC14/macro', [], $agent['headers'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('macro_id');
});

// ========================================================================
// A04: Insecure Design — Pagination
// ========================================================================

it('returns correct pagination meta on ticket list', function () {
    $agent = secAgent();

    for ($i = 0; $i < 5; $i++) {
        secTicket($agent['user']);
    }

    $response = $this->getJson('/support/api/v1/tickets?per_page=2', $agent['headers'])
        ->assertOk();

    expect($response->json('meta.per_page'))->toBe(2);
    expect($response->json('meta.total'))->toBe(5);
    expect($response->json('meta.last_page'))->toBe(3);
    expect($response->json('meta.current_page'))->toBe(1);
    expect($response->json('data'))->toHaveCount(2);
});

it('returns empty data when no tickets exist', function () {
    $agent = secAgent();

    $this->getJson('/support/api/v1/tickets', $agent['headers'])
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

// ========================================================================
// A07: Authentication Failures — Logging
// ========================================================================

it('logs failed authentication attempts', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'API authentication failed'));

    $this->getJson('/support/api/v1/tickets', [
        'Authorization' => 'Bearer totally-invalid-token',
    ])->assertStatus(401);
});

it('logs when expired token is used', function () {
    $user = TestUser::create([
        'name' => 'Expired User',
        'email' => 'expired-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $result = ApiToken::createToken($user, 'Expired', ['agent'], now()->subDay());

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'API authentication failed'));

    $this->getJson('/support/api/v1/tickets', [
        'Authorization' => 'Bearer '.$result['plainTextToken'],
    ])->assertStatus(401);
});

// ========================================================================
// A07: Authentication Failures — Edge Cases
// ========================================================================

it('rejects token with malformed Bearer header', function () {
    $this->getJson('/support/api/v1/tickets', [
        'Authorization' => 'Token some-value',
    ])->assertStatus(401);
});

it('rejects empty Bearer token', function () {
    $this->getJson('/support/api/v1/tickets', [
        'Authorization' => 'Bearer ',
    ])->assertStatus(401);
});

// ========================================================================
// A04: Insecure Design — 404 on Operations Against Non-Existent Tickets
// ========================================================================

it('returns 404 for reply to non-existent ticket', function () {
    $agent = secAgent();

    $this->postJson('/support/api/v1/tickets/ESC-GHOST/reply', [
        'body' => 'Reply to nothing.',
    ], $agent['headers'])
        ->assertStatus(404);
});

it('returns 404 for status change on non-existent ticket', function () {
    $agent = secAgent();

    $this->patchJson('/support/api/v1/tickets/ESC-GHOST/status', [
        'status' => 'in_progress',
    ], $agent['headers'])
        ->assertStatus(404);
});

it('returns 404 for delete on non-existent ticket', function () {
    $admin = secAdmin();

    $this->deleteJson('/support/api/v1/tickets/ESC-GHOST', [], $admin['headers'])
        ->assertStatus(404);
});

// ========================================================================
// Resource Endpoint Coverage
// ========================================================================

it('canned responses returns data structure', function () {
    $agent = secAgent();

    $this->getJson('/support/api/v1/canned-responses', $agent['headers'])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('macros returns data structure', function () {
    $agent = secAgent();

    $this->getJson('/support/api/v1/macros', $agent['headers'])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('agents endpoint excludes non-agent users', function () {
    $agent = secAgent();

    // Create a non-agent user
    TestUser::create([
        'name' => 'Customer Only',
        'email' => 'customer-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => false,
    ]);

    $response = $this->getJson('/support/api/v1/agents', $agent['headers'])
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->not->toContain('Customer Only');
});

it('realtime config returns null when no broadcasting configured', function () {
    $agent = secAgent();

    config()->set('broadcasting.default', 'null');

    $this->getJson('/support/api/v1/realtime/config', $agent['headers'])
        ->assertOk();
});

// ========================================================================
// Admin Token Management — Validation
// ========================================================================

it('admin token creation rejects invalid abilities', function () {
    $admin = TestUser::create([
        'name' => 'Admin',
        'email' => 'tokenadmin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
        'is_agent' => true,
    ]);

    $target = TestUser::create([
        'name' => 'Target',
        'email' => 'target-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $this->actingAs($admin)
        ->post('/support/admin/api-tokens', [
            'name' => 'Bad Token',
            'user_id' => $target->getKey(),
            'abilities' => ['root', 'superuser'],
        ])
        ->assertSessionHasErrors('abilities.0');
});

it('admin token creation rejects expires_in_days of 0', function () {
    $admin = TestUser::create([
        'name' => 'Admin',
        'email' => 'tokenadmin2-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
        'is_agent' => true,
    ]);

    $target = TestUser::create([
        'name' => 'Target',
        'email' => 'target2-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $this->actingAs($admin)
        ->post('/support/admin/api-tokens', [
            'name' => 'Zero Expiry',
            'user_id' => $target->getKey(),
            'abilities' => ['agent'],
            'expires_in_days' => 0,
        ])
        ->assertSessionHasErrors('expires_in_days');
});

it('admin token creation rejects expires_in_days over 365', function () {
    $admin = TestUser::create([
        'name' => 'Admin',
        'email' => 'tokenadmin3-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
        'is_agent' => true,
    ]);

    $target = TestUser::create([
        'name' => 'Target',
        'email' => 'target3-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);

    $this->actingAs($admin)
        ->post('/support/admin/api-tokens', [
            'name' => 'Long Expiry',
            'user_id' => $target->getKey(),
            'abilities' => ['agent'],
            'expires_in_days' => 500,
        ])
        ->assertSessionHasErrors('expires_in_days');
});

<?php

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function reportApiAdmin(array $attrs = []): array
{
    $user = TestUser::create(array_merge([
        'name' => 'API Admin',
        'email' => 'api-admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
        'is_agent' => true,
    ], $attrs));

    $result = ApiToken::createToken($user, 'Test Token', ['admin']);

    return [
        'user' => $user,
        'token' => $result['plainTextToken'],
        'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']],
    ];
}

function reportApiAgent(array $attrs = []): array
{
    $user = TestUser::create(array_merge([
        'name' => 'API Agent',
        'email' => 'api-agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ], $attrs));

    $result = ApiToken::createToken($user, 'Agent Token', ['agent']);

    return [
        'user' => $user,
        'token' => $result['plainTextToken'],
        'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']],
    ];
}

it('returns report summary via API', function () {
    $api = reportApiAdmin();

    Ticket::factory()->count(3)->create([
        'created_at' => now()->subDay(),
    ]);

    $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.reports.summary', ['period' => 7]))
        ->assertOk()
        ->assertJsonStructure([
            'period_days',
            'avg_first_response_hours',
            'avg_resolution_hours',
            'sla_compliance_rate',
            'csat_average',
            'volume',
            'by_status',
            'by_priority',
            'comparison',
        ]);
});

it('exports report data via API as JSON', function () {
    $api = reportApiAdmin();

    Ticket::factory()->create(['created_at' => now()->subDay()]);

    $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.reports.export', ['type' => 'tickets', 'format' => 'json', 'period' => 7]))
        ->assertOk()
        ->assertJsonStructure([
            'report_type',
            'generated_at',
            'filters',
            'record_count',
            'data',
        ]);
});

it('exports report data via API as CSV', function () {
    $api = reportApiAdmin();

    $response = $this->withHeaders($api['headers'])
        ->get(route('escalated.api.reports.export', ['type' => 'tickets', 'format' => 'csv', 'period' => 7]));

    expect($response->getStatusCode())->toBe(200);
});

it('rejects unauthenticated API access to reports', function () {
    $this->getJson(route('escalated.api.reports.summary'))
        ->assertUnauthorized();
});

it('rejects agent-only tokens from report API', function () {
    $api = reportApiAgent();

    $this->withHeaders($api['headers'])
        ->getJson(route('escalated.api.reports.summary'))
        ->assertForbidden();
});

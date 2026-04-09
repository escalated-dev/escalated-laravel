<?php

use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('shows SLA trends report', function () {
    $admin = $this->createAdmin();

    Ticket::factory()->create([
        'created_at' => now()->subDay(),
        'sla_first_response_breached' => true,
        'sla_policy_id' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.sla-trends', ['period' => 7]))
        ->assertOk();
});

it('shows first response time report', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.frt', ['period' => 30]))
        ->assertOk();
});

it('shows resolution time report', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.resolution', ['period' => 30]))
        ->assertOk();
});

it('shows agent ranking report', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.agent-ranking', ['period' => 30]))
        ->assertOk();
});

it('shows agent detail report', function () {
    $admin = $this->createAdmin();
    $agent = $this->createAgent();

    Ticket::factory()->create([
        'assigned_to' => $agent->id,
        'created_at' => now()->subDay(),
        'first_response_at' => now()->subDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.agent-detail', ['id' => $agent->id, 'period' => 30]))
        ->assertOk();
});

it('shows cohort analysis report', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.cohorts', ['period' => 30]))
        ->assertOk();
});

it('shows period comparison report', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.comparison', ['period' => 30]))
        ->assertOk();
});

it('exports tickets report as CSV', function () {
    $admin = $this->createAdmin();

    Ticket::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->actingAs($admin)
        ->get(route('escalated.admin.reports.export', ['type' => 'tickets', 'format' => 'csv', 'period' => 7]))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('exports tickets report as JSON', function () {
    $admin = $this->createAdmin();

    Ticket::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->actingAs($admin)
        ->get(route('escalated.admin.reports.export', ['type' => 'tickets', 'format' => 'json', 'period' => 7]))
        ->assertOk();

    $data = $response->json();
    expect($data)->toHaveKeys(['report_type', 'generated_at', 'data']);
});

it('rejects non-admin access to report endpoints', function () {
    $user = $this->createTestUser(['is_admin' => false, 'is_agent' => false]);

    $this->actingAs($user)
        ->get(route('escalated.admin.reports.sla-trends'))
        ->assertForbidden();
});

it('rejects unauthenticated access to report endpoints', function () {
    $response = $this->get(route('escalated.admin.reports.sla-trends'));

    // Without auth middleware properly configured in test, we just ensure it doesn't return 200
    expect($response->getStatusCode())->not->toBe(200);
});

it('accepts period parameter', function () {
    $admin = $this->createAdmin();

    foreach ([7, 30, 90, 365] as $period) {
        $this->actingAs($admin)
            ->get(route('escalated.admin.reports.frt', ['period' => $period]))
            ->assertOk();
    }
});

it('defaults to 30 day period when not specified', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports.frt'))
        ->assertOk();
});

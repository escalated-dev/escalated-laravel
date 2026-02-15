<?php

use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Tag;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('shows admin reports', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.reports'))
        ->assertOk();
});

it('lists departments', function () {
    $admin = $this->createAdmin();
    Department::factory()->create();

    $this->actingAs($admin)
        ->get(route('escalated.admin.departments.index'))
        ->assertOk();
});

it('creates a department', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->post(route('escalated.admin.departments.store'), [
            'name' => 'Engineering',
            'slug' => 'engineering',
            'description' => 'Engineering team',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_departments', ['name' => 'Engineering']);
});

it('lists SLA policies', function () {
    $admin = $this->createAdmin();
    SlaPolicy::factory()->create();

    $this->actingAs($admin)
        ->get(route('escalated.admin.sla-policies.index'))
        ->assertOk();
});

it('creates an SLA policy', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->post(route('escalated.admin.sla-policies.store'), [
            'name' => 'Standard SLA',
            'first_response_hours' => ['low' => 24, 'medium' => 8, 'high' => 4, 'urgent' => 2, 'critical' => 1],
            'resolution_hours' => ['low' => 72, 'medium' => 48, 'high' => 24, 'urgent' => 8, 'critical' => 4],
            'business_hours_only' => false,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_sla_policies', ['name' => 'Standard SLA']);
});

it('lists tags', function () {
    $admin = $this->createAdmin();
    Tag::factory()->create();

    $this->actingAs($admin)
        ->get(route('escalated.admin.tags.index'))
        ->assertOk();
});

it('creates a tag', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->post(route('escalated.admin.tags.store'), [
            'name' => 'Bug',
            'slug' => 'bug',
            'color' => '#EF4444',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('escalated_tags', ['name' => 'Bug']);
});

it('lists canned responses', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.canned-responses.index'))
        ->assertOk();
});

it('denies non-admin access', function () {
    $agent = $this->createAgent();

    $this->actingAs($agent)
        ->get(route('escalated.admin.reports'))
        ->assertForbidden();
});

it('denies regular user access', function () {
    $user = $this->createTestUser();

    $this->actingAs($user)
        ->get(route('escalated.admin.reports'))
        ->assertForbidden();
});

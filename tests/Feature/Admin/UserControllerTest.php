<?php

use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('lists users with their admin/agent flags for an admin', function () {
    $admin = $this->createAdmin(['email' => 'admin@example.com']);
    $this->createTestUser(['email' => 'customer@example.com']);
    $this->createAgent(['email' => 'agent@example.com']);

    $response = $this->actingAs($admin)
        ->get(route('escalated.admin.users.index'))
        ->assertOk();

    $users = collect($response->viewData('page')['props']['users']['data'] ?? [])
        ->pluck('email')->all();
    expect($users)->toContain('admin@example.com');
    expect($users)->toContain('customer@example.com');
    expect($users)->toContain('agent@example.com');
});

it('blocks non-admins from the user list', function () {
    $agent = $this->createAgent(['email' => 'agent@example.com']);

    $this->actingAs($agent)
        ->get(route('escalated.admin.users.index'))
        ->assertForbidden();
});

it('promotes a user to admin via the panel', function () {
    $admin = $this->createAdmin(['email' => 'admin@example.com']);
    $target = $this->createTestUser(['email' => 'someone@example.com']);

    $this->actingAs($admin)
        ->patch(route('escalated.admin.users.role', $target->id), [
            'role' => 'admin',
            'value' => true,
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->is_admin)->toBeTrue();
    expect($target->is_agent)->toBeTrue();
});

it('promotes a user to agent only', function () {
    $admin = $this->createAdmin(['email' => 'admin@example.com']);
    $target = $this->createTestUser(['email' => 'someone@example.com']);

    $this->actingAs($admin)
        ->patch(route('escalated.admin.users.role', $target->id), [
            'role' => 'agent',
            'value' => true,
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->is_agent)->toBeTrue();
    expect($target->is_admin)->toBeFalse();
});

it('prevents admins from demoting themselves', function () {
    $admin = $this->createAdmin(['email' => 'admin@example.com']);

    $this->actingAs($admin)
        ->patch(route('escalated.admin.users.role', $admin->id), [
            'role' => 'admin',
            'value' => false,
        ])
        ->assertRedirect();

    $admin->refresh();
    expect($admin->is_admin)->toBeTrue();
});

it('demotes an admin and turns off agent in one step', function () {
    $admin = $this->createAdmin(['email' => 'admin@example.com']);
    $target = $this->createAdmin(['email' => 'someone@example.com']);

    $this->actingAs($admin)
        ->patch(route('escalated.admin.users.role', $target->id), [
            'role' => 'agent',
            'value' => false,
        ])
        ->assertRedirect();

    $target->refresh();
    expect($target->is_agent)->toBeFalse();
    expect($target->is_admin)->toBeFalse();
});

it('filters users by search term', function () {
    $admin = $this->createAdmin(['email' => 'admin@example.com']);
    $this->createTestUser(['email' => 'jane@acme.test']);
    $this->createTestUser(['email' => 'bob@globex.test']);

    $response = $this->actingAs($admin)
        ->get(route('escalated.admin.users.index', ['search' => 'acme']))
        ->assertOk();

    $emails = collect($response->viewData('page')['props']['users']['data'] ?? [])
        ->pluck('email')->all();
    expect($emails)->toContain('jane@acme.test');
    expect($emails)->not->toContain('bob@globex.test');
});

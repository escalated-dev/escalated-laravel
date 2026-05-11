<?php

use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('shows the sso settings page', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->get(route('escalated.admin.settings.sso'))
        ->assertOk();
});

it('stores oauth configuration', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->post(route('escalated.admin.settings.sso.update'), [
            'sso_provider' => 'oauth',
            'sso_oauth_authorize_url' => 'https://id.example.com/oauth/authorize',
            'sso_oauth_token_url' => 'https://id.example.com/oauth/token',
            'sso_oauth_userinfo_url' => 'https://id.example.com/oauth/userinfo',
            'sso_oauth_client_id' => 'client-id',
            'sso_oauth_client_secret' => 'client-secret',
            'sso_oauth_scopes' => 'openid profile email',
            'sso_attr_email' => 'email',
            'sso_attr_name' => 'name',
            'sso_attr_role' => 'role',
        ])
        ->assertRedirect();

    expect(EscalatedSettings::get('sso_provider'))->toBe('oauth');
    expect(EscalatedSettings::get('sso_oauth_token_url'))->toBe('https://id.example.com/oauth/token');
});

it('requires oauth endpoints and client id when oauth is enabled', function () {
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->from(route('escalated.admin.settings.sso'))
        ->post(route('escalated.admin.settings.sso.update'), [
            'sso_provider' => 'oauth',
        ])
        ->assertSessionHasErrors([
            'sso_oauth_authorize_url',
            'sso_oauth_token_url',
            'sso_oauth_userinfo_url',
            'sso_oauth_client_id',
        ]);
});

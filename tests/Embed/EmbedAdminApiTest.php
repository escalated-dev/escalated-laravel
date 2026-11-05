<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Tests\EmbedApiTestCase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

/*
 * Escalated::embedAdmin() (ADR 2026-11-05-embeddable-admin), called from a
 * host service provider's register(), is the same as the config keys.
 */
uses(EmbedApiTestCase::class);

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('writes the embed config', function () {
    expect(config('escalated.ui.embed'))->toMatchArray([
        'enabled' => true,
        'mode' => 'layout',
        'prefix' => '/console/support/',
        'layout' => 'Layouts/ConsoleLayout',
        'replace_admin_gate' => false,
        'root_view' => null,
        'frame_ancestors' => [],
    ]);
});

it('mounts the admin under the prefix given, trimmed', function () {
    $this->actingAs($this->createAdmin())
        ->get('/console/support/tags')
        ->assertOk()
        ->assertHeader('X-Host-Admin', '1')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('escalated.embed.base_url', '/console/support')
            ->where('escalated.embed.layout', 'Layouts/ConsoleLayout'));
});

it('refuses an unknown mode', function () {
    Escalated::embedAdmin(mode: 'popup');
})->throws(InvalidArgumentException::class);

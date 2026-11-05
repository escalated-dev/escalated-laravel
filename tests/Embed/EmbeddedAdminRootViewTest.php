<?php

use Escalated\Laravel\Tests\EmbedRootViewTestCase;
use Illuminate\Support\Facades\Gate;

/*
 * A server-rendered host (ADR 2026-11-05-embeddable-admin): its admin template
 * becomes the Inertia root view for embedded pages, and only for them.
 */
uses(EmbedRootViewTestCase::class);

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('renders embedded pages in the host\'s root view', function () {
    $this->actingAs($this->createAdmin())
        ->get('/backoffice/helpdesk/tags')
        ->assertOk()
        ->assertSee('data-host-admin-sidebar', false)
        ->assertSee('Escalated\/Admin\/Tags\/Index', false);
});

it('keeps the normal root view everywhere else', function () {
    $this->actingAs($this->createAdmin(['is_agent' => true]))
        ->get('/support/agent')
        ->assertOk()
        ->assertDontSee('data-host-admin-sidebar', false);
});

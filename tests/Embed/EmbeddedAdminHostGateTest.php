<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Exceptions\UserNotTicketableException;
use Escalated\Laravel\Http\Middleware\EnforceRoutePermissions;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Escalated\Laravel\Http\Middleware\EnsureTicketableActor;
use Escalated\Laravel\Http\Middleware\RequireTwoFactor;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\EmbedHostGateTestCase;
use Escalated\Laravel\Tests\Fixtures\EmbedHostMiddleware;
use Escalated\Laravel\Tests\Fixtures\PlainUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
 * replace_admin_gate (ADR 2026-11-05-embeddable-admin): only when the host
 * opts in does its own middleware replace Escalated's admin gate, and only on
 * the embedded admin routes. Two-factor and route permissions still apply.
 */
uses(EmbedHostGateTestCase::class);

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('lets the host gate alone decide who reaches the embedded admin', function () {
    $owner = $this->createTestUser(['email' => EmbedHostMiddleware::OWNER_EMAIL]);

    $this->actingAs($owner)->get('/backoffice/helpdesk/tags')->assertOk();
    expect(Escalated::adminNavigation($owner))->not->toBeEmpty();
});

it('refuses an Escalated admin the host gate refuses', function () {
    $this->actingAs($this->createAdmin())->get('/backoffice/helpdesk/tags')->assertForbidden();
});

it('drops only the admin gate from the embedded routes', function () {
    $middleware = Route::getRoutes()->getByName('escalated.admin.tags.index')->gatherMiddleware();

    expect($middleware)->not->toContain(EnsureIsAdmin::class)
        ->and($middleware)->toContain(RequireTwoFactor::class, EnforceRoutePermissions::class, EmbedHostMiddleware::class.':owner');
});

it('keeps Escalated\'s gates on the agent panel', function () {
    $owner = $this->createTestUser(['email' => EmbedHostMiddleware::OWNER_EMAIL]);

    $this->actingAs($owner)->get('/support/agent')->assertForbidden();
});

it('keeps the Ticketable check in place of the admin gate', function () {
    $middleware = Route::getRoutes()->getByName('escalated.admin.tickets.reply')->gatherMiddleware();

    expect($middleware)->toContain(EnsureTicketableActor::class);
});

it('explains a missing Ticketable contract on an embedded admin action', function () {
    $owner = PlainUser::create([
        'name' => 'Owner', 'email' => EmbedHostMiddleware::OWNER_EMAIL, 'password' => 'x',
        'is_agent' => true, 'is_admin' => true,
    ]);
    $ticket = Ticket::factory()->create();

    $this->withoutExceptionHandling();
    $thrown = null;
    try {
        $this->actingAs($owner)->post('/backoffice/helpdesk/tickets/'.$ticket->reference.'/reply', ['body' => 'Hello']);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(UserNotTicketableException::class)
        ->and($thrown->getMessage())->toContain(PlainUser::class);
});

it('still lets a Ticketable host owner act in the embedded admin', function () {
    $owner = $this->createTestUser(['email' => EmbedHostMiddleware::OWNER_EMAIL, 'is_agent' => true, 'is_admin' => true]);
    $ticket = Ticket::factory()->create();

    $this->actingAs($owner)
        ->post('/backoffice/helpdesk/tickets/'.$ticket->reference.'/reply', ['body' => 'Hello'])
        ->assertRedirect();
});

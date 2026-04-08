<?php

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->policy = new TicketPolicy;
});

it('allows any authenticated user to view tickets', function () {
    $user = $this->createTestUser();
    expect($this->policy->viewAny($user))->toBeTrue();
});

it('allows any authenticated user to create tickets', function () {
    $user = $this->createTestUser();
    expect($this->policy->create($user))->toBeTrue();
});

it('allows ticket requester to view their ticket', function () {
    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->id,
    ]);

    expect($this->policy->view($user, $ticket))->toBeTrue();
});

it('allows agents to view any ticket', function () {
    $agent = $this->createAgent();

    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);

    $ticket = Ticket::factory()->create();

    expect($this->policy->view($agent, $ticket))->toBeTrue();
});

it('allows agents to update tickets', function () {
    $agent = $this->createAgent();

    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);

    $ticket = Ticket::factory()->create();

    expect($this->policy->update($agent, $ticket))->toBeTrue();
});

it('prevents regular users from updating tickets', function () {
    $user = $this->createTestUser();

    Gate::define('escalated-agent', fn ($u) => $u->is_agent || $u->is_admin);

    $ticket = Ticket::factory()->create();

    expect($this->policy->update($user, $ticket))->toBeFalse();
});

it('allows requester to reply to their ticket', function () {
    $user = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $user->getMorphClass(),
        'requester_id' => $user->id,
    ]);

    expect($this->policy->reply($user, $ticket))->toBeTrue();
});

it('only allows agents to add internal notes', function () {
    $user = $this->createTestUser();
    $agent = $this->createAgent();

    Gate::define('escalated-agent', fn ($u) => $u->is_agent || $u->is_admin);

    $ticket = Ticket::factory()->create();

    expect($this->policy->addNote($user, $ticket))->toBeFalse();
    expect($this->policy->addNote($agent, $ticket))->toBeTrue();
});

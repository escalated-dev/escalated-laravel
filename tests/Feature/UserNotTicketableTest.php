<?php

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Exceptions\UserNotTicketableException;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\PlainUser;
use Illuminate\Support\Facades\Gate;

/*
 * A host whose User does not implement Ticketable used to get a raw
 * TypeError from whichever service a staff action reached first. The staff
 * middleware now stops the request with an exception that names the
 * contract and how to add it.
 */

beforeEach(function () {
    // A host that lets every signed-in user through, as a host with a
    // permissive or missing setup might.
    Gate::define('escalated-agent', fn () => true);
    Gate::define('escalated-admin', fn () => true);
});

function plainStaffUser(): PlainUser
{
    return PlainUser::create([
        'name' => 'Plain', 'email' => 'plain@example.com', 'password' => 'x',
        'is_agent' => true, 'is_admin' => true,
    ]);
}

it('explains the missing Ticketable contract on a staff action', function (string $route) {
    $this->withoutExceptionHandling();
    $ticket = Ticket::factory()->create();

    $thrown = null;
    try {
        $this->actingAs(plainStaffUser())->post(route($route, $ticket->reference), ['body' => 'Hello']);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(UserNotTicketableException::class)
        ->and($thrown->getMessage())->toContain(PlainUser::class)
        ->and($thrown->getMessage())->toContain(Ticketable::class)
        ->and($thrown->getMessage())->toContain('use HasTickets;')
        ->and($thrown->getMessage())->toContain('escalated:install');
})->with(['agent reply' => 'escalated.agent.tickets.reply', 'admin reply' => 'escalated.admin.tickets.reply']);

it('leaves reads alone and lets a Ticketable user act', function () {
    $ticket = Ticket::factory()->create();

    $this->actingAs(plainStaffUser())->get(route('escalated.agent.tickets.index'))->assertOk();

    $this->actingAs($this->createAgent())
        ->post(route('escalated.agent.tickets.reply', $ticket->reference), ['body' => 'Hello'])
        ->assertRedirect();
});

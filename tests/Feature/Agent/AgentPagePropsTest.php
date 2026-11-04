<?php

/*
 * The agent ticket pages are sent exactly the props the shared frontend's
 * components declare (tests/Fixtures/escalated-pages.json), so nothing the
 * page reads is missing and nothing sent is dropped on the root element.
 */

use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('sends the agent ticket pages every prop they declare, and nothing they do not', function (string $page) {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    $url = $page === 'Escalated/Agent/TicketIndex'
        ? route('escalated.agent.tickets.index')
        : route('escalated.agent.tickets.show', $ticket->reference);

    $response = $this->actingAs($agent)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get($url)
        ->assertOk();

    expect($response->json('component'))->toBe($page);

    $manifest = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/escalated-pages.json'), true, flags: JSON_THROW_ON_ERROR);
    $declared = $manifest['props'][$page]['props'];

    $shared = ['errors', 'auth', 'flash', 'escalated', 'ziggy'];
    $sent = array_values(array_diff(array_keys($response->json('props')), $shared));
    sort($sent);

    expect($sent)->toBe($declared);
})->with(['Escalated/Agent/TicketIndex', 'Escalated/Agent/TicketShow']);

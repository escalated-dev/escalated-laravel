<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['escalated.hosted.signing_secret' => 'whsec_site']);
    Cache::flush();
});

function cloudWebhook(object $test, array $body, ?string $secret = 'whsec_site'): TestResponse
{
    $json = json_encode($body);
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($secret !== null) {
        $server['HTTP_X_ESCALATED_SIGNATURE'] = 'sha256='.hash_hmac('sha256', $json, $secret);
    }

    return $test->call('POST', '/escalated/cloud/webhook', [], [], [], $server, $json);
}

/**
 * The projected ticket exactly as the cloud's TicketResource serialises it.
 */
function projectedTicket(Ticket $ticket, array $overrides = []): array
{
    return array_merge([
        'id' => 501,
        'ticket_number' => 7,
        'subject' => $ticket->subject,
        'description' => $ticket->description,
        'status' => 'open',
        'priority' => 'normal',
        'assigned_to' => null,
        'external_id' => $ticket->reference,
        'metadata' => ['origin' => 'synced', 'source_site_id' => 1],
    ], $overrides);
}

function cloudEvent(string $event, array $ticket, string $eventId = 'evt-1'): array
{
    return ['event' => $event, 'event_id' => $eventId, 'ticket' => $ticket, 'timestamp' => now()->toIso8601String()];
}

it('answers 503 until a signing secret is configured', function () {
    config(['escalated.hosted.signing_secret' => null]);
    $ticket = Ticket::factory()->create();

    cloudWebhook($this, cloudEvent('ticket.updated', projectedTicket($ticket)))->assertStatus(503);
});

it('rejects a missing or wrong signature', function () {
    $ticket = Ticket::factory()->create();

    cloudWebhook($this, cloudEvent('ticket.updated', projectedTicket($ticket)), null)->assertStatus(401);
    cloudWebhook($this, cloudEvent('ticket.updated', projectedTicket($ticket)), 'other')->assertStatus(401);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

it('applies a cloud status change through the local driver with the package vocabulary', function () {
    // The factory picks a random priority; pin it so the projection's
    // `normal` (→ medium) does not register as a second change.
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Medium]);

    cloudWebhook($this, cloudEvent('ticket.status_changed', projectedTicket($ticket, ['status' => 'waiting'])))
        ->assertOk()
        ->assertJson(['received' => true, 'applied' => true, 'changes' => ['status']]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::WaitingOnCustomer);
});

it('applies subject and priority changes from ticket.updated', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Old subject', 'priority' => TicketPriority::Medium]);

    cloudWebhook($this, cloudEvent('ticket.updated', projectedTicket($ticket, ['subject' => 'Renamed in the cloud', 'priority' => 'urgent'])))
        ->assertOk()
        ->assertJsonPath('applied', true);

    $fresh = $ticket->fresh();

    expect($fresh->subject)->toBe('Renamed in the cloud')
        ->and($fresh->priority)->toBe(TicketPriority::Urgent);
});

it('does nothing when the projection already matches', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Medium, 'status' => TicketStatus::Open]);

    cloudWebhook($this, cloudEvent('ticket.updated', projectedTicket($ticket)))
        ->assertOk()
        ->assertJson(['applied' => false, 'changes' => []]);
});

it('ignores events that carry no site changes and tickets that did not originate here', function () {
    $ticket = Ticket::factory()->create();

    cloudWebhook($this, cloudEvent('ticket.created', projectedTicket($ticket, ['status' => 'closed'])))->assertStatus(202);
    cloudWebhook($this, cloudEvent('ticket.updated', projectedTicket($ticket, ['external_id' => null, 'status' => 'closed']), 'evt-2'))->assertStatus(202);
    cloudWebhook($this, cloudEvent('ticket.updated', projectedTicket($ticket, ['external_id' => 'ESC-99999', 'status' => 'closed']), 'evt-3'))->assertStatus(202);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

it('treats a redelivered event id as a replay', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => TicketPriority::Medium]);
    $event = cloudEvent('ticket.status_changed', projectedTicket($ticket, ['status' => 'in_progress']), 'evt-replay');

    cloudWebhook($this, $event)->assertOk()->assertJsonPath('applied', true);
    cloudWebhook($this, $event)->assertOk()->assertJson(['applied' => false, 'replay' => true]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
});

it('reports a transition the site does not allow instead of forcing it', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed]);

    $response = cloudWebhook($this, cloudEvent('ticket.status_changed', projectedTicket($ticket, ['status' => 'in_progress'])));

    $response->assertOk()->assertJsonPath('applied', false);

    expect($response->json('reason'))->toBeString()
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Closed);
});

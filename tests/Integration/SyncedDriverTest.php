<?php

use Escalated\Laravel\Drivers\SyncedDriver;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Client\HostedApiClient;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AttachmentService;
use Escalated\Laravel\Services\MentionService;
use Illuminate\Support\Facades\Http;

it('synced driver creates ticket locally and syncs to cloud', function () {
    Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

    config(['escalated.mode' => 'synced']);
    config(['escalated.hosted.api_url' => 'https://cloud.escalated.dev/api/v1']);
    config(['escalated.hosted.api_key' => 'test-key']);
    config(['escalated.transitions' => [
        'open' => ['in_progress', 'resolved', 'closed'],
    ]]);

    $driver = new SyncedDriver(
        app(AttachmentService::class),
        app(MentionService::class),
        new HostedApiClient,
    );

    $customer = $this->createTestUser();

    $ticket = $driver->createTicket($customer, [
        'subject' => 'Synced test ticket',
        'description' => 'Testing synced driver.',
    ]);

    expect($ticket)->toBeInstanceOf(Ticket::class);
    expect($ticket->subject)->toBe('Synced test ticket');
    expect($ticket->status)->toBe(TicketStatus::Open);

    // Verify the ticket exists in local DB
    $this->assertDatabaseHas('escalated_tickets', [
        'subject' => 'Synced test ticket',
    ]);

    // Verify HTTP was called to sync
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/events');
    });
});

it('synced driver continues working if cloud is unreachable', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    config(['escalated.mode' => 'synced']);
    config(['escalated.hosted.api_url' => 'https://cloud.escalated.dev/api/v1']);
    config(['escalated.hosted.api_key' => 'test-key']);

    $driver = new SyncedDriver(
        app(AttachmentService::class),
        app(MentionService::class),
        new HostedApiClient,
    );

    $customer = $this->createTestUser();

    // Should not throw even if cloud fails
    $ticket = $driver->createTicket($customer, [
        'subject' => 'Offline test',
        'description' => 'Cloud is offline.',
    ]);

    expect($ticket)->toBeInstanceOf(Ticket::class);
    $this->assertDatabaseHas('escalated_tickets', [
        'subject' => 'Offline test',
    ]);
});

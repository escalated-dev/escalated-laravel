<?php

use Escalated\Laravel\Drivers\CloudDriver;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Client\HostedApiClient;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['escalated.mode' => 'cloud']);
    config(['escalated.hosted.api_url' => 'https://cloud.escalated.dev/api/v1']);
    config(['escalated.hosted.api_key' => 'test-key']);

    $this->driver = new CloudDriver(new HostedApiClient);
});

/**
 * A ticket exactly as cloud.escalated.dev's CommandController returns it.
 */
function cloudTicket(array $overrides = []): array
{
    return array_merge([
        'id' => 501,
        'account_id' => 9,
        'ticket_number' => 7,
        'subject' => 'Cannot export',
        'description' => 'The CSV export is empty.',
        'status' => 'open',
        'priority' => 'normal',
        'assigned_to' => null,
        'assignee' => null,
        'sla' => null,
        'requester_email' => 'dana@example.com',
        'requester_name' => 'Dana Requester',
        'created_by' => 3,
        'tags' => ['billing'],
        'metadata' => null,
        'external_id' => null,
        'ticket_subject_id' => null,
        'created_at' => '2026-09-14T10:00:00+00:00',
        'updated_at' => '2026-09-14T10:00:00+00:00',
    ], $overrides);
}

it('creates a ticket through the commands endpoint and hydrates the cloud response', function () {
    $customer = $this->createTestUser();

    Http::fake([
        'cloud.escalated.dev/api/v1/commands' => Http::response(cloudTicket([
            'metadata' => [
                'host_requester_type' => $customer->getMorphClass(),
                'host_requester_id' => $customer->getKey(),
            ],
        ])),
    ]);

    $ticket = $this->driver->createTicket($customer, [
        'subject' => 'Cannot export',
        'description' => 'The CSV export is empty.',
        'priority' => 'critical',
    ]);

    expect($ticket)->toBeInstanceOf(Ticket::class)
        ->and($ticket->exists)->toBeTrue()
        ->and($ticket->id)->toBe(501)
        ->and($ticket->reference)->toBe('7')
        ->and($ticket->priority)->toBe(TicketPriority::Medium)
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->requester_name)->toBe($customer->name)
        ->and($ticket->metadata['cloud_id'])->toBe(501)
        ->and($ticket->metadata['cloud_tags'])->toBe(['billing']);

    Http::assertSent(function (Request $request) use ($customer) {
        return str_ends_with($request->url(), '/commands')
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['command'] === 'tickets.create'
            && $request['payload']['subject'] === 'Cannot export'
            && $request['payload']['priority'] === 'urgent'
            && $request['payload']['requester_email'] === $customer->email
            && $request['payload']['metadata']['host_requester_id'] === $customer->getKey();
    });
});

it('falls back to a guest requester when the cloud ticket was not created by this host', function () {
    Http::fake(['cloud.escalated.dev/api/v1/tickets/501' => Http::response(['data' => cloudTicket()])]);

    $ticket = $this->driver->getTicket(501);

    expect($ticket->reference)->toBe('7')
        ->and($ticket->requester_name)->toBe('Dana Requester')
        ->and($ticket->requester_email)->toBe('dana@example.com');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://cloud.escalated.dev/api/v1/tickets/501');
});

it('transitions status with the cloud vocabulary and maps the response back', function () {
    Http::fake(['cloud.escalated.dev/api/v1/commands' => Http::response(cloudTicket(['status' => 'waiting']))]);

    $ticket = $this->driver->getTicketFromArray(cloudTicket());

    $updated = $this->driver->transitionStatus($ticket, TicketStatus::WaitingOnCustomer);

    expect($updated->status)->toBe(TicketStatus::WaitingOnCustomer);

    Http::assertSent(fn (Request $request) => $request['command'] === 'tickets.transition'
        && $request['payload']['reference'] === '7'
        && $request['payload']['status'] === 'waiting');
});

it('changes priority with the cloud vocabulary', function () {
    Http::fake(['cloud.escalated.dev/api/v1/commands' => Http::response(cloudTicket(['priority' => 'normal']))]);

    $ticket = $this->driver->getTicketFromArray(cloudTicket());

    $updated = $this->driver->changePriority($ticket, TicketPriority::Medium);

    expect($updated->priority)->toBe(TicketPriority::Medium);

    Http::assertSent(fn (Request $request) => $request['command'] === 'tickets.change_priority'
        && $request['payload']['priority'] === 'normal');
});

it('hydrates a reply from the cloud comment shape', function () {
    Http::fake(['cloud.escalated.dev/api/v1/commands' => Http::response([
        'id' => 88,
        'account_id' => 9,
        'ticket_id' => 501,
        'user_id' => 3,
        'body' => 'Thanks, looking into it.',
        'is_internal' => true,
        'author_name' => 'Agent Smith',
        'author_email' => 'smith@example.com',
        'external_id' => null,
        'created_at' => '2026-09-14T10:05:00+00:00',
        'updated_at' => '2026-09-14T10:05:00+00:00',
    ])]);

    $agent = $this->createTestUser(['is_agent' => true]);
    $ticket = $this->driver->getTicketFromArray(cloudTicket());

    $reply = $this->driver->addReply($ticket, $agent, 'Thanks, looking into it.', true);

    expect($reply)->toBeInstanceOf(Reply::class)
        ->and($reply->exists)->toBeTrue()
        ->and($reply->id)->toBe(88)
        ->and($reply->ticket_id)->toBe(501)
        ->and($reply->body)->toBe('Thanks, looking into it.')
        ->and($reply->is_internal_note)->toBeTrue()
        ->and($reply->metadata['author_name'])->toBe('Agent Smith');

    Http::assertSent(fn (Request $request) => $request['command'] === 'tickets.reply'
        && $request['payload']['is_note'] === true
        && $request['payload']['reference'] === '7');
});

it('lists tickets from a paginated resource collection', function () {
    Http::fake(['cloud.escalated.dev/api/v1/tickets*' => Http::response([
        'data' => [cloudTicket(), cloudTicket(['id' => 502, 'ticket_number' => 8, 'subject' => 'Second'])],
        'links' => ['first' => 'x', 'last' => 'x', 'prev' => null, 'next' => null],
        'meta' => ['current_page' => 1, 'per_page' => 15, 'total' => 2, 'last_page' => 1],
    ])]);

    $page = $this->driver->listTickets(['status' => 'open']);

    expect($page->total())->toBe(2)
        ->and($page->perPage())->toBe(15)
        ->and($page->currentPage())->toBe(1)
        ->and($page->items()[0])->toBeInstanceOf(Ticket::class)
        ->and($page->items()[1]->reference)->toBe('8');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/tickets?status=open'));
});

it('raises a RequestException when the cloud responds with an error', function () {
    Http::fake(['cloud.escalated.dev/api/v1/commands' => Http::response(['message' => 'Server Error'], 500)]);

    $customer = $this->createTestUser();

    expect(fn () => $this->driver->createTicket($customer, ['subject' => 'x', 'description' => 'y']))
        ->toThrow(RequestException::class);
});

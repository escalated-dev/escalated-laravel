<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;

/*
 * Guest-facing ticket payloads are built from explicit allow-lists. Staff
 * appear by display name only, and internal fields such as metadata,
 * external_reference and chat_metadata never reach the guest.
 */

function exposedGuestTicket(object $test): array
{
    $agent = (fn () => $this->createAgent(['name' => 'Agent Smith', 'email' => 'agent.smith@example.com']))->call($test);
    $ticket = Ticket::factory()->create([
        'guest_name' => 'Guest Person',
        'guest_email' => 'guest@example.com',
        'assigned_to' => $agent->id,
        'status' => TicketStatus::Open,
        'metadata' => ['internal_score' => 'vip-risk'],
        'chat_metadata' => ['visitor_ip' => '203.0.113.9'],
        'external_reference' => 'ORDER-SECRET-1',
    ]);
    Reply::create([
        'ticket_id' => $ticket->id, 'author_type' => $agent->getMorphClass(), 'author_id' => $agent->id,
        'body' => 'Agent answer', 'is_internal_note' => false, 'type' => 'reply',
        'metadata' => ['source' => 'internal-tool'],
    ]);
    Reply::create([
        'ticket_id' => $ticket->id, 'author_type' => $agent->getMorphClass(), 'author_id' => $agent->id,
        'body' => 'Private note', 'is_internal_note' => true, 'type' => 'note',
    ]);
    Reply::create([
        'ticket_id' => $ticket->id, 'author_type' => null, 'author_id' => null,
        'body' => 'Guest follow-up', 'is_internal_note' => false, 'type' => 'reply',
    ]);

    return [$ticket, (fn () => $this->guestToken($ticket))->call($test)];
}

function flattenKeys(array $data, string $prefix = ''): array
{
    $keys = [];
    foreach ($data as $key => $value) {
        $path = is_int($key) ? $prefix.'*' : $prefix.$key;
        $keys[] = $path;
        if (is_array($value)) {
            $keys = [...$keys, ...flattenKeys($value, $path.'.')];
        }
    }

    return array_values(array_unique($keys));
}

it('renders the web guest ticket from an allow-list', function () {
    [$ticket, $token] = exposedGuestTicket($this);

    $ticketProp = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.guest.tickets.show', $token))
        ->assertOk()
        ->json('props.ticket');

    expect(array_keys($ticketProp))->toEqualCanonicalizing([
        'reference', 'subject', 'description', 'status', 'priority', 'channel',
        'department', 'attachments', 'replies', 'satisfaction_rating',
        'guest_access_expires_at', 'resolved_at', 'closed_at', 'created_at', 'updated_at',
    ]);
    expect(count($ticketProp['replies']))->toBe(2);
    foreach ($ticketProp['replies'] as $reply) {
        expect(array_keys($reply))->toEqualCanonicalizing([
            'id', 'body', 'is_internal_note', 'is_pinned', 'author', 'attachments', 'created_at',
        ])->and(array_keys($reply['author']))->toBe(['name']);
    }

    $names = collect($ticketProp['replies'])->pluck('author.name')->all();
    expect($names)->toEqualCanonicalizing(['Agent Smith', 'Guest Person']);

    $json = json_encode($ticketProp);
    expect($json)->not->toContain('agent.smith@example.com')
        ->not->toContain('vip-risk')
        ->not->toContain('ORDER-SECRET-1')
        ->not->toContain('203.0.113.9')
        ->not->toContain('internal-tool')
        ->not->toContain('Private note');
});

it('returns the mobile guest ticket from an allow-list with staff reduced to display names', function () {
    [$ticket, $token] = exposedGuestTicket($this);

    $data = $this->getJson('/support/api/v1/mobile/guest/tickets/'.$token)->assertOk()->json('data');

    expect(array_keys($data))->toEqualCanonicalizing([
        'id', 'reference', 'guest_access_token', 'guest_access_expires_at', 'subject', 'description',
        'status', 'priority', 'channel', 'metadata', 'requester', 'assignee', 'department', 'tags',
        'replies', 'activities', 'sla', 'is_following', 'followers_count', 'resolved_at', 'closed_at',
        'created_at', 'updated_at',
    ]);
    expect($data['metadata'])->toBe([])
        ->and($data['requester'])->toBe(['name' => 'Guest Person', 'email' => 'guest@example.com'])
        ->and($data['assignee'])->toBe(['id' => 0, 'name' => 'Agent Smith', 'email' => '']);

    $agentReply = collect($data['replies'])->firstWhere('body', 'Agent answer');
    $guestReply = collect($data['replies'])->firstWhere('body', 'Guest follow-up');
    expect($agentReply['author'])->toBe(['id' => 0, 'name' => 'Agent Smith', 'email' => ''])
        ->and($guestReply['author'])->toBe(['id' => 0, 'name' => 'Guest Person', 'email' => 'guest@example.com']);

    $json = json_encode($data);
    expect($json)->not->toContain('agent.smith@example.com')
        ->not->toContain('vip-risk')
        ->not->toContain('ORDER-SECRET-1')
        ->not->toContain('203.0.113.9')
        ->not->toContain('Private note');
});

it('keeps metadata a JSON object so mobile clients can still decode it', function () {
    [, $token] = exposedGuestTicket($this);

    $raw = $this->getJson('/support/api/v1/mobile/guest/tickets/'.$token)->assertOk()->getContent();

    expect($raw)->toContain('"metadata":{}');
});

it('shows authenticated mobile customers their own identity but only staff display names', function () {
    $customer = $this->createTestUser(['name' => 'Cust Omer', 'email' => 'customer@example.com']);
    $agent = $this->createAgent(['name' => 'Agent Smith', 'email' => 'agent.smith@example.com']);
    $ticket = Ticket::factory()->create([
        'requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id,
        'assigned_to' => $agent->id, 'metadata' => ['internal_score' => 'vip-risk'],
    ]);
    foreach ([[$agent, 'Agent answer'], [$customer, 'Customer follow-up']] as [$author, $body]) {
        Reply::create([
            'ticket_id' => $ticket->id, 'author_type' => $author->getMorphClass(), 'author_id' => $author->id,
            'body' => $body, 'is_internal_note' => false, 'type' => 'reply',
        ]);
    }

    $token = ApiToken::createToken($customer, 'Mobile Token', ['customer'])['plainTextToken'];
    $data = $this->withHeaders(['Authorization' => 'Bearer '.$token])
        ->getJson(route('escalated.api.mobile.tickets.show', $ticket->reference))
        ->assertOk()->json('data');

    $customerReply = collect($data['replies'])->firstWhere('body', 'Customer follow-up');
    expect($customerReply['author'])->toBe(['id' => $customer->id, 'name' => 'Cust Omer', 'email' => 'customer@example.com'])
        ->and(collect($data['replies'])->firstWhere('body', 'Agent answer')['author']['email'])->toBe('')
        ->and($data['assignee']['email'])->toBe('')
        ->and(json_encode($data))->not->toContain('vip-risk')->not->toContain('agent.smith@example.com');
});

it('renders the authenticated customer ticket page from an allow-list', function () {
    $customer = $this->createTestUser(['name' => 'Cust Omer', 'email' => 'customer@example.com']);
    $agent = $this->createAgent(['name' => 'Agent Smith', 'email' => 'agent.smith@example.com']);
    $ticket = Ticket::factory()->create([
        'requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id,
        'assigned_to' => $agent->id,
        'metadata' => ['internal_score' => 'vip-risk'],
        'chat_metadata' => ['visitor_ip' => '203.0.113.9'],
        'external_reference' => 'ORDER-SECRET-1',
    ]);
    Reply::create([
        'ticket_id' => $ticket->id, 'author_type' => $agent->getMorphClass(), 'author_id' => $agent->id,
        'body' => 'Agent answer', 'is_internal_note' => false, 'type' => 'reply',
        'metadata' => ['source' => 'internal-tool'],
    ]);
    Reply::create([
        'ticket_id' => $ticket->id, 'author_type' => $agent->getMorphClass(), 'author_id' => $agent->id,
        'body' => 'Private note', 'is_internal_note' => true, 'type' => 'note',
    ]);
    Reply::create([
        'ticket_id' => $ticket->id, 'author_type' => $customer->getMorphClass(), 'author_id' => $customer->id,
        'body' => 'Customer follow-up', 'is_internal_note' => false, 'type' => 'reply',
    ]);

    $ticketProp = $this->actingAs($customer)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.customer.tickets.show', $ticket->reference))
        ->assertOk()
        ->json('props.ticket');

    expect(array_keys($ticketProp))->toEqualCanonicalizing([
        'reference', 'subject', 'description', 'status', 'priority', 'channel',
        'department', 'attachments', 'replies', 'satisfaction_rating',
        'resolved_at', 'closed_at', 'created_at', 'updated_at',
    ]);
    expect(count($ticketProp['replies']))->toBe(2);
    foreach ($ticketProp['replies'] as $reply) {
        expect(array_keys($reply))->toEqualCanonicalizing([
            'id', 'body', 'is_internal_note', 'is_pinned', 'author', 'attachments', 'created_at',
        ])->and(array_keys($reply['author']))->toBe(['name']);
    }
    expect(collect($ticketProp['replies'])->pluck('author.name')->all())
        ->toEqualCanonicalizing(['Agent Smith', 'Cust Omer']);

    $json = json_encode($ticketProp);
    expect($json)->not->toContain('agent.smith@example.com')
        ->not->toContain('vip-risk')
        ->not->toContain('ORDER-SECRET-1')
        ->not->toContain('203.0.113.9')
        ->not->toContain('internal-tool')
        ->not->toContain('Private note');
});

it('tells the customer ticket page when the ticket was already rated', function () {
    $customer = $this->createTestUser();
    $ticket = Ticket::factory()->create([
        'requester_type' => $customer->getMorphClass(), 'requester_id' => $customer->id,
        'status' => TicketStatus::Resolved,
    ]);
    $ticket->satisfactionRating()->create([
        'rating' => 4, 'comment' => 'Thanks',
        'rated_by_type' => $customer->getMorphClass(), 'rated_by_id' => $customer->id,
    ]);

    $ticketProp = $this->actingAs($customer)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.customer.tickets.show', $ticket->reference))
        ->assertOk()
        ->json('props.ticket');

    expect($ticketProp['satisfaction_rating'])->toBe(['rating' => 4, 'comment' => 'Thanks']);
});

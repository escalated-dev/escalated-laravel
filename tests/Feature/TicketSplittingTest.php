<?php

use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketActivity;
use Escalated\Laravel\Models\TicketLink;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function createReplyForTicket(Ticket $ticket, array $attrs = []): Reply
{
    return Reply::create(array_merge([
        'ticket_id' => $ticket->id,
        'body' => 'Test reply body.',
        'is_internal_note' => false,
        'is_pinned' => false,
        'author_type' => null,
        'author_id' => null,
    ], $attrs));
}

function createTestTicket(array $attrs = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'reference' => 'TST-'.Str::random(8),
    ], $attrs));
}

it('creates a new ticket from reply content when splitting', function () {
    $source = createTestTicket(['subject' => 'Original ticket']);
    $reply = createReplyForTicket($source, ['body' => 'This reply should become a new ticket.']);

    $service = app(TicketService::class);
    $newTicket = $service->splitTicket($source, $reply);

    expect($newTicket)->toBeInstanceOf(Ticket::class);
    expect($newTicket->id)->not->toBe($source->id);
    expect($newTicket->description)->toBe('This reply should become a new ticket.');
    expect($newTicket->subject)->toContain('Split from');
    expect($newTicket->status)->toBe(TicketStatus::Open);
});

it('copies metadata from source ticket when splitting', function () {
    $source = createTestTicket([
        'priority' => TicketPriority::High,
        'requester_type' => null,
        'requester_id' => null,
        'department_id' => null,
        'guest_name' => 'John',
        'guest_email' => 'john@example.com',
    ]);

    $tag1 = Tag::factory()->create();
    $tag2 = Tag::factory()->create();
    $source->tags()->sync([$tag1->id, $tag2->id]);

    $reply = createReplyForTicket($source);

    $service = app(TicketService::class);
    $newTicket = $service->splitTicket($source, $reply);

    expect($newTicket->priority)->toBe(TicketPriority::High);
    expect($newTicket->guest_name)->toBe('John');
    expect($newTicket->guest_email)->toBe('john@example.com');
    expect($newTicket->tags()->pluck('id')->sort()->values()->all())
        ->toBe([$tag1->id, $tag2->id]);
});

it('creates a TicketLink between source and new ticket', function () {
    $source = createTestTicket();
    $reply = createReplyForTicket($source);

    $service = app(TicketService::class);
    $newTicket = $service->splitTicket($source, $reply);

    $link = TicketLink::where('parent_ticket_id', $source->id)
        ->where('child_ticket_id', $newTicket->id)
        ->first();

    expect($link)->not->toBeNull();
    expect($link->link_type)->toBe('split');
});

it('logs activity on both tickets when splitting', function () {
    $source = createTestTicket();
    $reply = createReplyForTicket($source);

    $service = app(TicketService::class);
    $newTicket = $service->splitTicket($source, $reply);

    $sourceActivity = TicketActivity::where('ticket_id', $source->id)
        ->where('type', ActivityType::TicketSplit)
        ->first();

    $newActivity = TicketActivity::where('ticket_id', $newTicket->id)
        ->where('type', ActivityType::TicketSplit)
        ->first();

    expect($sourceActivity)->not->toBeNull();
    expect($sourceActivity->properties['split_to'])->toBe($newTicket->reference);

    expect($newActivity)->not->toBeNull();
    expect($newActivity->properties['split_from'])->toBe($source->reference);
});

it('fires TicketCreated event when splitting', function () {
    Event::fake([TicketCreated::class]);

    $source = createTestTicket();
    $reply = createReplyForTicket($source);

    $service = app(TicketService::class);
    $service->splitTicket($source, $reply);

    Event::assertDispatched(TicketCreated::class);
});

it('split controller returns redirect to new ticket', function () {
    $admin = $this->createAdmin();
    $source = createTestTicket();
    $reply = createReplyForTicket($source);

    $response = $this->actingAs($admin)
        ->post(route('escalated.admin.tickets.split', $source), [
            'reply_id' => $reply->id,
        ]);

    $response->assertRedirect();

    $newTicket = Ticket::where('id', '!=', $source->id)->latest('id')->first();
    expect($newTicket)->not->toBeNull();
});

it('split controller validates reply_id is required', function () {
    $admin = $this->createAdmin();
    $source = createTestTicket();

    $response = $this->actingAs($admin)
        ->post(route('escalated.admin.tickets.split', $source), []);

    $response->assertSessionHasErrors('reply_id');
});

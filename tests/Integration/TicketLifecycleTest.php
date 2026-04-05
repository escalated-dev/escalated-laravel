<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Support\Facades\Event;

it('completes full ticket lifecycle: create → assign → reply → resolve → close', function () {
    Event::fake([
        Events\TicketCreated::class,
        Events\TicketAssigned::class,
        Events\TicketEscalated::class,
        Events\TicketStatusChanged::class,
        Events\ReplyCreated::class,
        Events\TicketResolved::class,
        Events\TicketClosed::class,
    ]);

    config(['escalated.transitions' => [
        'open' => ['in_progress', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
        'in_progress' => ['open', 'waiting_on_customer', 'waiting_on_agent', 'escalated', 'resolved', 'closed'],
        'resolved' => ['closed', 'reopened'],
    ]]);

    $customer = $this->createTestUser();
    $agent = $this->createAgent();

    $ticketService = app(TicketService::class);
    $assignmentService = app(AssignmentService::class);

    // 1. Customer creates ticket
    $ticket = $ticketService->create($customer, [
        'subject' => 'Cannot login to my account',
        'description' => 'I keep getting an error when trying to login.',
        'priority' => 'high',
    ]);

    expect($ticket->status)->toBe(TicketStatus::Open);
    expect($ticket->priority)->toBe(TicketPriority::High);
    expect($ticket->reference)->toStartWith('ESC-');
    Event::assertDispatched(Events\TicketCreated::class);

    // 2. Agent gets assigned
    $ticket = $assignmentService->assign($ticket, $agent->id, $agent);
    expect($ticket->assigned_to)->toBe($agent->id);
    Event::assertDispatched(Events\TicketAssigned::class);

    // 3. Agent changes status to in_progress
    $ticket = $ticketService->changeStatus($ticket, TicketStatus::InProgress, $agent);
    expect($ticket->status)->toBe(TicketStatus::InProgress);
    Event::assertDispatched(Events\TicketStatusChanged::class);

    // 4. Agent replies
    $reply = $ticketService->reply($ticket, $agent, 'Please try clearing your browser cache and cookies.');
    expect($reply->body)->toBe('Please try clearing your browser cache and cookies.');
    expect($reply->is_internal_note)->toBeFalse();
    Event::assertDispatched(Events\ReplyCreated::class);

    // 5. Customer replies
    $reply2 = $ticketService->reply($ticket, $customer, 'That worked! Thank you!');
    expect($reply2->author_id)->toBe($customer->id);

    // 6. Agent resolves
    $ticket = $ticketService->resolve($ticket, $agent);
    expect($ticket->status)->toBe(TicketStatus::Resolved);
    expect($ticket->resolved_at)->not->toBeNull();
    Event::assertDispatched(Events\TicketResolved::class);

    // 7. Auto-close (simulate)
    $ticket = $ticketService->close($ticket, $agent);
    expect($ticket->status)->toBe(TicketStatus::Closed);
    expect($ticket->closed_at)->not->toBeNull();
    Event::assertDispatched(Events\TicketClosed::class);

    // Verify all replies are stored
    expect($ticket->replies()->count())->toBe(2);

    // Verify activities logged
    expect($ticket->activities()->count())->toBeGreaterThanOrEqual(4);
});

it('supports tag management on tickets', function () {
    Event::fake([
        Events\TicketCreated::class,
        Events\TicketAssigned::class,
        Events\TicketEscalated::class,
        Events\TicketStatusChanged::class,
        Events\ReplyCreated::class,
        Events\TicketResolved::class,
        Events\TicketClosed::class,
    ]);

    $customer = $this->createTestUser();
    $agent = $this->createAgent();

    $ticketService = app(TicketService::class);

    $ticket = $ticketService->create($customer, [
        'subject' => 'Billing issue',
        'description' => 'Need help with billing.',
    ]);

    $tag1 = Tag::factory()->create(['name' => 'billing']);
    $tag2 = Tag::factory()->create(['name' => 'urgent']);

    $ticket = $ticketService->addTags($ticket, [$tag1->id, $tag2->id], $agent);
    expect($ticket->tags)->toHaveCount(2);

    $ticket = $ticketService->removeTags($ticket, [$tag1->id], $agent);
    expect($ticket->tags)->toHaveCount(1);
    expect($ticket->tags->first()->name)->toBe('urgent');
});

it('supports internal notes visible only to agents', function () {
    Event::fake([
        Events\TicketCreated::class,
        Events\TicketAssigned::class,
        Events\TicketEscalated::class,
        Events\TicketStatusChanged::class,
        Events\ReplyCreated::class,
        Events\TicketResolved::class,
        Events\TicketClosed::class,
    ]);

    $customer = $this->createTestUser();
    $agent = $this->createAgent();

    $ticketService = app(TicketService::class);

    $ticket = $ticketService->create($customer, [
        'subject' => 'Test internal notes',
        'description' => 'Testing.',
    ]);

    $note = $ticketService->addNote($ticket, $agent, 'Customer seems confused, follow up tomorrow.');
    expect($note->is_internal_note)->toBeTrue();
    expect($note->type)->toBe('note');

    $reply = $ticketService->reply($ticket, $agent, 'We are looking into it.');
    expect($reply->is_internal_note)->toBeFalse();

    expect($ticket->publicReplies()->count())->toBe(1);
    expect($ticket->internalNotes()->count())->toBe(1);
});

<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;

it('generates a reference from the ticket id', function () {
    $ticket = Ticket::factory()->create();
    $ref = $ticket->generateReference();
    expect($ref)->toStartWith('ESC-');
    expect($ref)->toBe(sprintf('ESC-%05d', $ticket->id));
});

it('uses dynamic table name from config', function () {
    $ticket = new Ticket;
    expect($ticket->getTable())->toBe('escalated_tickets');
});

it('casts status to enum', function () {
    $ticket = Ticket::factory()->create();
    expect($ticket->status)->toBeInstanceOf(TicketStatus::class);
});

it('casts priority to enum', function () {
    $ticket = Ticket::factory()->create();
    expect($ticket->priority)->toBeInstanceOf(TicketPriority::class);
});

it('scopes open tickets correctly', function () {
    Ticket::factory()->create(['status' => TicketStatus::Open]);
    Ticket::factory()->create(['status' => TicketStatus::InProgress]);
    Ticket::factory()->create(['status' => TicketStatus::Resolved]);
    Ticket::factory()->create(['status' => TicketStatus::Closed]);

    expect(Ticket::open()->count())->toBe(2);
});

it('scopes unassigned tickets', function () {
    Ticket::factory()->create(['assigned_to' => null]);
    Ticket::factory()->create(['assigned_to' => 1]);

    expect(Ticket::unassigned()->count())->toBe(1);
});

it('scopes tickets by assignee', function () {
    Ticket::factory()->create(['assigned_to' => 1]);
    Ticket::factory()->create(['assigned_to' => 2]);
    Ticket::factory()->create(['assigned_to' => 1]);

    expect(Ticket::assignedTo(1)->count())->toBe(2);
});

it('scopes breached SLA tickets', function () {
    Ticket::factory()->create(['sla_first_response_breached' => true]);
    Ticket::factory()->create(['sla_resolution_breached' => true]);
    Ticket::factory()->create(['sla_first_response_breached' => false, 'sla_resolution_breached' => false]);

    expect(Ticket::breachedSla()->count())->toBe(2);
});

it('scopes search by subject, reference, and description', function () {
    Ticket::factory()->create(['subject' => 'Login issue', 'reference' => 'ESC-00001']);
    Ticket::factory()->create(['subject' => 'Payment bug', 'reference' => 'ESC-00002']);

    expect(Ticket::search('Login')->count())->toBe(1);
    expect(Ticket::search('ESC-00002')->count())->toBe(1);
});

it('determines if ticket is open', function () {
    $open = Ticket::factory()->create(['status' => TicketStatus::Open]);
    $resolved = Ticket::factory()->create(['status' => TicketStatus::Resolved]);

    expect($open->isOpen())->toBeTrue();
    expect($resolved->isOpen())->toBeFalse();
});

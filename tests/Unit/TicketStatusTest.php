<?php

use Escalated\Laravel\Enums\TicketStatus;

it('has all expected statuses', function () {
    expect(TicketStatus::cases())->toHaveCount(9);
});

it('returns label for each status', function () {
    expect(TicketStatus::Open->label())->toBe('Open');
    expect(TicketStatus::InProgress->label())->toBe('In Progress');
    expect(TicketStatus::WaitingOnCustomer->label())->toBe('Waiting on Customer');
    expect(TicketStatus::WaitingOnAgent->label())->toBe('Waiting on Agent');
    expect(TicketStatus::Escalated->label())->toBe('Escalated');
    expect(TicketStatus::Resolved->label())->toBe('Resolved');
    expect(TicketStatus::Closed->label())->toBe('Closed');
    expect(TicketStatus::Reopened->label())->toBe('Reopened');
});

it('returns color for each status', function () {
    expect(TicketStatus::Open->color())->toStartWith('#');
    expect(TicketStatus::Resolved->color())->toStartWith('#');
});

it('identifies open statuses correctly', function () {
    expect(TicketStatus::Open->isOpen())->toBeTrue();
    expect(TicketStatus::InProgress->isOpen())->toBeTrue();
    expect(TicketStatus::WaitingOnCustomer->isOpen())->toBeTrue();
    expect(TicketStatus::Escalated->isOpen())->toBeTrue();
    expect(TicketStatus::Resolved->isOpen())->toBeFalse();
    expect(TicketStatus::Closed->isOpen())->toBeFalse();
});

it('can be created from string value', function () {
    expect(TicketStatus::from('open'))->toBe(TicketStatus::Open);
    expect(TicketStatus::from('in_progress'))->toBe(TicketStatus::InProgress);
    expect(TicketStatus::from('resolved'))->toBe(TicketStatus::Resolved);
});

it('validates transitions with config', function () {
    config(['escalated.transitions' => [
        'open' => ['in_progress', 'closed'],
        'in_progress' => ['resolved', 'waiting_on_customer'],
    ]]);

    expect(TicketStatus::Open->canTransitionTo(TicketStatus::InProgress))->toBeTrue();
    expect(TicketStatus::Open->canTransitionTo(TicketStatus::Closed))->toBeTrue();
    expect(TicketStatus::Open->canTransitionTo(TicketStatus::Resolved))->toBeFalse();
    expect(TicketStatus::InProgress->canTransitionTo(TicketStatus::Resolved))->toBeTrue();
});

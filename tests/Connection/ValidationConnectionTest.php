<?php

use Escalated\Laravel\Http\Requests\BulkActionRequest;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Validator;

it('validates existing and missing bulk ticket IDs on the package database', function () {
    $ticket = Ticket::factory()->create();
    $rules = (new BulkActionRequest)->rules();

    expect(Validator::make([
        'ticket_ids' => [$ticket->id], 'action' => 'delete',
    ], $rules)->passes())->toBeTrue()
        ->and(Validator::make([
            'ticket_ids' => [$ticket->id + 1], 'action' => 'delete',
        ], $rules)->errors()->has('ticket_ids.0'))->toBeTrue();
});

it('validates a widget department on the package database and rejects missing IDs', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');
    $department = Department::factory()->create();
    $payload = [
        'name' => 'Recipient', 'email' => 'recipient@example.com',
        'subject' => 'Parcel question', 'description' => 'Please help with delivery.',
        'department_id' => $department->id,
    ];

    $this->postJson(route('escalated.widget.tickets.store'), $payload)->assertCreated();
    expect(Ticket::first()->department_id)->toBe($department->id);

    $payload['department_id'] = $department->id + 1;
    $this->postJson(route('escalated.widget.tickets.store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('department_id');
    expect(Ticket::count())->toBe(1);
});

<?php

/**
 * escalated.tickets.require_department / default_department_id: a new ticket
 * with no department gets the default, or is rejected when there is none.
 */

use Escalated\Laravel\Mail\InboundMessage;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ChatSessionService;
use Escalated\Laravel\Services\InboundEmailService;
use Escalated\Laravel\Services\TicketService;
use Escalated\Laravel\Support\ImportContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    Route::get('/login', fn () => 'login')->name('login');
    Notification::fake();
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('widget_enabled', '1');
});

function requireDepartment(?int $defaultId = null): void
{
    config([
        'escalated.tickets.require_department' => true,
        'escalated.tickets.default_department_id' => $defaultId,
    ]);
}

function deptApiHeaders($agent): array
{
    return ['Authorization' => 'Bearer '.ApiToken::createToken($agent, 'Dept', ['agent'])['plainTextToken']];
}

it('leaves tickets without a department by default', function () {
    $user = $this->createTestUser();

    $ticket = app(TicketService::class)->create($user, ['subject' => 'Hi', 'description' => 'There']);

    expect($ticket->department_id)->toBeNull();
});

it('gives a ticket with no department the default department when one is required', function () {
    $default = Department::factory()->create();
    requireDepartment($default->id);

    $ticket = app(TicketService::class)->create($this->createTestUser(), ['subject' => 'Hi', 'description' => 'There']);

    expect($ticket->department_id)->toBe($default->id);
});

it('keeps the department the ticket was created with', function () {
    $default = Department::factory()->create();
    $chosen = Department::factory()->create();
    requireDepartment($default->id);

    $ticket = app(TicketService::class)->create($this->createTestUser(), [
        'subject' => 'Hi', 'description' => 'There', 'department_id' => $chosen->id,
    ]);

    expect($ticket->department_id)->toBe($chosen->id);
});

it('rejects a ticket with no department when one is required and there is no default', function () {
    requireDepartment();

    expect(fn () => app(TicketService::class)->create($this->createTestUser(), ['subject' => 'Hi', 'description' => 'There']))
        ->toThrow(ValidationException::class);
    expect(Ticket::count())->toBe(0);
});

it('rejects when the default department no longer exists', function () {
    requireDepartment(999999);

    expect(fn () => app(TicketService::class)->create($this->createTestUser(), ['subject' => 'Hi', 'description' => 'There']))
        ->toThrow(ValidationException::class);
});

it('returns a department_id validation error from the API', function () {
    requireDepartment();

    $this->postJson('/support/api/v1/tickets', ['subject' => 'Hi', 'description' => 'There'], deptApiHeaders($this->createAgent()))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('department_id');
    expect(Ticket::count())->toBe(0);
});

it('applies the default department to an API ticket', function () {
    $default = Department::factory()->create();
    requireDepartment($default->id);

    $this->postJson('/support/api/v1/tickets', ['subject' => 'Hi', 'description' => 'There'], deptApiHeaders($this->createAgent()))
        ->assertCreated();
    expect(Ticket::sole()->department_id)->toBe($default->id);
});

it('returns the customer to the form with a department_id error', function () {
    requireDepartment();
    $user = $this->createTestUser();

    $this->actingAs($user)
        ->from(route('escalated.customer.tickets.create'))
        ->post(route('escalated.customer.tickets.store'), [
            'subject' => 'Hi', 'description' => 'There', 'priority' => 'medium',
        ])
        ->assertRedirect(route('escalated.customer.tickets.create'))
        ->assertSessionHasErrors('department_id');
    expect(Ticket::count())->toBe(0);
});

it('rejects a widget ticket with no department and creates nothing', function () {
    requireDepartment();

    $this->postJson('/support/widget/tickets', [
        ...$this->guestProof('guest@example.com'),
        'name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Help', 'description' => 'Details',
    ])->assertUnprocessable()->assertJsonValidationErrors('department_id');
    expect(Ticket::count())->toBe(0);
});

it('applies the default department to a widget ticket', function () {
    $default = Department::factory()->create();
    requireDepartment($default->id);

    $this->postJson('/support/widget/tickets', [
        ...$this->guestProof('guest@example.com'),
        'name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Help', 'description' => 'Details',
    ])->assertCreated();
    expect(Ticket::sole()->department_id)->toBe($default->id);
});

it('applies the default department to an inbound email ticket', function () {
    config(['escalated.inbound_email.enabled' => true]);
    $default = Department::factory()->create();
    requireDepartment($default->id);

    $inbound = app(InboundEmailService::class)->process(new InboundMessage(
        fromEmail: 'customer@example.test', fromName: 'Customer', toEmail: 'support@example.test',
        subject: 'Help', bodyText: 'Details', bodyHtml: null,
    ));

    expect($inbound->status)->toBe('processed')
        ->and(Ticket::sole()->department_id)->toBe($default->id);
});

it('records an inbound email as failed when a department is required and there is no default', function () {
    config(['escalated.inbound_email.enabled' => true]);
    requireDepartment();

    $inbound = app(InboundEmailService::class)->process(new InboundMessage(
        fromEmail: 'customer@example.test', fromName: 'Customer', toEmail: 'support@example.test',
        subject: 'Help', bodyText: 'Details', bodyHtml: null,
    ));

    expect($inbound->status)->toBe('failed')
        ->and(Ticket::count())->toBe(0);
});

it('exempts imports', function () {
    requireDepartment();

    $ticket = ImportContext::suppress(fn () => Ticket::factory()->create(['department_id' => null]));

    expect($ticket->department_id)->toBeNull();
});

it('rejects a live chat with no department when one is required and there is no default', function () {
    requireDepartment();

    expect(fn () => app(ChatSessionService::class)->startChat([
        ...$this->guestProof('chatter@example.com', 'chat'),
        'name' => 'Chatter', 'email' => 'chatter@example.com', 'subject' => 'Help', 'message' => 'Hello',
    ]))->toThrow(ValidationException::class);
    expect(Ticket::count())->toBe(0);
});

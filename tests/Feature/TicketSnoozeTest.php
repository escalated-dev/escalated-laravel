<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('snoozeTicket sets snoozed_until, snoozed_by, and saves previous status', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'reference' => 'SNZ-00001',
    ]);

    $until = now()->addDays(3);

    $service = app(TicketService::class);
    $result = $service->snoozeTicket($ticket, $until, $agent);

    expect($result->snoozed_until->toDateString())->toBe($until->toDateString());
    expect($result->snoozed_by)->toBe($agent->id);
    expect($result->status_before_snooze)->toBe('open');
});

it('unsnoozeTicket clears snooze fields and restores previous status', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::InProgress,
        'reference' => 'SNZ-00002',
    ]);

    $service = app(TicketService::class);
    $snoozed = $service->snoozeTicket($ticket, now()->addDays(1), $agent);
    $unsnoozed = $service->unsnoozeTicket($snoozed, $agent);

    expect($unsnoozed->snoozed_until)->toBeNull();
    expect($unsnoozed->snoozed_by)->toBeNull();
    expect($unsnoozed->status_before_snooze)->toBeNull();
    expect($unsnoozed->status)->toBe(TicketStatus::InProgress);
});

it('isSnoozed accessor returns correct value', function () {
    $snoozedTicket = Ticket::factory()->create([
        'snoozed_until' => now()->addHour(),
        'reference' => 'SNZ-00003',
    ]);

    $notSnoozedTicket = Ticket::factory()->create([
        'snoozed_until' => null,
        'reference' => 'SNZ-00004',
    ]);

    $expiredSnooze = Ticket::factory()->create([
        'snoozed_until' => now()->subHour(),
        'reference' => 'SNZ-00005',
    ]);

    expect($snoozedTicket->is_snoozed)->toBeTrue();
    expect($notSnoozedTicket->is_snoozed)->toBeFalse();
    expect($expiredSnooze->is_snoozed)->toBeFalse();
});

it('scopeSnoozed returns only actively snoozed tickets', function () {
    Ticket::factory()->create([
        'snoozed_until' => now()->addHour(),
        'reference' => 'SNZ-00010',
    ]);
    Ticket::factory()->create([
        'snoozed_until' => null,
        'reference' => 'SNZ-00011',
    ]);
    Ticket::factory()->create([
        'snoozed_until' => now()->subHour(),
        'reference' => 'SNZ-00012',
    ]);

    expect(Ticket::snoozed()->count())->toBe(1);
});

it('scopeNotSnoozed excludes actively snoozed tickets', function () {
    Ticket::factory()->create([
        'snoozed_until' => now()->addHour(),
        'reference' => 'SNZ-00020',
    ]);
    Ticket::factory()->create([
        'snoozed_until' => null,
        'reference' => 'SNZ-00021',
    ]);
    Ticket::factory()->create([
        'snoozed_until' => now()->subHour(),
        'reference' => 'SNZ-00022',
    ]);

    expect(Ticket::notSnoozed()->count())->toBe(2);
});

it('WakeSnoozedTicketsCommand unsnoozes expired tickets', function () {
    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'snoozed_until' => now()->subMinutes(5),
        'snoozed_by' => null,
        'status_before_snooze' => 'open',
        'reference' => 'SNZ-00030',
    ]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'snoozed_until' => now()->addDay(),
        'snoozed_by' => null,
        'status_before_snooze' => 'open',
        'reference' => 'SNZ-00031',
    ]);

    $this->artisan('escalated:wake-snoozed-tickets')
        ->assertSuccessful();

    $expired = Ticket::where('reference', 'SNZ-00030')->first();
    $active = Ticket::where('reference', 'SNZ-00031')->first();

    expect($expired->snoozed_until)->toBeNull();
    expect($active->snoozed_until)->not->toBeNull();
});

it('controller snooze action validates future date', function () {
    $admin = $this->createAdmin();
    $ticket = Ticket::factory()->create(['reference' => 'SNZ-00040']);

    $response = $this->actingAs($admin)
        ->post(route('escalated.admin.tickets.snooze', $ticket), [
            'snoozed_until' => now()->subDay()->toDateTimeString(),
        ]);

    $response->assertSessionHasErrors('snoozed_until');
});

it('default ticket list excludes snoozed tickets', function () {
    $agent = $this->createAgent();

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'snoozed_until' => now()->addDay(),
        'reference' => 'SNZ-00050',
    ]);

    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'snoozed_until' => null,
        'reference' => 'SNZ-00051',
    ]);

    $service = app(TicketService::class);
    $results = $service->list([]);

    // Only the non-snoozed ticket should be in default results
    $references = collect($results->items())->pluck('reference')->all();
    expect($references)->toContain('SNZ-00051');
    expect($references)->not->toContain('SNZ-00050');
});

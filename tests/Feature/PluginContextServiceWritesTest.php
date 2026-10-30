<?php

use Escalated\Laravel\Bridge\ContextHandler;
use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\DepartmentChanged;
use Escalated\Laravel\Events\InternalNoteAdded;
use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Events\TicketPriorityChanged;
use Escalated\Laravel\Events\TicketStatusChanged;
use Escalated\Laravel\Facades\Hook;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketActivity;
use Illuminate\Support\Facades\Event;

/*
 * domain-model/plugin-contract.md: ctx.tickets.create/update and
 * ctx.replies.create go through the ticket service, so a plugin's ticket or
 * reply gets the same activity log, SLA, canonical events, workflows and
 * webhooks as one written over the HTTP API. A plugin's own write does not
 * re-deliver the event it is handling back to plugins.
 */

function serviceHandler(): ContextHandler
{
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme');

    return $handler;
}

function activityTypes(Ticket $ticket): array
{
    return TicketActivity::where('ticket_id', $ticket->getKey())->orderBy('id')->get()
        ->map(fn ($activity) => $activity->type instanceof BackedEnum ? $activity->type->value : $activity->type)
        ->all();
}

it('logs the opening activity and attaches the default SLA to a plugin ticket', function () {
    SlaPolicy::factory()->create(['is_default' => true, 'is_active' => true]);
    $agent = $this->createAgent();
    $this->actingAs($agent);

    $created = serviceHandler()->handle('ctx.tickets.create', ['data' => ['subject' => 'From a plugin']]);

    $ticket = Ticket::findOrFail($created['id']);
    $opening = TicketActivity::where('ticket_id', $ticket->id)->firstOrFail();
    expect($ticket->sla_policy_id)->not->toBeNull()
        ->and(activityTypes($ticket))->toBe([ActivityType::StatusChanged->value])
        ->and($opening->properties)->toBe(['new_status' => 'open'])
        ->and((int) $opening->causer_id)->toBe($agent->id);
});

it('announces a plugin ticket as created, and a requested status as a status change', function () {
    Event::fake([TicketCreated::class, TicketStatusChanged::class]);

    $created = serviceHandler()->handle('ctx.tickets.create', ['data' => ['subject' => 'Waiting', 'status' => 'waiting_on_customer']]);

    $ticket = Ticket::findOrFail($created['id']);
    expect($ticket->status)->toBe(TicketStatus::WaitingOnCustomer);
    Event::assertDispatched(TicketCreated::class, fn ($event) => $event->ticket->is($ticket));
    Event::assertDispatched(TicketStatusChanged::class, fn ($event) => $event->ticket->is($ticket)
        && $event->oldStatus === TicketStatus::Open && $event->newStatus === TicketStatus::WaitingOnCustomer);
});

it('refuses a plugin ticket status the ticket cannot open into, writing nothing', function () {
    expect(fn () => serviceHandler()->handle('ctx.tickets.create', ['data' => ['subject' => 'Bad', 'status' => 'reopened']]))
        ->toThrow(InvalidArgumentException::class)
        ->and(Ticket::count())->toBe(0);
});

it('changes a plugin ticket status, priority and department through the service', function () {
    Event::fake([TicketStatusChanged::class, TicketPriorityChanged::class, DepartmentChanged::class]);
    $agent = $this->createAgent();
    $this->actingAs($agent);
    $department = Department::factory()->create();
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open, 'priority' => 'low']);

    serviceHandler()->handle('ctx.tickets.update', ['id' => $ticket->id, 'data' => [
        'status' => 'in_progress',
        'priority' => 'urgent',
        'department_id' => $department->id,
    ]]);

    $fresh = $ticket->fresh();
    expect($fresh->status)->toBe(TicketStatus::InProgress)
        ->and($fresh->priority->value)->toBe('urgent')
        ->and((int) $fresh->department_id)->toBe($department->id)
        ->and(activityTypes($ticket))->toContain(ActivityType::PriorityChanged->value, ActivityType::DepartmentChanged->value)
        ->and((int) TicketActivity::where('ticket_id', $ticket->id)->where('type', ActivityType::PriorityChanged->value)->value('causer_id'))->toBe($agent->id);
    Event::assertDispatched(TicketStatusChanged::class, fn ($event) => $event->newStatus === TicketStatus::InProgress);
    Event::assertDispatched(TicketPriorityChanged::class);
    Event::assertDispatched(DepartmentChanged::class);
});

it('logs a plugin status change in the activity log', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    serviceHandler()->handle('ctx.tickets.update', ['id' => $ticket->id, 'data' => ['status' => 'resolved']]);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and(TicketActivity::where('ticket_id', $ticket->id)->where('type', ActivityType::StatusChanged->value)->value('properties'))
        ->toEqual(['old_status' => 'open', 'new_status' => 'resolved']);
});

it('refuses a plugin status change the workflow does not allow, writing nothing', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed, 'priority' => 'low']);

    expect(fn () => serviceHandler()->handle('ctx.tickets.update', ['id' => $ticket->id, 'data' => ['priority' => 'high', 'status' => 'in_progress']]))
        ->toThrow(InvalidArgumentException::class)
        ->and($ticket->fresh()->priority->value)->toBe('low');
});

it('logs plugin replies and notes, by the acting user, and announces them', function () {
    Event::fake([ReplyCreated::class, InternalNoteAdded::class]);
    $agent = $this->createAgent();
    $this->actingAs($agent);
    $ticket = Ticket::factory()->create();

    serviceHandler()->handle('ctx.replies.create', ['data' => ['ticket_id' => $ticket->id, 'body' => 'Synced']]);
    serviceHandler()->handle('ctx.replies.create', ['data' => ['ticket_id' => $ticket->id, 'body' => 'Note', 'is_internal' => true]]);

    expect(activityTypes($ticket))->toBe([ActivityType::Replied->value, ActivityType::NoteAdded->value])
        ->and(TicketActivity::where('ticket_id', $ticket->id)->pluck('causer_id')->map(fn ($id) => (int) $id)->all())->toBe([$agent->id, $agent->id]);
    Event::assertDispatched(ReplyCreated::class);
    Event::assertDispatched(InternalNoteAdded::class);
});

it('logs a plugin reply with no acting user as a reply with no causer', function () {
    $ticket = Ticket::factory()->create();

    serviceHandler()->handle('ctx.replies.create', ['data' => ['ticket_id' => $ticket->id, 'body' => 'From cron']]);

    $activity = TicketActivity::where('ticket_id', $ticket->id)->firstOrFail();
    expect(activityTypes($ticket))->toBe([ActivityType::Replied->value])
        ->and($activity->causer_id)->toBeNull();
});

it('does not hand a plugin the event its own write raised while it handles that event', function () {
    $calls = 0;
    Hook::addAction('ticket.created', function () use (&$calls) {
        // A plugin that opens a follow-up ticket for every new ticket.
        if (++$calls > 5) {
            return;
        }
        serviceHandler()->handle('ctx.tickets.create', ['data' => ['subject' => 'Follow-up']]);
    });

    serviceHandler()->handle('ctx.tickets.create', ['data' => ['subject' => 'First']]);

    expect($calls)->toBe(1)
        ->and(Ticket::count())->toBe(2);
});

it('still delivers other events a plugin write raises while it handles one', function () {
    $seen = [];
    Hook::addAction('ticket.created', function ($payload) {
        serviceHandler()->handle('ctx.replies.create', ['data' => ['ticket_id' => $payload['ticket']['id'], 'body' => 'Thanks']]);
    });
    Hook::addAction('reply.created', function () use (&$seen) {
        $seen[] = 'reply.created';
    });

    serviceHandler()->handle('ctx.tickets.create', ['data' => ['subject' => 'First']]);

    expect($seen)->toBe(['reply.created']);
});

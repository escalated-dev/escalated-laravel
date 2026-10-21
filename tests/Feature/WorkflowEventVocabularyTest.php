<?php

/**
 * ADR decisions/2026-10-21-event-vocabulary.md: workflows fire under the
 * canonical event names, and workflows saved under a legacy name keep firing.
 */

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Escalated\Laravel\Models\WorkflowLog;
use Escalated\Laravel\Services\ChatSessionService;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Support\Facades\Gate;

function vocabWorkflow(string $trigger): Workflow
{
    return Workflow::create([
        'name' => 'On '.$trigger,
        'trigger_event' => $trigger,
        'conditions' => ['all' => []],
        'actions' => [['type' => 'add_note', 'value' => 'fired']],
        'is_active' => true,
        'position' => 0,
    ]);
}

function vocabFired(Workflow $workflow): bool
{
    return WorkflowLog::where('workflow_id', $workflow->id)->exists();
}

function vocabTag(): Tag
{
    return Tag::create(['name' => 'Vip', 'slug' => 'vip']);
}

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('runs reply.created workflows on a public reply, under the canonical name', function () {
    $ticket = Ticket::factory()->create();
    $workflow = vocabWorkflow('reply.created');

    $ticket->replies()->create(['body' => 'Any update?', 'is_internal_note' => false]);

    expect(vocabFired($workflow))->toBeTrue()
        ->and(WorkflowLog::where('workflow_id', $workflow->id)->value('trigger_event'))->toBe('reply.created');
});

it('keeps running workflows saved under a legacy event name', function (string $legacy, Closure $trigger) {
    $ticket = Ticket::factory()->create();
    $workflow = vocabWorkflow($legacy);

    $trigger($ticket);

    expect(vocabFired($workflow))->toBeTrue();
})->with([
    'ticket.replied' => ['ticket.replied', fn (Ticket $t) => $t->replies()->create(['body' => 'Hi', 'is_internal_note' => false])],
    'internal_note.added' => ['internal_note.added', fn (Ticket $t) => $t->replies()->create(['body' => 'Note', 'is_internal_note' => true])],
    'internal_note.created' => ['internal_note.created', fn (Ticket $t) => $t->replies()->create(['body' => 'Note', 'is_internal_note' => true])],
    'tag.added' => ['tag.added', fn (Ticket $t) => app(TicketService::class)->addTags($t, [vocabTag()->id])],
    'ticket.tagged' => ['ticket.tagged', fn (Ticket $t) => app(TicketService::class)->addTags($t, [vocabTag()->id])],
]);

it('runs workflows on the ticket events the builder now offers', function (string $event, Closure $trigger) {
    $ticket = Ticket::factory()->create();
    $workflow = vocabWorkflow($event);

    $trigger($ticket, $this->createAgent());

    expect(vocabFired($workflow))->toBeTrue();
})->with([
    'ticket.priority_changed' => ['ticket.priority_changed', fn (Ticket $t) => $t->changePriority(TicketPriority::Urgent)],
    'ticket.department_changed' => ['ticket.department_changed', fn (Ticket $t) => $t->changeDepartment(Department::factory()->create(), null)],
    'ticket.unassigned' => ['ticket.unassigned', function (Ticket $t, $agent) {
        $t->assign($agent);
        $t->unassignTicket();
    }],
    'ticket.tag_added' => ['ticket.tag_added', fn (Ticket $t) => app(TicketService::class)->addTags($t, [vocabTag()->id])],
    'ticket.tag_removed' => ['ticket.tag_removed', function (Ticket $t) {
        $tag = vocabTag();
        $t->tags()->attach($tag->id);
        app(TicketService::class)->removeTags($t, [$tag->id]);
    }],
    'note.created' => ['note.created', fn (Ticket $t) => $t->replies()->create(['body' => 'Note', 'is_internal_note' => true])],
]);

it('does not run note.created workflows for a note a workflow wrote', function () {
    $ticket = Ticket::factory()->create();
    $onUpdate = vocabWorkflow('ticket.updated');
    $onNote = vocabWorkflow('note.created');

    $ticket->update(['subject' => 'Changed']);

    expect(vocabFired($onUpdate))->toBeTrue()
        ->and(vocabFired($onNote))->toBeFalse();
});

it('runs workflows on the chat events the engine fires', function (string $event, Closure $trigger) {
    $ticket = Ticket::factory()->create(['status' => 'live', 'channel' => 'chat']);
    $session = ChatSession::factory()->active()->create(['ticket_id' => $ticket->id]);
    $workflow = vocabWorkflow($event);

    $trigger(app(ChatSessionService::class), $session, $this->createAgent());

    expect(vocabFired($workflow))->toBeTrue();
})->with([
    'chat.assigned' => ['chat.assigned', fn ($s, $session, $agent) => $s->assignAgent($session, $agent->id)],
    'chat.transferred' => ['chat.transferred', fn ($s, $session, $agent) => $s->transferChat($session, $agent->id)],
    'chat.message' => ['chat.message', fn ($s, $session) => $s->sendMessage($session, 'Hello', null, false)],
    'chat.ended' => ['chat.ended', fn ($s, $session) => $s->endChat($session, 'agent')],
    'chat.session_ended (legacy)' => ['chat.session_ended', fn ($s, $session) => $s->endChat($session, 'agent')],
]);

it('stores a legacy trigger name posted by an old client under the canonical name', function () {
    $this->actingAs($this->createAdmin());

    $this->post(route('escalated.admin.workflows.store'), [
        'name' => 'Old client',
        'trigger_event' => 'ticket.replied',
        'conditions' => ['all' => []],
        'actions' => [['type' => 'add_tag', 'value' => 'x']],
        'is_active' => true,
    ])->assertRedirect();

    expect(Workflow::sole()->trigger_event)->toBe('reply.created');
});

it('rewrites stored legacy trigger names in the migration', function () {
    $legacy = vocabWorkflow('ticket.replied');
    $tagged = vocabWorkflow('ticket.tagged');
    $current = vocabWorkflow('ticket.created');

    $migration = require __DIR__.'/../../database/migrations/2026_10_21_000001_canonicalize_workflow_trigger_events.php';
    $migration->up();

    expect($legacy->fresh()->trigger_event)->toBe('reply.created')
        ->and($tagged->fresh()->trigger_event)->toBe('ticket.tag_added')
        ->and($current->fresh()->trigger_event)->toBe('ticket.created');
});

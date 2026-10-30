<?php

use Escalated\Laravel\Bridge\ContextHandler;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;

/*
 * domain-model/plugin-contract.md: ctx.replies.create, ctx.tickets.create,
 * ctx.tickets.update and ctx.tags.create take only the contract's fields. Any
 * other key is an error, never an attribute, so a plugin cannot name the
 * author, the requester, the assignee or a reply's flags.
 */

function writesHandler(): ContextHandler
{
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('acme');

    return $handler;
}

it('creates a plugin reply authored by the acting user, or a note with is_internal', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();
    $this->actingAs($agent);
    $handler = writesHandler();

    $reply = $handler->handle('ctx.replies.create', ['data' => ['ticket_id' => $ticket->id, 'body' => 'Synced']]);
    $note = $handler->handle('ctx.replies.create', ['data' => ['ticket_id' => $ticket->id, 'body' => 'Internal', 'is_internal' => true]]);

    $replyRow = Reply::findOrFail($reply['id']);
    $noteRow = Reply::findOrFail($note['id']);
    expect($replyRow->is_internal_note)->toBeFalse()
        ->and($replyRow->type)->toBe('reply')
        ->and((int) $replyRow->author_id)->toBe($agent->id)
        ->and($replyRow->author_type)->toBe($agent->getMorphClass())
        ->and($noteRow->is_internal_note)->toBeTrue()
        ->and($noteRow->type)->toBe('note');
});

it('creates a plugin reply with no author outside a request', function () {
    $ticket = Ticket::factory()->create();

    $reply = writesHandler()->handle('ctx.replies.create', ['data' => ['ticket_id' => $ticket->id, 'body' => 'From cron']]);

    expect(Reply::findOrFail($reply['id'])->author_id)->toBeNull();
});

it('rejects plugin reply fields outside the contract', function (string $key, mixed $value) {
    $ticket = Ticket::factory()->create();
    $handler = writesHandler();

    expect(fn () => $handler->handle('ctx.replies.create', ['data' => ['ticket_id' => $ticket->id, 'body' => 'x', $key => $value]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(Reply::count())->toBe(0);
})->with([
    ['author_id', 1],
    ['author_type', 'App\Models\User'],
    ['is_internal_note', true],
    ['type', 'system'],
    ['is_pinned', true],
    ['metadata', ['x' => 1]],
    ['id', 999],
]);

it('requires an existing ticket and a body for a plugin reply', function (array $data) {
    $handler = writesHandler();

    expect(fn () => $handler->handle('ctx.replies.create', ['data' => $data]))->toThrow(InvalidArgumentException::class);
})->with([
    'no ticket' => [['body' => 'x']],
    'unknown ticket' => [['ticket_id' => 999999, 'body' => 'x']],
    'no body' => [['ticket_id' => 1]],
    'blank body' => [['ticket_id' => 1, 'body' => '  ']],
]);

it('creates a plugin ticket from the contract fields and aliases', function () {
    $created = writesHandler()->handle('ctx.tickets.create', ['data' => [
        'title' => 'From Slack',
        'description' => 'Help',
        'priority' => 'high',
        'metadata' => ['slack_ts' => '1.2'],
        'requester_email' => 'Pat@Example.com',
        'requester_name' => 'Pat',
    ]]);

    $ticket = Ticket::findOrFail($created['id']);
    expect($ticket->subject)->toBe('From Slack')
        ->and($ticket->description)->toBe('Help')
        ->and($ticket->priority->value)->toBe('high')
        ->and($ticket->channel->value)->toBe('api')
        ->and($ticket->metadata)->toBe(['slack_ts' => '1.2'])
        ->and(Contact::findOrFail($ticket->contact_id)->email)->toBe('pat@example.com')
        ->and($ticket->requester_id)->toBeNull()
        ->and($ticket->assigned_to)->toBeNull();
});

it('rejects plugin ticket fields outside the contract', function (string $method, string $key, mixed $value) {
    $ticket = Ticket::factory()->create(['subject' => 'Original']);
    $params = $method === 'ctx.tickets.create'
        ? ['data' => ['subject' => 'S', $key => $value]]
        : ['id' => $ticket->id, 'data' => ['subject' => 'Changed', $key => $value]];

    expect(fn () => writesHandler()->handle($method, $params))->toThrow(InvalidArgumentException::class)
        ->and(Ticket::count())->toBe(1)
        ->and($ticket->fresh()->subject)->toBe('Original');
})->with([
    ['ctx.tickets.create', 'requester_id', 1],
    ['ctx.tickets.create', 'requester_type', 'App\Models\User'],
    ['ctx.tickets.create', 'assigned_to', 1],
    ['ctx.tickets.create', 'reference', 'ESC-1'],
    ['ctx.tickets.create', 'guest_token', 'x'],
    ['ctx.tickets.update', 'assigned_to', 1],
    ['ctx.tickets.update', 'requester_id', 1],
    ['ctx.tickets.update', 'channel', 'email'],
    ['ctx.tickets.update', 'requester_email', 'a@b.c'],
    ['ctx.tickets.update', 'resolved_at', '2026-01-01'],
]);

it('updates a plugin ticket from the contract fields', function () {
    $ticket = Ticket::factory()->create(['priority' => 'low']);

    writesHandler()->handle('ctx.tickets.update', ['id' => $ticket->id, 'data' => ['title' => 'Renamed', 'body' => 'New body', 'priority' => 'urgent']]);

    $fresh = $ticket->fresh();
    expect($fresh->subject)->toBe('Renamed')
        ->and($fresh->description)->toBe('New body')
        ->and($fresh->priority->value)->toBe('urgent');
});

it('creates a plugin tag from name and color only', function () {
    $handler = writesHandler();

    $tag = $handler->handle('ctx.tags.create', ['data' => ['name' => 'VIP', 'color' => '#ff0000']]);

    expect($tag['name'])->toBe('VIP')
        ->and(Tag::findOrFail($tag['id'])->color)->toBe('#ff0000')
        ->and(fn () => $handler->handle('ctx.tags.create', ['data' => ['name' => 'Other', 'id' => 5]]))
        ->toThrow(InvalidArgumentException::class);
});

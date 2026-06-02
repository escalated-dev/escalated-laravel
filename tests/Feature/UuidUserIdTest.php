<?php

use Escalated\Laravel\Models\Mention;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SavedView;
use Escalated\Laravel\Models\Ticket;

// Regression coverage for host apps whose User model uses a UUID/string primary
// key. The package must accept string user ids throughout without a TypeError.

it('assigns a ticket via a string user id without a type error', function () {
    $agent = $this->createAgent();
    $ticket = Ticket::factory()->create();

    // Passing the id as a string (as a UUID host would) must route through the
    // find path and assign, not throw a TypeError on Ticket::assign().
    $ticket->assign((string) $agent->getKey());

    expect($ticket->fresh()->assigned_to)->toEqual($agent->getKey());
});

it('rejects an unknown string user id with a clean exception, not a TypeError', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => $ticket->assign('9f1c2d3e-0000-0000-0000-000000000000'))
        ->toThrow(InvalidArgumentException::class);
});

it('scopes saved views for a string/uuid user id without a type error', function () {
    $uuid = '9f1c2d3e-4a5b-6c7d-8e9f-0a1b2c3d4e5f';
    $otherUuid = '00000000-0000-0000-0000-000000000000';

    $mine = SavedView::create([
        'name' => 'Mine',
        'user_id' => $uuid,
        'filters' => ['status' => 'open'],
        'position' => 1,
    ]);

    $shared = SavedView::create([
        'name' => 'Shared',
        'user_id' => $otherUuid,
        'filters' => [],
        'is_shared' => true,
        'position' => 2,
    ]);

    SavedView::create([
        'name' => 'Someone else private',
        'user_id' => $otherUuid,
        'filters' => [],
        'position' => 3,
    ]);

    $ids = SavedView::forUser($uuid)->pluck('id')->all();

    expect($ids)->toContain($mine->id)
        ->and($ids)->toContain($shared->id)
        ->and($ids)->toHaveCount(2);
});

it('scopes mentions for a string/uuid user id without a type error', function () {
    $uuid = 'abcdef01-2345-6789-abcd-ef0123456789';

    $ticket = Ticket::factory()->create();
    $reply = Reply::create([
        'ticket_id' => $ticket->id,
        'body' => 'Hey @agent',
        'is_internal_note' => true,
        'type' => 'note',
    ]);

    $mention = Mention::create([
        'reply_id' => $reply->id,
        'user_id' => $uuid,
    ]);

    $found = Mention::forUser($uuid)->pluck('id')->all();

    expect($found)->toContain($mention->id)
        ->and(Mention::forUser('11111111-1111-1111-1111-111111111111')->count())->toBe(0);
});

<?php

use Escalated\Laravel\Models\Mention;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SavedView;
use Escalated\Laravel\Models\Ticket;

// Regression coverage for host apps whose User model uses a UUID/string primary
// key. The package must accept string user ids throughout without a TypeError.
//
// The ids below are strings, not UUID literals. This suite's host user model is
// integer-keyed, so `Escalated::userKeyType()` resolves to bigint and the
// package's user columns are bigints -- writing a UUID into one is invalid.
// PostgreSQL says so; SQLite stores it anyway and MySQL silently truncates it
// to 9, which is how these cases passed while asserting something that cannot
// happen. What is actually under test is the PHP type: a string reaching a
// scope or a fill must not raise a TypeError. UUID-typed columns are covered by
// UserKeyTypeTest.
function stringUserId(int $id): string
{
    return (string) $id;
}

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
    $uuid = stringUserId(987654321);
    $otherUuid = stringUserId(123456789);

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
    $uuid = stringUserId(555000111);

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
        ->and(Mention::forUser(stringUserId(222000333))->count())->toBe(0);
});

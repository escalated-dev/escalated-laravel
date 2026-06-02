<?php

use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Models\Ticket;

// Regression: the real-time `.reply.created` payload must carry the reply's
// author. It previously read $reply->user_id / $reply->user (which don't exist
// on Reply — it uses the polymorphic author_type/author_id), so every payload
// shipped author_id/author_name = null → "Unknown" in the UI.

it('includes the real author in the ReplyCreated broadcast payload', function () {
    $agent = $this->createAgent(['name' => 'Dana Agent']);
    $ticket = Ticket::factory()->create();

    $reply = $ticket->addReply($agent, 'On it!');

    $payload = (new ReplyCreated($reply))->broadcastWith();

    expect($payload['author_id'])->toEqual($agent->getKey())
        ->and($payload['author_name'])->toBe('Dana Agent')
        ->and($payload['author'])->toMatchArray([
            'id' => $agent->getKey(),
            'name' => 'Dana Agent',
        ]);
});

it('emits a null author for an unauthored (system) reply', function () {
    $ticket = Ticket::factory()->create();

    $reply = $ticket->replies()->create([
        'ticket_id' => $ticket->id,
        'author_type' => null,
        'author_id' => null,
        'body' => 'Automated system message',
        'is_internal_note' => false,
        'type' => 'reply',
    ]);

    $payload = (new ReplyCreated($reply))->broadcastWith();

    expect($payload['author_id'])->toBeNull()
        ->and($payload['author_name'])->toBeNull()
        ->and($payload['author'])->toBeNull();
});

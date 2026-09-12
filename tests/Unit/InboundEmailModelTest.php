<?php

use Escalated\Laravel\Models\InboundEmail;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;

// ticket_id and reply_id are foreign keys. SQLite does not enforce them unless
// asked to, so these cases used to pass with ids nothing pointed at; MySQL and
// PostgreSQL both reject that. Real rows cost one insert and make the
// assertions mean what they say.

it('marks inbound email as processed', function () {
    $inbound = InboundEmail::create([
        'from_email' => 'test@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Test',
        'status' => 'pending',
        'adapter' => 'mailgun',
    ]);

    expect($inbound->isPending())->toBeTrue();

    $ticket = Ticket::factory()->create();
    $reply = Reply::create([
        'ticket_id' => $ticket->id,
        'body' => 'Thanks for getting in touch',
        'type' => 'reply',
    ]);

    $inbound->markProcessed($ticket->id, $reply->id);

    $inbound->refresh();
    expect($inbound->status)->toBe('processed');
    expect($inbound->ticket_id)->toBe($ticket->id);
    expect($inbound->reply_id)->toBe($reply->id);
    expect($inbound->processed_at)->not->toBeNull();
    expect($inbound->isProcessed())->toBeTrue();
});

it('marks inbound email as failed with error message', function () {
    $inbound = InboundEmail::create([
        'from_email' => 'test@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Test',
        'status' => 'pending',
        'adapter' => 'postmark',
    ]);

    $inbound->markFailed('Connection refused');

    $inbound->refresh();
    expect($inbound->status)->toBe('failed');
    expect($inbound->error_message)->toBe('Connection refused');
    expect($inbound->processed_at)->not->toBeNull();
    expect($inbound->isFailed())->toBeTrue();
});

it('marks inbound email as spam', function () {
    $inbound = InboundEmail::create([
        'from_email' => 'spammer@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Buy now!!!',
        'status' => 'pending',
        'adapter' => 'ses',
    ]);

    $inbound->markSpam();

    $inbound->refresh();
    expect($inbound->status)->toBe('spam');
    expect($inbound->processed_at)->not->toBeNull();
});

it('queries by status scopes', function () {
    InboundEmail::create([
        'from_email' => 'a@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Pending',
        'status' => 'pending',
        'adapter' => 'mailgun',
    ]);
    InboundEmail::create([
        'from_email' => 'b@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Processed',
        'status' => 'processed',
        'adapter' => 'mailgun',
    ]);
    InboundEmail::create([
        'from_email' => 'c@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Failed',
        'status' => 'failed',
        'adapter' => 'postmark',
    ]);
    InboundEmail::create([
        'from_email' => 'd@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Spam',
        'status' => 'spam',
        'adapter' => 'ses',
    ]);

    expect(InboundEmail::pending()->count())->toBe(1);
    expect(InboundEmail::processed()->count())->toBe(1);
    expect(InboundEmail::failed()->count())->toBe(1);
    expect(InboundEmail::spam()->count())->toBe(1);
});

it('queries by adapter scope', function () {
    InboundEmail::create([
        'from_email' => 'a@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'MG email',
        'status' => 'processed',
        'adapter' => 'mailgun',
    ]);
    InboundEmail::create([
        'from_email' => 'b@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'PM email',
        'status' => 'processed',
        'adapter' => 'postmark',
    ]);

    expect(InboundEmail::forAdapter('mailgun')->count())->toBe(1);
    expect(InboundEmail::forAdapter('postmark')->count())->toBe(1);
    expect(InboundEmail::forAdapter('ses')->count())->toBe(0);
});

it('belongs to ticket', function () {
    $ticket = Ticket::factory()->create();

    $inbound = InboundEmail::create([
        'from_email' => 'test@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Test',
        'status' => 'processed',
        'adapter' => 'mailgun',
        'ticket_id' => $ticket->id,
    ]);

    expect($inbound->ticket)->toBeInstanceOf(Ticket::class);
    expect($inbound->ticket->id)->toBe($ticket->id);
});

it('marks processed with ticket id only', function () {
    $inbound = InboundEmail::create([
        'from_email' => 'test@example.com',
        'to_email' => 'support@example.com',
        'subject' => 'Test',
        'status' => 'pending',
        'adapter' => 'mailgun',
    ]);

    $ticket = Ticket::factory()->create();

    $inbound->markProcessed($ticket->id);

    $inbound->refresh();
    expect($inbound->ticket_id)->toBe($ticket->id);
    expect($inbound->reply_id)->toBeNull();
});

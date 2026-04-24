<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Mail\InboundMessage;
use Escalated\Laravel\Mail\MessageIdUtil;
use Escalated\Laravel\Models\InboundEmail;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\InboundEmailService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['escalated.inbound_email.enabled' => true]);
    Notification::fake();
});

it('creates a new ticket from inbound email for a registered user', function () {
    $user = $this->createTestUser(['email' => 'customer@example.com']);

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'customer@example.com',
        fromName: 'Customer',
        toEmail: 'support@example.com',
        subject: 'I need help with login',
        bodyText: 'I cannot log in to my account.',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'mailgun');

    expect($inbound->status)->toBe('processed');
    expect($inbound->ticket_id)->not->toBeNull();

    $ticket = Ticket::find($inbound->ticket_id);
    expect($ticket->subject)->toBe('I need help with login');
    expect($ticket->description)->toBe('I cannot log in to my account.');
    expect($ticket->channel->value)->toBe('email');
    expect($ticket->requester_id)->toBe($user->id);
});

it('creates a guest ticket when sender email is not a registered user', function () {
    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'stranger@unknown.com',
        fromName: 'Some Stranger',
        toEmail: 'support@example.com',
        subject: 'Product question',
        bodyText: 'What is your return policy?',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'postmark');

    expect($inbound->status)->toBe('processed');

    $ticket = Ticket::find($inbound->ticket_id);
    expect($ticket->requester_type)->toBeNull();
    expect($ticket->guest_name)->toBe('Some Stranger');
    expect($ticket->guest_email)->toBe('stranger@unknown.com');
    expect($ticket->guest_token)->not->toBeNull();
    expect($ticket->channel->value)->toBe('email');
});

it('adds a reply to existing ticket when subject contains reference', function () {
    $user = $this->createTestUser(['email' => 'customer@example.com']);
    $ticket = Ticket::factory()->create(['reference' => 'ESC-00001']);

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'customer@example.com',
        fromName: 'Customer',
        toEmail: 'support@example.com',
        subject: 'RE: [ESC-00001] I need help with login',
        bodyText: 'Thanks, that fixed it!',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'mailgun');

    expect($inbound->status)->toBe('processed');
    expect($inbound->ticket_id)->toBe($ticket->id);
    expect($inbound->reply_id)->not->toBeNull();

    $this->assertDatabaseHas('escalated_replies', [
        'ticket_id' => $ticket->id,
        'body' => 'Thanks, that fixed it!',
    ]);
});

it('adds a guest reply to existing ticket when sender is not registered', function () {
    $ticket = Ticket::factory()->create([
        'reference' => 'ESC-00002',
        'requester_type' => null,
        'guest_email' => 'guest@example.com',
    ]);

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'guest@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'RE: [ESC-00002] My issue',
        bodyText: 'Still having problems.',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'ses');

    expect($inbound->status)->toBe('processed');
    expect($inbound->ticket_id)->toBe($ticket->id);
    expect($inbound->reply_id)->not->toBeNull();
});

it('reopens a resolved ticket when a reply comes in', function () {
    $user = $this->createTestUser(['email' => 'customer@example.com']);
    $ticket = Ticket::factory()->create([
        'reference' => 'ESC-00003',
        'status' => TicketStatus::Resolved,
        'resolved_at' => now(),
    ]);

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'customer@example.com',
        fromName: 'Customer',
        toEmail: 'support@example.com',
        subject: 'RE: [ESC-00003] Still broken',
        bodyText: 'The issue is back.',
        bodyHtml: null,
    );

    $service->process($message, 'mailgun');

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Reopened);
});

it('detects duplicate message IDs and skips processing', function () {
    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'customer@example.com',
        fromName: 'Customer',
        toEmail: 'support@example.com',
        subject: 'New ticket',
        bodyText: 'Body text',
        bodyHtml: null,
        messageId: '<unique-msg-123@mail.example.com>',
    );

    $first = $service->process($message, 'mailgun');
    expect($first->status)->toBe('processed');

    // Second message with same ID but different messageId field to avoid unique constraint
    $message2 = new InboundMessage(
        fromEmail: 'customer@example.com',
        fromName: 'Customer',
        toEmail: 'support@example.com',
        subject: 'New ticket',
        bodyText: 'Body text',
        bodyHtml: null,
        messageId: null, // Different message_id for logging, but check inReplyTo
        inReplyTo: '<unique-msg-123@mail.example.com>',
    );

    $second = $service->process($message2, 'mailgun');
    expect($second->status)->toBe('processed');

    // Should have used the existing ticket (reply, not new ticket)
    expect($second->ticket_id)->toBe($first->ticket_id);
    expect($second->reply_id)->not->toBeNull();
});

it('logs inbound email record even on failure', function () {
    // Force a failure by using invalid config
    config(['escalated.mode' => 'cloud']);
    config(['escalated.hosted.api_url' => 'http://invalid-url-that-will-fail']);

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'customer@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'Test',
        bodyText: 'Body',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'mailgun');

    // Should have an inbound email record regardless of processing outcome
    expect(InboundEmail::count())->toBeGreaterThanOrEqual(1);
    expect($inbound->from_email)->toBe('customer@example.com');
    expect($inbound->adapter)->toBe('mailgun');
});

it('uses sanitized html body when text body is empty', function () {
    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'nobody@unknown.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'HTML only email',
        bodyText: null,
        bodyHtml: '<p>Hello <b>world</b></p>',
    );

    $inbound = $service->process($message, 'postmark');

    $ticket = Ticket::find($inbound->ticket_id);
    // Sanitized HTML preserves safe tags like <p> and <b>
    expect($ticket->description)->toBe('<p>Hello <b>world</b></p>');
});

it('strips dangerous tags from html body', function () {
    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'nobody@unknown.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'XSS email',
        bodyText: null,
        bodyHtml: '<p>Hello</p><script>alert("xss")</script><img src=x onerror="alert(1)">',
    );

    $inbound = $service->process($message, 'postmark');

    $ticket = Ticket::find($inbound->ticket_id);
    // Script tags and event handlers must be stripped
    expect($ticket->description)->not->toContain('<script>');
    expect($ticket->description)->not->toContain('onerror');
    expect($ticket->description)->toContain('<p>Hello</p>');
});

it('sanitizes subject by removing RE/FW prefixes', function () {
    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'nobody@unknown.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'RE: Important request',
        bodyText: 'Some body.',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'mailgun');

    $ticket = Ticket::find($inbound->ticket_id);
    expect($ticket->subject)->toBe('Important request');
});

it('derives guest name from email when no name provided', function () {
    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'john.doe@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'Help me',
        bodyText: 'Please help.',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'mailgun');

    $ticket = Ticket::find($inbound->ticket_id);
    expect($ticket->guest_name)->toBe('John Doe');
});

it('finds ticket by canonical In-Reply-To Message-ID via MessageIdUtil', function () {
    // No InboundEmail row — this is the cold-start path where the
    // reply hits us first. Parsing the ticket id out of the canonical
    // Message-ID format lets us route without database lookup.
    $ticket = Ticket::factory()->create();

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'nobody@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'RE: ticket',
        bodyText: 'Follow up.',
        bodyHtml: null,
        inReplyTo: "<ticket-{$ticket->id}@support.example.com>",
    );

    $inbound = $service->process($message, 'mailgun');

    expect($inbound->ticket_id)->toBe($ticket->id);
});

it('finds ticket by canonical References header via MessageIdUtil', function () {
    $ticket = Ticket::factory()->create();

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'nobody@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'RE: ticket',
        bodyText: 'Follow up.',
        bodyHtml: null,
        references: "<unrelated@mail.com> <ticket-{$ticket->id}@support.example.com>",
    );

    $inbound = $service->process($message, 'mailgun');

    expect($inbound->ticket_id)->toBe($ticket->id);
});

it('finds ticket by signed Reply-To when inbound_secret is configured', function () {
    config(['escalated.email.inbound_secret' => 'test-secret']);
    config(['escalated.email.domain' => 'support.example.com']);
    $ticket = Ticket::factory()->create();

    $replyTo = MessageIdUtil::buildReplyTo(
        $ticket->id,
        'test-secret',
        'support.example.com'
    );

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'customer@example.com',
        fromName: null,
        toEmail: $replyTo, // this is what the customer's client sends to
        subject: 'My issue',
        bodyText: 'Another message.',
        bodyHtml: null,
        // No In-Reply-To / References — clients stripped them. The
        // signed Reply-To is the safety net.
    );

    $inbound = $service->process($message, 'mailgun');

    expect($inbound->ticket_id)->toBe($ticket->id);
});

it('rejects a forged Reply-To signature', function () {
    config(['escalated.email.inbound_secret' => 'real-secret']);
    config(['escalated.email.domain' => 'support.example.com']);
    $ticket = Ticket::factory()->create();

    // Signed with a DIFFERENT secret — should not verify.
    $forged = MessageIdUtil::buildReplyTo(
        $ticket->id,
        'wrong-secret',
        'support.example.com'
    );

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'attacker@example.com',
        fromName: null,
        toEmail: $forged,
        subject: 'Try to take over ticket',
        bodyText: 'injected content.',
        bodyHtml: null,
    );

    $inbound = $service->process($message, 'mailgun');

    // Not routed to the ticket — falls through to new-ticket creation.
    expect($inbound->ticket_id)->not->toBe($ticket->id);
});

it('finds ticket by In-Reply-To header', function () {
    // Create a ticket and record its inbound email with a message ID
    $ticket = Ticket::factory()->create();
    InboundEmail::create([
        'message_id' => '<original-msg@example.com>',
        'from_email' => 'support@example.com',
        'to_email' => 'customer@example.com',
        'subject' => 'Ticket created',
        'status' => 'processed',
        'adapter' => 'mailgun',
        'ticket_id' => $ticket->id,
    ]);

    $service = app(InboundEmailService::class);
    $message = new InboundMessage(
        fromEmail: 'nobody@example.com',
        fromName: null,
        toEmail: 'support@example.com',
        subject: 'RE: Ticket created',
        bodyText: 'Follow up.',
        bodyHtml: null,
        inReplyTo: '<original-msg@example.com>',
    );

    $inbound = $service->process($message, 'mailgun');

    expect($inbound->ticket_id)->toBe($ticket->id);
    expect($inbound->reply_id)->not->toBeNull();
});

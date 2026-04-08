<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\InboundEmail;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Inbound Email Controller Tests
|--------------------------------------------------------------------------
|
| Note: The inbound routes are only registered when config
| 'escalated.inbound_email.enabled' is true at boot time.
| We use defineEnvironment in Pest.php to enable this for testing,
| OR we define the route URL directly.
|
*/

beforeEach(function () {
    config(['escalated.inbound_email.enabled' => true]);
    Notification::fake();
});

// Helper to get the inbound webhook URL (since routes may not be named if
// the config wasn't set at boot time)
function inboundUrl(string $adapter): string
{
    $prefix = config('escalated.routes.prefix', 'support');

    return "/{$prefix}/inbound/{$adapter}";
}

it('returns 404 when inbound email is disabled', function () {
    config(['escalated.inbound_email.enabled' => false]);

    $response = $this->postJson(inboundUrl('mailgun'), [
        'sender' => 'test@example.com',
    ]);

    $response->assertStatus(404);
});

it('returns 404 for unknown adapter', function () {
    $response = $this->post(inboundUrl('unknown'), [
        'sender' => 'test@example.com',
    ]);

    // The route constraint (mailgun|postmark|ses) should 404 unknown adapters
    $response->assertStatus(404);
});

it('returns 403 when mailgun signature is invalid', function () {
    config(['escalated.inbound_email.mailgun.signing_key' => 'test-key']);

    $response = $this->postJson(inboundUrl('mailgun'), [
        'sender' => 'test@example.com',
        'from' => 'Test <test@example.com>',
        'recipient' => 'support@example.com',
        'subject' => 'Test',
        'body-plain' => 'Body',
        'timestamp' => (string) time(),
        'token' => 'some-token',
        'signature' => 'invalid-signature',
        'attachment-count' => '0',
        'message-headers' => '[]',
    ]);

    $response->assertStatus(403);
    $response->assertJson(['error' => 'Invalid signature.']);
});

it('processes a valid mailgun webhook and creates a ticket', function () {
    $signingKey = 'test-signing-key';
    config(['escalated.inbound_email.mailgun.signing_key' => $signingKey]);

    $timestamp = (string) time();
    $token = 'random-token-abc';
    $signature = hash_hmac('sha256', $timestamp.$token, $signingKey);

    $response = $this->postJson(inboundUrl('mailgun'), [
        'sender' => 'nobody@example.com',
        'from' => 'Nobody <nobody@example.com>',
        'recipient' => 'support@example.com',
        'subject' => 'Feature request',
        'body-plain' => 'Please add dark mode.',
        'attachment-count' => '0',
        'message-headers' => '[]',
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['status', 'id']);
    $response->assertJson(['status' => 'ok']);

    // Verify ticket was created
    $this->assertDatabaseHas(
        Escalated::table('tickets'),
        ['subject' => 'Feature request']
    );

    // Verify inbound email log was created
    expect(InboundEmail::count())->toBe(1);
    expect(InboundEmail::first()->adapter)->toBe('mailgun');
    expect(InboundEmail::first()->status)->toBe('processed');
});

it('processes a valid postmark webhook with token', function () {
    config(['escalated.inbound_email.postmark.token' => 'postmark-secret']);

    $payload = [
        'FromFull' => ['Email' => 'customer@example.com', 'Name' => 'Customer'],
        'ToFull' => [['Email' => 'support@example.com']],
        'Subject' => 'Billing question',
        'TextBody' => 'I have a billing question.',
        'Attachments' => [],
        'Headers' => [],
    ];

    $response = $this->postJson(inboundUrl('postmark'), $payload, [
        'X-Postmark-Token' => 'postmark-secret',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas(
        Escalated::table('tickets'),
        ['subject' => 'Billing question']
    );
});

it('rejects postmark webhook with wrong token', function () {
    config(['escalated.inbound_email.postmark.token' => 'correct-token']);

    $payload = [
        'FromFull' => ['Email' => 'test@example.com', 'Name' => ''],
        'ToFull' => [['Email' => 'support@example.com']],
        'Subject' => 'Test',
        'TextBody' => 'Body',
        'Attachments' => [],
        'Headers' => [],
    ];

    $response = $this->postJson(inboundUrl('postmark'), $payload, [
        'X-Postmark-Token' => 'wrong-token',
    ]);

    $response->assertStatus(403);
});

it('adds reply to existing ticket via subject reference', function () {
    $signingKey = 'test-key-reply';
    config(['escalated.inbound_email.mailgun.signing_key' => $signingKey]);

    $ticket = Ticket::factory()->create(['reference' => 'ESC-00099']);

    $timestamp = (string) time();
    $token = 'reply-token-123';
    $signature = hash_hmac('sha256', $timestamp.$token, $signingKey);

    $response = $this->postJson(inboundUrl('mailgun'), [
        'sender' => 'customer@example.com',
        'from' => 'Customer <customer@example.com>',
        'recipient' => 'support@example.com',
        'subject' => 'RE: [ESC-00099] My ticket',
        'body-plain' => 'This is a follow-up reply.',
        'attachment-count' => '0',
        'message-headers' => '[]',
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
    ]);

    $response->assertStatus(200);

    // Should not create a new ticket
    expect(Ticket::count())->toBe(1);

    // Should have a reply on the existing ticket
    $this->assertDatabaseHas(
        Escalated::table('replies'),
        [
            'ticket_id' => $ticket->id,
            'body' => 'This is a follow-up reply.',
        ]
    );
});

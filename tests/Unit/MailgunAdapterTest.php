<?php

use Escalated\Laravel\Mail\Adapters\MailgunAdapter;
use Illuminate\Http\Request;

it('parses mailgun webhook into inbound message', function () {
    $adapter = new MailgunAdapter;

    $request = Request::create('/inbound/mailgun', 'POST', [
        'sender' => 'customer@example.com',
        'from' => 'John Doe <customer@example.com>',
        'recipient' => 'support@example.com',
        'subject' => 'Help needed',
        'body-plain' => 'I need help with my order.',
        'body-html' => '<p>I need help with my order.</p>',
        'Message-Id' => '<msg-123@example.com>',
        'In-Reply-To' => '<prev-msg@example.com>',
        'message-headers' => json_encode([
            ['From', 'John Doe <customer@example.com>'],
            ['Subject', 'Help needed'],
        ]),
        'attachment-count' => '0',
    ]);

    $message = $adapter->parseRequest($request);

    expect($message->fromEmail)->toBe('customer@example.com');
    expect($message->fromName)->toBe('John Doe');
    expect($message->toEmail)->toBe('support@example.com');
    expect($message->subject)->toBe('Help needed');
    expect($message->bodyText)->toBe('I need help with my order.');
    expect($message->bodyHtml)->toBe('<p>I need help with my order.</p>');
    expect($message->messageId)->toBe('<msg-123@example.com>');
    expect($message->inReplyTo)->toBe('<prev-msg@example.com>');
});

it('extracts email from "Name <email>" format', function () {
    $adapter = new MailgunAdapter;

    $request = Request::create('/inbound/mailgun', 'POST', [
        'sender' => 'Jane Doe <jane@example.com>',
        'from' => '"Jane Doe" <jane@example.com>',
        'recipient' => 'support@test.com',
        'subject' => 'Test',
        'body-plain' => 'Body',
        'attachment-count' => '0',
        'message-headers' => '[]',
    ]);

    $message = $adapter->parseRequest($request);

    expect($message->fromEmail)->toBe('jane@example.com');
    expect($message->fromName)->toBe('Jane Doe');
});

it('verifies valid mailgun signature', function () {
    $signingKey = 'test-signing-key-12345';
    config(['escalated.inbound_email.mailgun.signing_key' => $signingKey]);

    $adapter = new MailgunAdapter;

    $timestamp = (string) time();
    $token = 'random-token-value';
    $signature = hash_hmac('sha256', $timestamp.$token, $signingKey);

    $request = Request::create('/inbound/mailgun', 'POST', [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
    ]);

    expect($adapter->verifyRequest($request))->toBeTrue();
});

it('rejects invalid mailgun signature', function () {
    config(['escalated.inbound_email.mailgun.signing_key' => 'correct-key']);

    $adapter = new MailgunAdapter;

    $request = Request::create('/inbound/mailgun', 'POST', [
        'timestamp' => (string) time(),
        'token' => 'some-token',
        'signature' => 'definitely-wrong-signature',
    ]);

    expect($adapter->verifyRequest($request))->toBeFalse();
});

it('rejects mailgun request with stale timestamp', function () {
    $signingKey = 'test-key';
    config(['escalated.inbound_email.mailgun.signing_key' => $signingKey]);

    $adapter = new MailgunAdapter;

    $timestamp = (string) (time() - 400); // 6+ minutes ago
    $token = 'some-token';
    $signature = hash_hmac('sha256', $timestamp.$token, $signingKey);

    $request = Request::create('/inbound/mailgun', 'POST', [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => $signature,
    ]);

    expect($adapter->verifyRequest($request))->toBeFalse();
});

it('rejects when no signing key is configured', function () {
    config(['escalated.inbound_email.mailgun.signing_key' => null]);

    $adapter = new MailgunAdapter;

    $request = Request::create('/inbound/mailgun', 'POST', [
        'timestamp' => (string) time(),
        'token' => 'token',
        'signature' => 'sig',
    ]);

    expect($adapter->verifyRequest($request))->toBeFalse();
});

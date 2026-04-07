<?php

use Escalated\Laravel\Mail\Adapters\SesAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

it('parses ses sns notification into inbound message', function () {
    $adapter = new SesAdapter;

    $rawMime = implode("\r\n", [
        'Content-Type: multipart/alternative; boundary="boundary123"',
        '',
        '--boundary123',
        'Content-Type: text/plain',
        '',
        'Plain text body from SES.',
        '--boundary123',
        'Content-Type: text/html',
        '',
        '<p>HTML body from SES.</p>',
        '--boundary123--',
    ]);

    $snsPayload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'mail' => [
                'source' => 'sender@example.com',
                'destination' => ['support@example.com'],
                'commonHeaders' => [
                    'from' => ['Sender Name <sender@example.com>'],
                    'to' => ['support@example.com'],
                    'subject' => 'Help request via SES',
                    'messageId' => '<ses-msg-456@example.com>',
                ],
                'headers' => [
                    ['name' => 'In-Reply-To', 'value' => '<prev@example.com>'],
                    ['name' => 'References', 'value' => '<ref1@example.com>'],
                ],
            ],
            'content' => $rawMime,
        ]),
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    $message = $adapter->parseRequest($request);

    expect($message->fromEmail)->toBe('sender@example.com');
    expect($message->fromName)->toBe('Sender Name');
    expect($message->toEmail)->toBe('support@example.com');
    expect($message->subject)->toBe('Help request via SES');
    expect($message->bodyText)->toBe('Plain text body from SES.');
    expect($message->bodyHtml)->toBe('<p>HTML body from SES.</p>');
    expect($message->messageId)->toBe('<ses-msg-456@example.com>');
    expect($message->inReplyTo)->toBe('<prev@example.com>');
    expect($message->references)->toBe('<ref1@example.com>');
});

it('handles sns subscription confirmation', function () {
    $adapter = new SesAdapter;

    $snsPayload = [
        'Type' => 'SubscriptionConfirmation',
        'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/confirm?token=abc',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789:escalated-inbound',
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    // Mock the HTTP call for confirmation (it would fail in test, but parseRequest still returns a message)
    Http::fake([
        'sns.us-east-1.amazonaws.com/*' => Http::response('OK', 200),
    ]);

    $message = $adapter->parseRequest($request);

    expect($message->fromEmail)->toBe('sns-confirmation@amazonaws.com');
    expect($message->subject)->toBe('[SNS Subscription Confirmation]');
});

it('parses single-part mime message', function () {
    $adapter = new SesAdapter;

    $rawMime = implode("\r\n", [
        'Content-Type: text/plain',
        '',
        'Simple plain text email.',
    ]);

    $snsPayload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'mail' => [
                'source' => 'test@example.com',
                'destination' => ['support@example.com'],
                'commonHeaders' => [
                    'from' => ['test@example.com'],
                    'to' => ['support@example.com'],
                    'subject' => 'Simple email',
                ],
                'headers' => [],
            ],
            'content' => $rawMime,
        ]),
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    $message = $adapter->parseRequest($request);

    expect($message->fromEmail)->toBe('test@example.com');
    expect($message->bodyText)->toBe('Simple plain text email.');
});

it('rejects when topic arn does not match', function () {
    config(['escalated.inbound_email.ses.topic_arn' => 'arn:aws:sns:us-east-1:123:correct-topic']);

    $adapter = new SesAdapter;

    $snsPayload = [
        'Type' => 'Notification',
        'TopicArn' => 'arn:aws:sns:us-east-1:999:wrong-topic',
        'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
        'Signature' => base64_encode('fake-signature'),
        'Message' => '{}',
        'MessageId' => 'msg-1',
        'Timestamp' => now()->toISOString(),
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    expect($adapter->verifyRequest($request))->toBeFalse();
});

it('rejects invalid signing cert url', function () {
    config(['escalated.inbound_email.ses.topic_arn' => null]);

    $adapter = new SesAdapter;

    $snsPayload = [
        'Type' => 'Notification',
        'SigningCertURL' => 'https://evil.com/cert.pem',
        'Signature' => base64_encode('fake'),
        'Message' => '{}',
        'MessageId' => 'msg-1',
        'Timestamp' => now()->toISOString(),
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    expect($adapter->verifyRequest($request))->toBeFalse();
});

it('rejects non-https signing cert url', function () {
    config(['escalated.inbound_email.ses.topic_arn' => null]);

    $adapter = new SesAdapter;

    $snsPayload = [
        'Type' => 'Notification',
        'SigningCertURL' => 'http://sns.us-east-1.amazonaws.com/cert.pem',
        'Signature' => base64_encode('fake'),
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    expect($adapter->verifyRequest($request))->toBeFalse();
});

it('parses base64 encoded mime part', function () {
    $adapter = new SesAdapter;

    $encodedBody = base64_encode('Decoded plain text body.');

    $rawMime = implode("\r\n", [
        'Content-Type: multipart/alternative; boundary="bound456"',
        '',
        '--bound456',
        'Content-Type: text/plain',
        'Content-Transfer-Encoding: base64',
        '',
        $encodedBody,
        '--bound456--',
    ]);

    $snsPayload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'mail' => [
                'source' => 'test@example.com',
                'destination' => ['support@example.com'],
                'commonHeaders' => [
                    'from' => ['test@example.com'],
                    'to' => ['support@example.com'],
                    'subject' => 'Base64 test',
                ],
                'headers' => [],
            ],
            'content' => $rawMime,
        ]),
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    $message = $adapter->parseRequest($request);

    expect($message->bodyText)->toBe('Decoded plain text body.');
});

it('parses notification without raw content', function () {
    $adapter = new SesAdapter;

    $snsPayload = [
        'Type' => 'Notification',
        'Message' => json_encode([
            'mail' => [
                'source' => 'noreply@example.com',
                'destination' => ['support@example.com'],
                'commonHeaders' => [
                    'from' => ['No Reply <noreply@example.com>'],
                    'to' => ['support@example.com'],
                    'subject' => 'No content email',
                ],
                'headers' => [],
            ],
            // No 'content' key
        ]),
    ];

    $request = Request::create('/inbound/ses', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($snsPayload));

    $message = $adapter->parseRequest($request);

    expect($message->fromEmail)->toBe('noreply@example.com');
    expect($message->fromName)->toBe('No Reply');
    expect($message->subject)->toBe('No content email');
    expect($message->bodyText)->toBeNull();
    expect($message->bodyHtml)->toBeNull();
});

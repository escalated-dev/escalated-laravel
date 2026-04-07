<?php

use Escalated\Laravel\Mail\Adapters\PostmarkAdapter;
use Illuminate\Http\Request;

it('parses postmark inbound webhook json', function () {
    $adapter = new PostmarkAdapter;

    $payload = [
        'FromFull' => ['Email' => 'sender@example.com', 'Name' => 'Sender Name'],
        'ToFull' => [['Email' => 'support@example.com', 'Name' => 'Support']],
        'Subject' => 'Need help',
        'TextBody' => 'Plain text body here.',
        'HtmlBody' => '<p>HTML body here.</p>',
        'MessageID' => 'pm-msg-123',
        'Headers' => [
            ['Name' => 'In-Reply-To', 'Value' => '<prev@example.com>'],
            ['Name' => 'References', 'Value' => '<ref1@example.com> <ref2@example.com>'],
        ],
        'Attachments' => [],
    ];

    $request = Request::create('/inbound/postmark', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $message = $adapter->parseRequest($request);

    expect($message->fromEmail)->toBe('sender@example.com');
    expect($message->fromName)->toBe('Sender Name');
    expect($message->toEmail)->toBe('support@example.com');
    expect($message->subject)->toBe('Need help');
    expect($message->bodyText)->toBe('Plain text body here.');
    expect($message->bodyHtml)->toBe('<p>HTML body here.</p>');
    expect($message->messageId)->toBe('pm-msg-123');
    expect($message->inReplyTo)->toBe('<prev@example.com>');
    expect($message->references)->toBe('<ref1@example.com> <ref2@example.com>');
});

it('parses postmark attachments from base64', function () {
    $adapter = new PostmarkAdapter;

    $content = base64_encode('Hello attachment content');

    $payload = [
        'FromFull' => ['Email' => 'test@example.com', 'Name' => ''],
        'ToFull' => [['Email' => 'support@example.com']],
        'Subject' => 'With attachment',
        'TextBody' => 'See attachment.',
        'Attachments' => [
            [
                'Name' => 'document.pdf',
                'Content' => $content,
                'ContentType' => 'application/pdf',
                'ContentLength' => strlen('Hello attachment content'),
            ],
        ],
        'Headers' => [],
    ];

    $request = Request::create('/inbound/postmark', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($payload));

    $message = $adapter->parseRequest($request);

    expect($message->attachments)->toHaveCount(1);
    expect($message->attachments[0]['filename'])->toBe('document.pdf');
    expect($message->attachments[0]['contentType'])->toBe('application/pdf');
    expect($message->attachments[0]['content'])->toBe('Hello attachment content');
});

it('verifies postmark token from header', function () {
    config(['escalated.inbound_email.postmark.token' => 'secret-token-123']);

    $adapter = new PostmarkAdapter;

    $request = Request::create('/inbound/postmark', 'POST');
    $request->headers->set('X-Postmark-Token', 'secret-token-123');

    expect($adapter->verifyRequest($request))->toBeTrue();
});

it('rejects invalid postmark token', function () {
    config(['escalated.inbound_email.postmark.token' => 'correct-token']);

    $adapter = new PostmarkAdapter;

    $request = Request::create('/inbound/postmark', 'POST');
    $request->headers->set('X-Postmark-Token', 'wrong-token');

    expect($adapter->verifyRequest($request))->toBeFalse();
});

it('rejects request when no postmark token is configured', function () {
    config(['escalated.inbound_email.postmark.token' => null]);

    $adapter = new PostmarkAdapter;

    $request = Request::create('/inbound/postmark', 'POST');

    expect($adapter->verifyRequest($request))->toBeFalse();
});

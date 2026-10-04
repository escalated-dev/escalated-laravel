<?php

use Escalated\Laravel\Bridge\ContextHandler;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SlackInboundEvent;
use Escalated\Laravel\Models\SlackThread;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketActivity;
use Escalated\Laravel\Notifications\TicketReplyNotification;
use Escalated\Laravel\Services\SlackInboxProcessor;
use Escalated\Laravel\Tests\Fixtures\ExercisesSlackInbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

uses(ExercisesSlackInbox::class);
beforeEach(fn () => $this->prepareSlackInbox());
afterEach(fn () => $this->cleanSlackInbox());

it('durably accepts signed events without doing ticket work during acknowledgement', function () {
    Event::fake([TicketCreated::class]);
    $this->slackRequest()->assertStatus(202)->assertJsonPath('accepted', true)->assertJsonPath('event_id', 'Ev123');
    $this->slackRequest()->assertStatus(202);
    expect(SlackInboundEvent::count())->toBe(1)->and(Ticket::count())->toBe(0)
        ->and(Escalated::db()->transactionLevel())->toBe(0);
    $record = SlackInboundEvent::sole();
    expect($record->getRawOriginal('payload'))->not->toContain('Parcel')->and($record->payload['event']['user'])->toBe('U123');
    Event::assertNotDispatched(TicketCreated::class);
});

it('creates one ticket and one public reply with the mapped requester and final after-commit state', function () {
    $observed = [];
    Event::listen(TicketCreated::class, function ($event) use (&$observed) {
        $observed[] = [Escalated::db()->transactionLevel(), $event->ticket->channel->value, SlackThread::sole()->ticket_id, SlackInboundEvent::first()->status];
    });
    $this->slackRequest()->assertStatus(202);
    $processor = app(SlackInboxProcessor::class);
    expect($processor->process(SlackInboundEvent::sole()->id))->toBe('processed');
    $ticket = Ticket::sole();
    expect($ticket->requester_id)->toBe($this->recipient->id)->and($ticket->description)->not->toContain('<script>')
        ->and($ticket->description)->toContain('&lt;script&gt;')->and($observed)->toBe([[0, 'slack', $ticket->id, 'processed']]);
    $replyPayload = $this->slackPayload(['event_id' => 'Ev124', 'event' => ['ts' => '1234567891.000100', 'thread_ts' => '1234567890.000100', 'text' => 'More detail']]);
    $this->slackRequest($replyPayload)->assertStatus(202);
    $record = SlackInboundEvent::where('event_id', 'Ev124')->sole();
    Event::listen(ReplyCreated::class, function ($event) use (&$observed) {
        $observed[] = [Escalated::db()->transactionLevel(), $event->reply->metadata['source'], SlackInboundEvent::where('event_id', 'Ev124')->sole()->status];
    });
    expect($processor->process($record->id))->toBe('processed')->and($processor->process($record->id))->toBe('processed');
    expect(Ticket::count())->toBe(1)->and(Reply::count())->toBe(1)->and(Reply::sole()->author_id)->toBe($this->recipient->id)
        ->and(Reply::sole()->is_internal_note)->toBeFalse()->and($observed[1])->toBe([0, 'slack', 'processed']);
});

it('uses a named contact without creating a login or verified mailbox grant', function () {
    config(['escalated.slack.apps.default.users.T123.U123' => ['name' => 'Slack recipient', 'email' => 'recipient@host-mapped.test']]);
    $this->slackRequest()->assertStatus(202);
    expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id))->toBe('processed');
    $ticket = Ticket::sole();
    expect($ticket->contact_id)->toBe(Contact::sole()->id)->and($ticket->guest_verified_email)->toBeNull()
        ->and($ticket->guest_access_hash)->toBeNull()->and($ticket->requester_id)->toBeNull();
    $ticket->update(['assigned_to' => $this->actor->id]);
    Notification::fake();
    $this->slackRequest($this->slackPayload(['event_id' => 'Ev124', 'event' => ['ts' => '1234567891.000100', 'thread_ts' => '1234567890.000100']]))->assertStatus(202);
    expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::where('event_id', 'Ev124')->sole()->id))->toBe('processed')
        ->and(Reply::sole()->author_type)->toBe((new Contact)->getMorphClass())
        ->and($ticket->fresh()->first_response_at)->toBeNull();
    Notification::assertSentTo($this->actor, TicketReplyNotification::class);
});

it('does not overwrite an existing first response timestamp on later agent replies', function () {
    $this->slackRequest()->assertStatus(202);
    app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id);
    $ticket = Ticket::sole();
    $ticket->addReply($this->actor, 'First response');
    $first = $ticket->fresh()->first_response_at;
    expect($first)->not->toBeNull();
    $this->travel(5)->minutes();
    $ticket->refresh()->addReply($this->actor, 'Later response');
    expect($ticket->fresh()->first_response_at->eq($first))->toBeTrue();
});

it('rejects a mapped non-agent replying to another requesters thread', function (string $identity) {
    $this->slackRequest()->assertStatus(202);
    $processor = app(SlackInboxProcessor::class);
    $processor->process(SlackInboundEvent::sole()->id);
    $other = $this->createTestUser(['email' => 'other-slack@example.test']);
    config(['escalated.slack.apps.default.users.T123.UOTHER' => $identity === 'host'
        ? ['id' => $other->id] : ['name' => 'Other contact', 'email' => 'other-contact@example.test']]);
    $this->slackRequest($this->slackPayload(['event_id' => 'Ev124', 'event' => ['user' => 'UOTHER', 'ts' => '1234567891.000100', 'thread_ts' => '1234567890.000100']]))->assertStatus(202);
    expect($processor->process(SlackInboundEvent::where('event_id', 'Ev124')->sole()->id))->toBe('pending')
        ->and(Reply::count())->toBe(0)->and(Ticket::count())->toBe(1);
})->with(['host', 'contact']);

it('does not duplicate a committed ticket if an after-commit listener fails', function () {
    $this->slackRequest()->assertStatus(202);
    Event::listen(TicketCreated::class, fn () => throw new RuntimeException('notification transport failed'));
    $processor = app(SlackInboxProcessor::class);
    $id = SlackInboundEvent::sole()->id;
    expect($processor->process($id))->toBe('processed')->and($processor->process($id))->toBe('processed')
        ->and(Ticket::count())->toBe(1)->and(SlackInboundEvent::sole()->status)->toBe('processed');
});

it('rejects unauthenticated and stale callbacks before challenge or persistence', function () {
    $this->slackRequest(headers: ['HTTP_X_SLACK_SIGNATURE' => ''])->assertUnauthorized();
    $this->slackRequest(timestamp: now()->subMinutes(6)->timestamp)->assertUnauthorized();
    $this->slackRequest(timestamp: now()->addMinutes(6)->timestamp)->assertUnauthorized();
    $challenge = ['type' => 'url_verification', 'challenge' => 'hello'];
    $this->slackRequest($challenge, headers: ['HTTP_X_SLACK_SIGNATURE' => 'v0=bad'])->assertUnauthorized();
    $this->slackRequest($challenge)->assertOk()->assertJsonPath('challenge', 'hello');
    expect(SlackInboundEvent::count())->toBe(0);
});

it('rejects a wrong app and conflicting replay data', function () {
    $this->slackRequest($this->slackPayload(['api_app_id' => 'AOTHER']))->assertForbidden();
    $this->slackRequest()->assertStatus(202);
    $this->slackRequest($this->slackPayload(['event' => ['text' => 'changed']]))->assertStatus(409);
    $this->slackRequest($this->slackPayload(['event_id' => 'EvOTHER']))->assertStatus(409);
    expect(SlackInboundEvent::count())->toBe(1);
});

it('acknowledges authenticated events from unmapped workspaces channels and direct messages without storing them', function (array $replace) {
    $this->slackRequest($this->slackPayload($replace))->assertOk()->assertExactJson(['ignored' => true]);
    expect(SlackInboundEvent::count())->toBe(0);
})->with([
    'unmapped workspace' => [['team_id' => 'TOTHER']],
    'unmapped channel' => [['event' => ['channel' => 'COTHER']]],
    'direct message' => [['event' => ['channel' => 'D123']]],
    'whitespace-only text' => [['event' => ['text' => " \n\t "]]],
]);

it('accepts text up to the Slack message limit and processes it without truncation', function () {
    $text = str_repeat('Parcel 📦', 5000);
    expect(strlen($text))->toBeGreaterThan(16384)->and(mb_strlen($text))->toBe(40000);
    $this->slackRequest($this->slackPayload(['event' => ['text' => $text]]))->assertStatus(202);
    expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id))->toBe('processed')
        ->and(Ticket::sole()->description)->toBe(nl2br(e($text)));
});

function expectTruncatedSlackBody(string $body, string $text): void
{
    $note = SlackInboxProcessor::TRUNCATION_NOTE;
    expect(strlen($body))->toBeLessThanOrEqual(SlackInboxProcessor::MAX_BODY_BYTES)
        ->and(mb_check_encoding($body, 'UTF-8'))->toBeTrue()->and($body)->toEndWith($note);
    $prefix = substr($body, 0, -strlen($note));
    $cut = htmlspecialchars_decode(str_replace('<br />', '', $prefix), ENT_QUOTES);
    // The kept text is an exact leading slice of the message, escaped whole.
    expect($text)->toStartWith($cut)->and(nl2br(e($cut)))->toBe($prefix)
        ->and(mb_strlen($cut))->toBeGreaterThan(1000)->toBeLessThan(mb_strlen($text));
}

function expectFullSlackTextAttached(Model $attachable, string $text): void
{
    $attachment = Attachment::sole();
    expect($attachment->attachable->is($attachable))->toBeTrue()
        ->and($attachment->mime_type)->toBe('text/plain')->and($attachment->original_filename)->toEndWith('.txt')
        ->and($attachment->disk)->toBe('local')->and($attachment->size)->toBe(strlen($text))
        ->and(Storage::disk('local')->get($attachment->path))->toBe($text);
}

it('truncates a root message too large for the ticket and attaches the full text privately', function () {
    Storage::fake('local');
    $text = str_repeat('📦', 40000);
    $this->slackRequest($this->slackPayload(['event' => ['text' => $text]]))->assertStatus(202);
    $id = SlackInboundEvent::sole()->id;
    expect(app(SlackInboxProcessor::class)->process($id))->toBe('processed');
    $ticket = Ticket::sole();
    expectTruncatedSlackBody($ticket->description, $text);
    expectFullSlackTextAttached($ticket, $text);
    expect(SlackInboundEvent::sole()->last_error)->toBeNull();
});

it('cuts escaped and multi-line text on a character boundary before escaping overflows', function () {
    Storage::fake('local');
    $text = str_repeat("&lt;é\n", 12000).str_repeat('📦', 9000);
    $this->slackRequest($this->slackPayload(['event' => ['text' => $text]]))->assertStatus(202);
    expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id))->toBe('processed');
    $decoded = SlackInboxProcessor::decodeEntities($text);
    expectTruncatedSlackBody(Ticket::sole()->description, $decoded);
    expectFullSlackTextAttached(Ticket::sole(), $decoded);
});

it('truncates an oversized thread reply and attaches the full text to that reply', function () {
    Storage::fake('local');
    $this->slackRequest()->assertStatus(202);
    $processor = app(SlackInboxProcessor::class);
    $processor->process(SlackInboundEvent::sole()->id);
    $text = str_repeat('📦', 40000);
    $this->slackRequest($this->slackPayload(['event_id' => 'Ev124', 'event' => ['ts' => '1234567891.000100',
        'thread_ts' => '1234567890.000100', 'text' => $text]]))->assertStatus(202);
    expect($processor->process(SlackInboundEvent::where('event_id', 'Ev124')->sole()->id))->toBe('processed');
    $reply = Reply::sole();
    expectTruncatedSlackBody($reply->body, $text);
    expectFullSlackTextAttached($reply, $text);
    expect($reply->is_internal_note)->toBeFalse()->and(Ticket::sole()->attachments()->count())->toBe(0);
});

it('retries a rolled back attempt without duplicating the attachment or leaving its file', function (string $failure) {
    Storage::fake('local');
    $text = str_repeat('📦', 40000);
    $this->slackRequest($this->slackPayload(['event' => ['text' => $text]]))->assertStatus(202);
    $id = SlackInboundEvent::sole()->id;
    $processor = app(SlackInboxProcessor::class);
    $fail = true;
    $throwOnce = function () use (&$fail) {
        if ($fail) {
            $fail = false;
            throw new RuntimeException('write failed');
        }
    };
    // Fail while recording the attachment, or after it while completing the receipt.
    $failure === 'attachment' ? Attachment::created($throwOnce)
        : SlackInboundEvent::updating(fn ($event) => $event->status === 'processed' ? $throwOnce() : null);
    expect($processor->process($id))->toBe('pending')->and(Ticket::count())->toBe(0)->and(Attachment::count())->toBe(0)
        ->and(SlackInboundEvent::sole()->last_error)->toBe($failure === 'attachment' ? 'attachment_storage_failed' : 'RuntimeException')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    SlackInboundEvent::whereKey($id)->update(['available_at' => now()]);
    expect($processor->process($id))->toBe('processed')->and($processor->process($id))->toBe('processed')
        ->and(Ticket::count())->toBe(1)->and(Attachment::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
    expectFullSlackTextAttached(Ticket::sole(), $text);
})->with(['attachment', 'receipt']);

it('dead letters oversized text only when the full text cannot be stored', function () {
    config(['escalated.slack.max_attempts' => 2, 'escalated.storage.disk' => 'slack-broken']);
    $broken = Mockery::mock(FilesystemAdapter::class);
    $broken->shouldReceive('put')->andReturn(false);
    Storage::set('slack-broken', $broken);
    $text = str_repeat('&lt;', 40000);
    $this->slackRequest($this->slackPayload(['event' => ['text' => $text]]))->assertStatus(202);
    $id = SlackInboundEvent::sole()->id;
    $processor = app(SlackInboxProcessor::class);
    expect($processor->process($id))->toBe('pending');
    SlackInboundEvent::whereKey($id)->update(['available_at' => now()]);
    expect($processor->process($id))->toBe('failed')->and(Ticket::count())->toBe(0)->and(Attachment::count())->toBe(0);
    $record = SlackInboundEvent::sole();
    expect($record->last_error)->toBe('attachment_storage_failed')->and($record->attempts)->toBe(2)
        ->and($record->payload['event']['text'])->toBe($text);
});

it('decodes Slack entities once before escaping the ticket and reply text', function () {
    $this->slackRequest($this->slackPayload(['event' => ['text' => "Fish &amp; chips &lt;b&gt; &amp;lt;\nnext"]]))->assertStatus(202);
    $processor = app(SlackInboxProcessor::class);
    expect($processor->process(SlackInboundEvent::sole()->id))->toBe('processed');
    $ticket = Ticket::sole();
    expect($ticket->subject)->toBe('Fish & chips <b> &lt; next')
        ->and($ticket->description)->toBe(nl2br(e("Fish & chips <b> &lt;\nnext")));
    $this->slackRequest($this->slackPayload(['event_id' => 'Ev124', 'event' => ['ts' => '1234567891.000100',
        'thread_ts' => '1234567890.000100', 'text' => 'A &amp; B']]))->assertStatus(202);
    expect($processor->process(SlackInboundEvent::where('event_id', 'Ev124')->sole()->id))->toBe('processed')
        ->and(Reply::sole()->body)->toBe('A &amp; B');
});

it('treats a deleted linked ticket as terminal instead of retrying', function (bool $force) {
    $this->slackRequest()->assertStatus(202);
    $processor = app(SlackInboxProcessor::class);
    $processor->process(SlackInboundEvent::sole()->id);
    $force ? Ticket::sole()->forceDelete() : Ticket::sole()->delete();
    $this->slackRequest($this->slackPayload(['event_id' => 'Ev124', 'event' => ['ts' => '1234567891.000100', 'thread_ts' => '1234567890.000100']]))->assertStatus(202);
    $id = SlackInboundEvent::where('event_id', 'Ev124')->sole()->id;
    expect($processor->process($id))->toBe('failed');
    $record = SlackInboundEvent::findOrFail($id);
    expect($record->last_error)->toBe('ticket_missing')->and($record->attempts)->toBe(1)->and(Reply::count())->toBe(0);
})->with(['soft deleted' => false, 'purged' => true]);

it('acknowledges a second app delivering a message another app already recorded', function () {
    config(['escalated.slack.apps.second' => array_replace(config('escalated.slack.apps.default'), ['app_id' => 'A456'])]);
    $this->slackRequest()->assertStatus(202);
    $raw = json_encode($this->slackPayload(['api_app_id' => 'A456', 'event_id' => 'Ev999']), JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->timestamp;
    $this->call('POST', '/support/inbound/slack/second', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$raw, 'fixture-secret')], $raw)
        ->assertOk()->assertExactJson(['ignored' => true]);
    expect(SlackInboundEvent::count())->toBe(1)->and(SlackInboundEvent::sole()->app_key)->toBe('default');
});

it('ignores bot edits deletion hidden and non-message events', function (array $event) {
    $this->slackRequest($this->slackPayload(['event' => $event]))->assertOk()->assertJsonPath('ignored', true);
    expect(SlackInboundEvent::count())->toBe(0);
})->with([[['bot_id' => 'B123']], [['subtype' => 'message_changed']], [['subtype' => 'message_deleted']], [['hidden' => true]], [['type' => 'reaction_added']]]);

it('refuses to acknowledge an event inside a host transaction that could roll back', function () {
    Escalated::db()->beginTransaction();
    $this->slackRequest()->assertStatus(503);
    Escalated::db()->rollBack();
    expect(SlackInboundEvent::count())->toBe(0);
});

it('retries an out-of-order reply after the root message arrives', function () {
    $reply = $this->slackPayload(['event_id' => 'Ev124', 'event' => ['ts' => '1234567891.000100', 'thread_ts' => '1234567890.000100']]);
    $this->slackRequest($reply)->assertStatus(202);
    $processor = app(SlackInboxProcessor::class);
    $id = SlackInboundEvent::sole()->id;
    expect($processor->process($id))->toBe('deferred')->and(Ticket::count())->toBe(0);
    $this->slackRequest()->assertStatus(202);
    expect($processor->process(SlackInboundEvent::where('event_id', 'Ev123')->sole()->id))->toBe('processed');
    $this->travel(61)->seconds();
    expect($processor->process($id))->toBe('processed')->and(Reply::count())->toBe(1)->and(Ticket::count())->toBe(1);
});

it('rolls back failed aggregate writes and retains a retryable inbox receipt', function () {
    Event::fake([TicketCreated::class]);
    $this->slackRequest()->assertStatus(202);
    $fail = true;
    TicketActivity::creating(function () use (&$fail) {
        if ($fail) {
            throw new RuntimeException('write failed');
        }
    });
    $id = SlackInboundEvent::sole()->id;
    expect(app(SlackInboxProcessor::class)->process($id))->toBe('pending')->and(Ticket::count())->toBe(0)->and(SlackThread::count())->toBe(0);
    expect(SlackInboundEvent::sole()->attempts)->toBe(1);
    Event::assertNotDispatched(TicketCreated::class);
    $fail = false;
    $this->travel(61)->seconds();
    expect(app(SlackInboxProcessor::class)->process($id))->toBe('processed')->and(Ticket::count())->toBe(1);
});

it('dead letters an unmapped identity and allows an explicit tenant-scoped retry after mapping', function () {
    config(['escalated.slack.apps.default.users' => [], 'escalated.slack.max_attempts' => 1]);
    $this->slackRequest()->assertStatus(202);
    $id = SlackInboundEvent::sole()->id;
    $this->artisan('escalated:slack:process')->assertFailed();
    expect(SlackInboundEvent::sole()->status)->toBe('failed')->and(Ticket::count())->toBe(0);
    config(['escalated.slack.apps.default.users.T123.U123' => ['id' => $this->recipient->id]]);
    $this->artisan('escalated:slack:process', ['--retry' => $id])->assertSuccessful();
    expect(SlackInboundEvent::sole()->status)->toBe('processed')->and(Ticket::count())->toBe(1);
});

it('rolls back a failed reply and will not reset its completed receipt for another retry', function () {
    $this->slackRequest()->assertStatus(202);
    $processor = app(SlackInboxProcessor::class);
    $processor->process(SlackInboundEvent::sole()->id);
    $this->slackRequest($this->slackPayload(['event_id' => 'Ev124', 'event' => ['ts' => '1234567891.000100', 'thread_ts' => '1234567890.000100']]))->assertStatus(202);
    Event::fake([ReplyCreated::class]);
    $fail = true;
    TicketActivity::creating(function () use (&$fail) {
        if ($fail) {
            throw new RuntimeException('reply activity failed');
        }
    });
    $id = SlackInboundEvent::where('event_id', 'Ev124')->sole()->id;
    config(['escalated.slack.max_attempts' => 1]);
    expect($processor->process($id))->toBe('failed')->and(Reply::count())->toBe(0);
    Event::assertNotDispatched(ReplyCreated::class);
    $fail = false;
    $this->artisan('escalated:slack:process', ['--retry' => $id])->assertSuccessful();
    $this->artisan('escalated:slack:process', ['--retry' => $id])->assertFailed();
    expect(SlackInboundEvent::findOrFail($id)->status)->toBe('processed')->and(Reply::count())->toBe(1);
    Event::assertDispatchedTimes(ReplyCreated::class, 1);
});

it('subscribes to the plugin hook and re-verifies its signed bytes at the host', function () {
    $raw = json_encode($this->slackPayload(), JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->timestamp;
    $data = ['raw_body_base64' => base64_encode($raw), 'timestamp' => $timestamp,
        'signature' => 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$raw, 'fixture-secret'),
        'tenant_id' => 'forged', 'event' => ['text' => 'forged']];
    $handler = new ContextHandler;
    $handler->setCurrentPlugin('slack');
    expect($handler->handle('ctx.emit', ['hook' => 'slack.message.received', 'data' => $data]))
        ->toBe(['accepted' => true, 'event_id' => 'Ev123']);
    expect(SlackInboundEvent::sole()->payload['event']['text'])->toBe($this->slackPayload()['event']['text']);
    $unmapped = json_encode($this->slackPayload(['event_id' => 'Ev125', 'event' => ['channel' => 'COTHER']]), JSON_THROW_ON_ERROR);
    expect($handler->handle('ctx.emit', ['hook' => 'slack.message.received', 'data' => ['raw_body_base64' => base64_encode($unmapped),
        'timestamp' => $timestamp, 'signature' => 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$unmapped, 'fixture-secret')]]))
        ->toBe(['ignored' => true]);
    $data['signature'] = 'v0=bad';
    expect(fn () => $handler->handle('ctx.emit', ['hook' => 'slack.message.received', 'data' => $data]))
        ->toThrow(HttpException::class);
});

it('deduplicates concurrent requesters and deliveries and serializes workers on a real server database', function () {
    if (Escalated::db()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Concurrent-process row locking is exercised by the PostgreSQL and MySQL suite legs.');
    }
    $fixturePath = tempnam(sys_get_temp_dir(), 'esc-slack-race-');
    $workers = [];
    try {
        foreach (['contact', 'receive', 'process'] as $mode) {
            $fixture = ['mode' => $mode, 'payload' => $this->slackPayload(), 'id' => SlackInboundEvent::first()?->id,
                'config' => ['app.key' => config('app.key'), 'database.default' => 'testing',
                    'database.connections.testing' => Escalated::db()->getConfig(),
                    'escalated.connection' => 'testing', 'escalated.user_model' => config('escalated.user_model'),
                    'escalated.slack' => config('escalated.slack'), 'escalated.tenancy.enabled' => false,
                    'escalated.plugins.enabled' => false, 'escalated.plugins.sdk_enabled' => false,
                    'escalated.routes.enabled' => false, 'escalated.ui.enabled' => false,
                    'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']];
            file_put_contents($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR));
            foreach ([1, 2] as $number) {
                $workers[$number] = new Process([PHP_BINARY, __DIR__.'/../Fixtures/slack-concurrent-worker.php', $fixturePath, (string) $number], null, [
                    'APP_SERVICES_CACHE' => $fixturePath.'.services'.$number,
                    'APP_PACKAGES_CACHE' => $fixturePath.'.packages'.$number,
                ]);
                $workers[$number]->setTimeout(20)->start();
            }
            $deadline = microtime(true) + 10;
            while (! is_file($fixturePath.'.ready1') || ! is_file($fixturePath.'.ready2')) {
                foreach ($workers as $worker) {
                    if (! $worker->isRunning()) {
                        throw new RuntimeException('Worker failed to bootstrap: '.$worker->getErrorOutput().$worker->getOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Workers did not reach the shared barrier.');
                }
                usleep(10000);
            }
            file_put_contents($fixturePath.'.go', 'go');
            foreach ($workers as $worker) {
                $worker->wait();
                expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput().$worker->getOutput());
                try {
                    $result = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $error) {
                    throw new RuntimeException('Unexpected worker output: '.$worker->getOutput(), previous: $error);
                }
                match ($mode) {
                    'contact' => expect($result)->toBe(Contact::where('email', 'concurrent@example.test')->sole()->id),
                    'receive' => expect($result['accepted'])->toBeTrue(),
                    'process' => expect($result)->toBe('processed'),
                };
            }
            foreach (['.ready1', '.ready2', '.go', '.insert-ready1', '.insert-ready2'] as $suffix) {
                if (is_file($fixturePath.$suffix)) {
                    unlink($fixturePath.$suffix);
                }
            }
        }
        expect(SlackInboundEvent::count())->toBe(1)->and(Ticket::count())->toBe(1)->and(SlackThread::count())->toBe(1);
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        // Every path is the explicit file returned by tempnam or its fixed barrier suffix.
        foreach (['', '.ready1', '.ready2', '.go', '.services1', '.services2', '.packages1', '.packages2', '.insert-ready1', '.insert-ready2'] as $suffix) {
            if (is_file($fixturePath.$suffix)) {
                unlink($fixturePath.$suffix);
            }
        }
    }
});

<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\SlackInboundEvent;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\SlackInboxProcessor;
use Escalated\Laravel\Tests\Fixtures\ExercisesSlackInbox;
use Illuminate\Support\Facades\Storage;

uses(ExercisesSlackInbox::class);
beforeEach(fn () => $this->prepareSlackInbox());
afterEach(fn () => $this->cleanSlackInbox());

it('resolves tenant only from verified channel configuration on a separate database', function () {
    $context = $this->enableSlackTenancy();
    $this->slackRequest(headers: ['HTTP_X_TENANT_ID' => 'b'])->assertStatus(202);
    expect($context->current())->toBeNull();
    $context->run('a', function () {
        expect(SlackInboundEvent::count())->toBe(1);
        expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id))->toBe('processed');
        expect(Ticket::sole()->requester->email)->toBe('recipient@example.test');
    });
    $context->run('b', fn () => expect(SlackInboundEvent::count())->toBe(0)->and(Ticket::count())->toBe(0));
    expect(Escalated::schema()->hasTable('users'))->toBeFalse()->and($context->current())->toBeNull();
});

it('rejects routing changes on redelivery and queued processing without exposing another merchant', function () {
    $context = $this->enableSlackTenancy();
    $this->slackRequest()->assertStatus(202);
    config(['escalated.slack.apps.default.channels.T123.C123.tenant_id' => 'b']);
    $this->slackRequest()->assertStatus(409);
    $context->run('a', function () {
        expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id))->toBe('pending')
            ->and(Ticket::count())->toBe(0);
    });
    $context->run('b', fn () => expect(SlackInboundEvent::count())->toBe(0));
});

it('does not create for a host requester outside the mapped merchant', function () {
    $context = $this->enableSlackTenancy();
    $foreign = $this->createTestUser(['email' => 'foreign@example.test']);
    config(['escalated.slack.apps.default.users.T123.U123.id' => $foreign->id]);
    $this->slackRequest()->assertStatus(202);
    $context->run('a', function () {
        expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id))->toBe('pending')
            ->and(Ticket::count())->toBe(0);
    });
});

it('keeps the full text attachment of an oversized message inside the mapped merchant', function () {
    Storage::fake('local');
    $context = $this->enableSlackTenancy();
    $text = str_repeat('📦', 40000);
    $this->slackRequest($this->slackPayload(['event' => ['text' => $text]]))->assertStatus(202);
    $context->run('a', function () use ($text) {
        expect(app(SlackInboxProcessor::class)->process(SlackInboundEvent::sole()->id))->toBe('processed');
        $attachment = Attachment::sole();
        expect($attachment->attachable->is(Ticket::sole()))->toBeTrue()
            ->and(Storage::disk('local')->get($attachment->path))->toBe($text)
            ->and(Escalated::db()->table(Escalated::table('attachments'))->value('tenant_id'))->toBe('a');
    });
    $context->run('b', fn () => expect(Attachment::count())->toBe(0));
});

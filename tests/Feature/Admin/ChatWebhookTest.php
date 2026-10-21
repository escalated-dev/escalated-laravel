<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Webhook;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

// A public address written as a literal, so these tests never depend on DNS.
const CHAT_WEBHOOK_URL = 'https://93.184.215.14/hooks/chat';

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    EscalatedSettings::set('chat_enabled', '1');
    Http::fake();

    Webhook::create([
        'url' => CHAT_WEBHOOK_URL,
        'events' => ['chat.started', 'chat.assigned', 'chat.transferred', 'chat.message', 'chat.ended'],
        'active' => true,
    ]);
});

/**
 * The chat webhook requests sent, in order, as [event, payload, raw body].
 *
 * @return list<array{0: string, 1: array, 2: string}>
 */
function sentChatWebhooks(): array
{
    return collect(Http::recorded())
        ->map(fn ($pair) => $pair[0])
        ->filter(fn (HttpRequest $request) => $request->url() === CHAT_WEBHOOK_URL)
        ->map(fn (HttpRequest $request) => [$request['event'], $request['payload'], $request->body()])
        ->values()
        ->all();
}

/**
 * The single delivery of one event.
 *
 * @return array{0: string, 1: array, 2: string}
 */
function sentChatWebhook(string $event): array
{
    $sent = array_values(array_filter(sentChatWebhooks(), fn ($s) => $s[0] === $event));
    expect($sent)->toHaveCount(1);

    return $sent[0];
}

function startWidgetChat($test): array
{
    $start = $test->postJson('/support/widget/chat/start', [
        ...(fn () => $this->guestProof('visitor@example.com', 'chat'))->call($test),
        'name' => 'Visitor',
        'email' => 'visitor@example.com',
        'subject' => 'Need help',
        'message' => 'Hello',
    ])->assertCreated();

    $token = $start->json('session_id');
    $session = ChatSession::where('customer_session_id', hash('sha256', $token))->sole();

    return [$token, $session];
}

it('offers the chat events in the webhook form', function () {
    $props = $this->actingAs($this->createAdmin())
        ->get(route('escalated.admin.webhooks.create'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['availableEvents'])->toContain(
        'chat.started', 'chat.assigned', 'chat.transferred', 'chat.message', 'chat.ended'
    );
});

it('sends chat.started when a visitor starts a chat, without the token or the email', function () {
    [$token, $session] = startWidgetChat($this);

    [, $payload, $body] = sentChatWebhook('chat.started');

    expect($payload['chat'])->toMatchArray([
        'session_id' => $session->id,
        'ticket_id' => $session->ticket_id,
        'status' => 'waiting',
        'agent_id' => null,
    ])
        ->and($payload['chat'])->toHaveKey('department_id')
        ->and($body)->not->toContain('visitor@example.com')
        ->and($body)->not->toContain($token)
        ->and($body)->not->toContain(hash('sha256', $token));
});

it('sends chat.assigned when an agent accepts a chat', function () {
    [$token, $session] = startWidgetChat($this);
    $admin = $this->createAdmin();

    $this->actingAs($admin)
        ->postJson("/support/admin/chat/{$session->id}/accept")
        ->assertOk();

    [, $payload, $body] = sentChatWebhook('chat.assigned');

    expect($payload['chat'])->toMatchArray([
        'session_id' => $session->id,
        'status' => 'active',
        'agent_id' => $admin->id,
    ])
        ->and($body)->not->toContain('visitor@example.com')
        ->and($body)->not->toContain(hash('sha256', $token));
});

it('sends chat.transferred when a chat moves to another agent', function () {
    [$token, $session] = startWidgetChat($this);
    $admin = $this->createAdmin();
    $other = $this->createAgent();
    $this->actingAs($admin)->postJson("/support/admin/chat/{$session->id}/accept")->assertOk();

    $this->actingAs($admin)
        ->postJson("/support/admin/chat/{$session->id}/transfer", ['agent_id' => $other->id])
        ->assertOk();

    [, $payload, $body] = sentChatWebhook('chat.transferred');

    expect($payload['chat'])->toMatchArray([
        'session_id' => $session->id,
        'agent_id' => $other->id,
        'from_agent_id' => $admin->id,
        'to_agent_id' => $other->id,
        'to_department_id' => null,
    ])
        ->and($body)->not->toContain('visitor@example.com')
        ->and($body)->not->toContain(hash('sha256', $token));
});

it('sends chat.message for a visitor message, without the message body', function () {
    [$token, $session] = startWidgetChat($this);

    $reply = $this->postJson("/support/widget/chat/{$token}/message", ['body' => 'My secret order 4242'])
        ->assertOk();

    [, $payload, $body] = sentChatWebhook('chat.message');

    expect($payload['chat'])->toMatchArray([
        'session_id' => $session->id,
        'reply_id' => $reply->json('reply_id'),
        'author_type' => 'visitor',
    ])
        ->and($body)->not->toContain('My secret order 4242')
        ->and($body)->not->toContain('visitor@example.com')
        ->and($body)->not->toContain($token);
});

it('sends chat.message for an agent message', function () {
    [, $session] = startWidgetChat($this);
    $admin = $this->createAdmin();
    $this->actingAs($admin)->postJson("/support/admin/chat/{$session->id}/accept")->assertOk();

    $this->actingAs($admin)
        ->postJson("/support/admin/chat/{$session->id}/message", ['body' => 'How can I help?'])
        ->assertOk();

    [, $payload] = sentChatWebhook('chat.message');

    expect($payload['chat']['author_type'])->toBe('agent');
});

it('sends chat.ended when the visitor ends the chat', function () {
    [$token, $session] = startWidgetChat($this);

    $this->postJson("/support/widget/chat/{$token}/end")->assertOk();

    [, $payload, $body] = sentChatWebhook('chat.ended');

    expect($payload['chat'])->toMatchArray([
        'session_id' => $session->id,
        'status' => ChatSessionStatus::Ended->value,
        'ended_by' => 'customer',
    ])
        ->and($body)->not->toContain('visitor@example.com')
        ->and($body)->not->toContain($token);
});

it('reads a legacy chat subscription as its canonical name', function () {
    Webhook::query()->delete();
    Webhook::create([
        'url' => CHAT_WEBHOOK_URL,
        'events' => ['chat.session_started', 'chat.session_ended'],
        'active' => true,
    ]);

    [$token] = startWidgetChat($this);
    $this->postJson("/support/widget/chat/{$token}/end")->assertOk();

    expect(array_column(sentChatWebhooks(), 0))->toBe(['chat.started', 'chat.ended']);
});

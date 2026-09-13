<?php

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Webhook;
use Escalated\Laravel\Models\WebhookDelivery;
use Escalated\Laravel\Services\WebhookDispatcher;
use Escalated\Laravel\Support\OutboundUrlGuard;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

// A public address written as a literal, so these tests never depend on DNS.
const WEBHOOK_TEST_PUBLIC_URL = 'https://93.184.215.14/hooks/escalated';

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

/**
 * @param  list<string>  $addresses
 */
function resolveWebhookHostsTo(array $addresses): void
{
    app()->instance(OutboundUrlGuard::class, new class($addresses) extends OutboundUrlGuard
    {
        public function __construct(private array $addresses) {}

        public function resolve(string $host): array
        {
            return $this->addresses;
        }
    });
}

it('delivers ticket.created to an active webhook subscribed to it', function () {
    Http::fake();
    Webhook::create([
        'url' => WEBHOOK_TEST_PUBLIC_URL,
        'events' => ['ticket.created'],
        'secret' => 'shared-secret',
        'active' => true,
    ]);

    $ticket = Ticket::factory()->create();

    Http::assertSent(fn (HttpRequest $request) => $request->url() === WEBHOOK_TEST_PUBLIC_URL
        && $request['event'] === 'ticket.created'
        && $request['payload']['ticket']['id'] === $ticket->id
        && $request->header('X-Escalated-Signature')[0] === hash_hmac('sha256', $request->body(), 'shared-secret'));

    $delivery = WebhookDelivery::sole();
    expect($delivery->event)->toBe('ticket.created')
        ->and($delivery->response_code)->toBe(200);
});

it('does not deliver an event the webhook is not subscribed to', function () {
    Http::fake();
    Webhook::create([
        'url' => WEBHOOK_TEST_PUBLIC_URL,
        'events' => ['reply.created'],
        'active' => true,
    ]);

    Ticket::factory()->create();

    Http::assertNothingSent();
    expect(WebhookDelivery::count())->toBe(0);
});

it('does not deliver to an inactive webhook', function () {
    Http::fake();
    Webhook::create([
        'url' => WEBHOOK_TEST_PUBLIC_URL,
        'events' => ['ticket.created'],
        'active' => false,
    ]);

    Ticket::factory()->create();

    Http::assertNothingSent();
});

it('still posts to the webhook url configured in the environment', function () {
    Http::fake();
    config()->set('escalated.notifications.webhook_url', 'https://hooks.example.test/escalated');

    Ticket::factory()->create();

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://hooks.example.test/escalated'
        && $request['event'] === 'ticket.created');
});

it('refuses to store a webhook url on a private or loopback address', function (string $url) {
    $this->actingAs($this->createAdmin())
        ->postJson('/support/admin/webhooks', [
            'url' => $url,
            'events' => ['ticket.created'],
            'active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('url');

    expect(Webhook::count())->toBe(0);
})->with([
    'loopback' => 'http://127.0.0.1/',
    'private' => 'http://10.0.0.5/hook',
    'link-local metadata' => 'http://169.254.169.254/latest/meta-data',
    'localhost' => 'http://localhost/hook',
    'ipv6 loopback' => 'http://[::1]/hook',
]);

it('refuses to update a webhook to a private address', function () {
    $webhook = Webhook::create([
        'url' => WEBHOOK_TEST_PUBLIC_URL,
        'events' => ['ticket.created'],
        'active' => true,
    ]);

    $this->actingAs($this->createAdmin())
        ->putJson("/support/admin/webhooks/{$webhook->id}", [
            'url' => 'http://127.0.0.1/',
            'events' => ['ticket.created'],
            'active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('url');

    expect($webhook->fresh()->url)->toBe(WEBHOOK_TEST_PUBLIC_URL);
});

it('stores a webhook url on a public address', function () {
    $this->actingAs($this->createAdmin())
        ->postJson('/support/admin/webhooks', [
            'url' => WEBHOOK_TEST_PUBLIC_URL,
            'events' => ['ticket.created'],
            'active' => true,
        ])
        ->assertRedirect();

    expect(Webhook::sole()->url)->toBe(WEBHOOK_TEST_PUBLIC_URL);
});

it('sends nothing when a stored webhook points at a private address', function () {
    Http::fake();
    // Written straight to the table, as a row saved before validation
    // existed would be.
    Webhook::create([
        'url' => 'http://127.0.0.1/hook',
        'events' => ['ticket.created'],
        'active' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch('ticket.created', ['ticket' => ['id' => 1]]);

    Http::assertNothingSent();
    expect(WebhookDelivery::sole()->response_code)->toBe(0);
});

it('sends nothing when a host name resolves to a private address', function () {
    Http::fake();
    resolveWebhookHostsTo(['10.0.0.5']);
    Webhook::create([
        'url' => 'https://hooks.example.com/escalated',
        'events' => ['ticket.created'],
        'active' => true,
    ]);

    Ticket::factory()->create();

    Http::assertNothingSent();
});

it('delivers to a host name that resolves to a public address', function () {
    Http::fake();
    resolveWebhookHostsTo(['93.184.215.14']);
    Webhook::create([
        'url' => 'https://hooks.example.com/escalated',
        'events' => ['ticket.created'],
        'active' => true,
    ]);

    Ticket::factory()->create();

    Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://hooks.example.com/escalated');
});

it('refuses a host name when any of its addresses is private', function () {
    resolveWebhookHostsTo(['93.184.215.14', '10.0.0.5']);

    $this->actingAs($this->createAdmin())
        ->postJson('/support/admin/webhooks', [
            'url' => 'https://hooks.example.com/escalated',
            'events' => ['ticket.created'],
            'active' => true,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('url');
});

it('delivers to a webhook saved under an event name the admin form used to offer', function (string $legacy, string $dispatched) {
    Http::fake();
    Webhook::create([
        'url' => WEBHOOK_TEST_PUBLIC_URL,
        'events' => [$legacy],
        'active' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch($dispatched, []);

    Http::assertSent(fn (HttpRequest $request) => $request['event'] === $dispatched);
})->with([
    ['internal_note.added', 'note.created'],
    ['tag.added', 'ticket.tag_added'],
    ['tag.removed', 'ticket.tag_removed'],
]);

it('sends nothing when an admin retries a delivery to a private address', function () {
    Http::fake();
    $webhook = Webhook::create([
        'url' => 'http://169.254.169.254/latest/meta-data',
        'events' => ['ticket.created'],
        'active' => true,
    ]);
    $delivery = WebhookDelivery::create([
        'webhook_id' => $webhook->id,
        'event' => 'ticket.created',
        'payload' => ['ticket' => ['id' => 1]],
        'attempts' => 1,
    ]);

    $this->actingAs($this->createAdmin())
        ->post("/support/admin/webhooks/deliveries/{$delivery->id}/retry")
        ->assertRedirect();

    Http::assertNothingSent();
});

it('does not follow a redirect from a webhook endpoint', function () {
    Http::fake([
        '93.184.215.14/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/internal']),
        '*' => Http::response('internal secret', 200),
    ]);
    $webhook = Webhook::create([
        'url' => WEBHOOK_TEST_PUBLIC_URL,
        'events' => ['ticket.created'],
        'active' => true,
    ]);

    app(WebhookDispatcher::class)->send($webhook, 'ticket.created', ['ticket' => ['id' => 1]], 3);

    Http::assertSentCount(1);
    expect(WebhookDelivery::sole()->response_body)->not->toContain('internal secret');
});

<?php

use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Events\ChatAssigned;
use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Http\Controllers\WidgetScriptController;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Escalated\Laravel\Http\Middleware\WidgetCsrf;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/** The host's CSRF middleware as it behaves outside the test runner. */
class WidgetEmbeddingStrictCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}

const WIDGET_EMBED_SHOP = 'https://shop.example.com';

beforeEach(function () {
    // The host's CSRF middleware enforced as it is outside tests, whichever
    // class this Laravel version puts in the web group.
    foreach ([...WidgetCsrf::CSRF_MIDDLEWARE, ValidateCsrfToken::class] as $csrf) {
        $this->app->bind($csrf, WidgetEmbeddingStrictCsrf::class);
    }
    config(['escalated.widget.allowed_origins' => [WIDGET_EMBED_SHOP]]);
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');
});

function widgetTicketPayload(array $proof, string $email = 'jane@example.com'): array
{
    return [
        ...$proof,
        'name' => 'Jane Doe',
        'email' => $email,
        'subject' => 'Where is my order?',
        'description' => 'It has not arrived yet.',
    ];
}

it('answers a preflight from an allowed site', function () {
    $response = $this->call('OPTIONS', route('escalated.widget.tickets.store'), server: [
        'HTTP_ORIGIN' => WIDGET_EMBED_SHOP,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,authorization',
    ]);

    $response->assertNoContent();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe(WIDGET_EMBED_SHOP)
        ->and($response->headers->get('Access-Control-Allow-Methods'))->toContain('POST')
        ->and($response->headers->get('Access-Control-Allow-Headers'))->toContain('Content-Type', 'Authorization')
        ->and($response->headers->get('Access-Control-Max-Age'))->toBe('600')
        ->and($response->headers->get('Vary'))->toContain('Origin');
});

it('gives no CORS grant to a site that is not allowed', function () {
    $preflight = $this->call('OPTIONS', route('escalated.widget.tickets.store'), server: [
        'HTTP_ORIGIN' => 'https://evil.example.net',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    $config = $this->getJson(route('escalated.widget.config'), ['Origin' => 'https://evil.example.net']);

    expect($preflight->headers->has('Access-Control-Allow-Origin'))->toBeFalse()
        ->and($config->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    $config->assertOk();
});

it('lets an allowed site create a ticket without a CSRF token', function () {
    $response = $this->postJson(route('escalated.widget.tickets.store'), widgetTicketPayload($this->guestProof('jane@example.com')), ['Origin' => WIDGET_EMBED_SHOP]);

    $response->assertCreated();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe(WIDGET_EMBED_SHOP);
    expect(Ticket::where('subject', 'Where is my order?')->exists())->toBeTrue();
});

it('still requires a CSRF token from other sites and from the same site', function (?string $origin) {
    $headers = $origin === null ? [] : ['Origin' => $origin];

    $response = $this->postJson(route('escalated.widget.tickets.store'), widgetTicketPayload($this->guestProof('jane@example.com')), $headers);

    $response->assertStatus(419);
    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    expect(Ticket::count())->toBe(0);
})->with([
    'not allowed' => ['https://evil.example.net'],
    'same origin' => ['http://localhost'],
    'no Origin header' => [null],
]);

it('reads allowed sites from the admin setting, including subdomain wildcards', function () {
    config(['escalated.widget.allowed_origins' => []]);
    EscalatedSettings::set('widget_allowed_origins', "https://*.brand.test\nhttp://localhost:5173");

    $allowed = fn (string $origin) => $this->getJson(route('escalated.widget.config'), ['Origin' => $origin])
        ->headers->get('Access-Control-Allow-Origin');

    expect($allowed('https://help.brand.test'))->toBe('https://help.brand.test')
        ->and($allowed('https://a.b.brand.test'))->toBe('https://a.b.brand.test')
        ->and($allowed('http://localhost:5173'))->toBe('http://localhost:5173')
        ->and($allowed('https://brand.test'))->toBeNull()
        ->and($allowed('http://help.brand.test'))->toBeNull()
        ->and($allowed('https://evilbrand.test'))->toBeNull()
        ->and($allowed('http://localhost:5174'))->toBeNull();
});

it('treats an embedded visitor as a guest even when signed in to Escalated', function () {
    EscalatedSettings::set('knowledge_base_enabled', '1');
    EscalatedSettings::set('knowledge_base_public', '0');
    Article::create(['title' => 'Returns', 'slug' => 'returns', 'body' => 'Send it back.', 'status' => 'published']);
    $this->actingAs($this->createAgent());

    $this->getJson(route('escalated.widget.articles.show', 'returns'))->assertOk();
    $this->getJson(route('escalated.widget.articles.show', 'returns'), ['Origin' => WIDGET_EMBED_SHOP])
        ->assertForbidden()
        ->assertHeader('Access-Control-Allow-Origin', WIDGET_EMBED_SHOP);
});

it('adds CORS headers to validation answers for allowed sites', function () {
    $this->postJson(route('escalated.widget.tickets.store'), ['name' => 'x'], ['Origin' => WIDGET_EMBED_SHOP])
        ->assertUnprocessable()
        ->assertHeader('Access-Control-Allow-Origin', WIDGET_EMBED_SHOP);
});

it('adds CORS headers to rate-limit answers for allowed sites', function () {
    config(['escalated.guest_rate_limits.requests_per_minute' => 1]);

    $this->getJson(route('escalated.widget.config'), ['Origin' => WIDGET_EMBED_SHOP])->assertOk();
    $limited = $this->getJson(route('escalated.widget.config'), ['Origin' => WIDGET_EMBED_SHOP]);

    $limited->assertTooManyRequests()->assertHeader('Access-Control-Allow-Origin', WIDGET_EMBED_SHOP);
    expect($limited->headers->get('Access-Control-Expose-Headers'))->toContain('Retry-After');
});

it('stores host parameters sent with a widget ticket', function () {
    $response = $this->postJson(route('escalated.widget.tickets.store'), [
        ...widgetTicketPayload($this->guestProof('jane@example.com')),
        'metadata' => [
            'order_number' => 'A-1001',
            'plan' => 'pro',
            'items' => 3,
            'page_url' => 'https://shop.example.com/orders/A-1001',
            'embedded_on' => 'https://spoofed.example',
            'nested' => ['not' => 'kept'],
            'bad key!' => 'dropped',
            'note' => str_repeat('a', 600),
        ],
    ], ['Origin' => WIDGET_EMBED_SHOP]);

    $response->assertCreated();
    $metadata = Ticket::where('reference', $response->json('reference'))->value('metadata');
    $metadata = is_string($metadata) ? json_decode($metadata, true) : $metadata;

    expect($metadata)->toMatchArray([
        'order_number' => 'A-1001',
        'plan' => 'pro',
        'items' => 3,
        'page_url' => 'https://shop.example.com/orders/A-1001',
        'embedded_on' => WIDGET_EMBED_SHOP,
    ])->and($metadata)->not->toHaveKeys(['nested', 'bad key!'])
        ->and(mb_strlen($metadata['note']))->toBe(500);
});

it('drops a page_url that is not a web address', function () {
    $response = $this->postJson(route('escalated.widget.tickets.store'), [
        ...widgetTicketPayload($this->guestProof('jane@example.com')),
        'metadata' => ['page_url' => 'javascript:alert(1)', 'plan' => 'pro'],
    ], ['Origin' => WIDGET_EMBED_SHOP]);

    $ticket = Ticket::where('reference', $response->json('reference'))->firstOrFail();
    expect($ticket->metadata)->toBe(['plan' => 'pro', 'embedded_on' => WIDGET_EMBED_SHOP]);
});

it('leaves widget tickets without host parameters unchanged', function () {
    foreach ([...WidgetCsrf::CSRF_MIDDLEWARE, ValidateCsrfToken::class] as $csrf) {
        $this->app->offsetUnset($csrf);
    }

    $response = $this->postJson(route('escalated.widget.tickets.store'), widgetTicketPayload($this->guestProof('jane@example.com')));

    $response->assertCreated();
    expect(Ticket::where('reference', $response->json('reference'))->value('metadata'))->toBeNull();
});

it('lets an allowed site start a live chat and keeps its host parameters', function () {
    Event::fake([ChatStarted::class, ChatAssigned::class]);
    EscalatedSettings::set('chat_enabled', '1');
    $agent = $this->createAgent();
    AgentProfile::forUser($agent->id)->update(['chat_status' => ChatStatus::Online]);

    $response = $this->postJson(route('escalated.widget.chat.start'), [
        ...$this->guestProof('john@example.com', 'chat'),
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'message' => 'Hello',
        'metadata' => ['page_url' => 'https://shop.example.com/cart', 'cart_total' => 129.5, 'deep' => ['x' => 1]],
    ], ['Origin' => WIDGET_EMBED_SHOP]);

    $response->assertCreated()->assertHeader('Access-Control-Allow-Origin', WIDGET_EMBED_SHOP);
    $ticket = Ticket::where('reference', $response->json('ticket_reference'))->firstOrFail();
    expect($ticket->chat_metadata)->toBe([
        'page_url' => 'https://shop.example.com/cart',
        'cart_total' => 129.5,
        'embedded_on' => WIDGET_EMBED_SHOP,
    ]);
});

it('serves the standalone widget script for other sites to load', function () {
    $response = $this->get(route('escalated.widget.script'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript')
        ->and($response->headers->get('Cross-Origin-Resource-Policy'))->toBe('cross-origin')
        ->and($response->getContent())->toBe(file_get_contents(WidgetScriptController::BUNDLE));

    $this->get(route('escalated.widget.script'), ['If-None-Match' => $response->headers->get('ETag')])
        ->assertStatus(304);
});

it('lets admins turn the widget on and list embedding sites', function () {
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    EscalatedSettings::set('widget_enabled', '0');
    $required = [
        'guest_tickets_enabled' => true, 'allow_customer_close' => true,
        'auto_close_resolved_after_days' => 7, 'max_attachments_per_reply' => 5,
        'max_attachment_size_kb' => 10240, 'ticket_reference_prefix' => 'ESC',
    ];
    $this->actingAs($this->createAdmin());
    foreach ([...WidgetCsrf::CSRF_MIDDLEWARE, ValidateCsrfToken::class] as $csrf) {
        $this->app->offsetUnset($csrf);
    }

    $this->post(route('escalated.admin.settings.update'), [
        ...$required,
        'widget_enabled' => true,
        'widget_color' => '#112233',
        'widget_allowed_origins' => "https://shop.example.com\nhttps://*.brand.test",
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(EscalatedSettings::getBool('widget_enabled'))->toBeTrue()
        ->and(EscalatedSettings::get('widget_color'))->toBe('#112233')
        ->and(EscalatedSettings::get('widget_allowed_origins'))->toBe("https://shop.example.com\nhttps://*.brand.test");

    $this->post(route('escalated.admin.settings.update'), [
        ...$required,
        'widget_allowed_origins' => "https://shop.example.com/checkout\n*",
    ])->assertSessionHasErrors('widget_allowed_origins');
    expect(EscalatedSettings::get('widget_allowed_origins'))->toBe("https://shop.example.com\nhttps://*.brand.test");
});

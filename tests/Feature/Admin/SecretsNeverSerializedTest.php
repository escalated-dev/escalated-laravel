<?php

/*
 * Stored secrets never reach the browser.
 *
 * Import source credentials, webhook signing secrets and SSO secrets are kept
 * server-side so the app can use them. None of them is shown back to an admin:
 * every page and JSON response that carries one of these records is checked
 * here for the raw secret anywhere in the body.
 */

use Escalated\Laravel\Contracts\ImportAdapter;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\ImportJob;
use Escalated\Laravel\Models\TwoFactor;
use Escalated\Laravel\Models\Webhook;
use Escalated\Laravel\Models\WebhookDelivery;
use Escalated\Laravel\Support\ExtractResult;
use Escalated\Laravel\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;

const IMPORT_API_KEY = 'imp-key-7f3a9c2e5b1d';
const IMPORT_PASSWORD = 'imp-pass-q8w7e6r5t4';
const WEBHOOK_SECRET = 'whsec-4c8e1a7b9d3f';

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);

    $this->admin = $this->createAdmin();

    // Webhook saves check the URL resolves publicly; keep that off the network.
    app()->instance(OutboundUrlGuard::class, new class extends OutboundUrlGuard
    {
        public function __construct() {}

        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    escalated_add_filter('import.adapters', fn (array $adapters) => [...$adapters, new FakeLeakTestAdapter]);
});

function leakTestInertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => ''];
}

function assertHasNoSecrets(TestResponse $response, array $secrets): void
{
    $body = $response->getContent();

    foreach ($secrets as $secret) {
        expect($body)->not->toContain($secret);
    }
}

function importJobWithCredentials(mixed $userId): ImportJob
{
    return ImportJob::create([
        'user_id' => $userId,
        'platform' => 'fakedesk',
        'status' => 'importing',
        'credentials' => ['api_key' => IMPORT_API_KEY, 'password' => IMPORT_PASSWORD],
        'progress' => ['tickets' => ['total' => 10, 'processed' => 4, 'skipped' => 0, 'failed' => 0, 'cursor' => null]],
        'error_log' => [],
    ]);
}

// --- Import ------------------------------------------------------------------

it('keeps import credentials out of the import index page', function () {
    $job = importJobWithCredentials($this->admin->getKey());

    $response = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.import.index'))
        ->assertOk();

    assertHasNoSecrets($response, [IMPORT_API_KEY, IMPORT_PASSWORD]);

    $jobs = $response->json('props.jobs');
    expect($jobs)->toHaveCount(1)
        ->and($jobs[0]['id'])->toBe($job->id)
        ->and($jobs[0]['platform'])->toBe('fakedesk')
        ->and($jobs[0]['status'])->toBe('importing')
        ->and($jobs[0])->toHaveKey('created_at')
        ->and($jobs[0])->not->toHaveKey('credentials');
});

it('keeps import credentials out of the import progress page', function () {
    $job = importJobWithCredentials($this->admin->getKey());

    $response = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.import.progress', $job->id))
        ->assertOk();

    assertHasNoSecrets($response, [IMPORT_API_KEY, IMPORT_PASSWORD]);

    $props = $response->json('props.job');
    expect($props['id'])->toBe($job->id)
        ->and($props['status'])->toBe('importing')
        ->and($props['platform'])->toBe('fakedesk')
        ->and($props['progress']['tickets']['processed'])->toBe(4)
        ->and($props)->toHaveKey('error_log')
        ->and($props)->not->toHaveKey('credentials');
});

it('keeps import credentials out of the import status json', function () {
    $job = importJobWithCredentials($this->admin->getKey());

    $response = $this->actingAs($this->admin)
        ->getJson(route('escalated.admin.import.status', $job->id))
        ->assertOk()
        ->assertJsonPath('status', 'importing');

    assertHasNoSecrets($response, [IMPORT_API_KEY, IMPORT_PASSWORD]);
});

it('keeps import credentials out of the connect, test-connection, mapping and review steps', function () {
    $credentials = ['api_key' => IMPORT_API_KEY, 'password' => IMPORT_PASSWORD];

    $connect = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.import.connect', 'fakedesk'))
        ->assertOk();
    assertHasNoSecrets($connect, [IMPORT_API_KEY, IMPORT_PASSWORD]);

    $test = $this->actingAs($this->admin)
        ->postJson(route('escalated.admin.import.test-connection', 'fakedesk'), ['credentials' => $credentials])
        ->assertOk()
        ->assertJsonPath('success', true);
    assertHasNoSecrets($test, [IMPORT_API_KEY, IMPORT_PASSWORD]);

    $mapping = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.import.mapping', 'fakedesk'))
        ->assertOk();
    assertHasNoSecrets($mapping, [IMPORT_API_KEY, IMPORT_PASSWORD]);

    $review = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->post(route('escalated.admin.import.review', 'fakedesk'), ['entity_types' => ['tickets']])
        ->assertOk();
    assertHasNoSecrets($review, [IMPORT_API_KEY, IMPORT_PASSWORD]);
});

it('still hands the stored credentials to the import run server-side', function () {
    $job = importJobWithCredentials($this->admin->getKey());

    expect(ImportJob::find($job->id)->credentials)
        ->toBe(['api_key' => IMPORT_API_KEY, 'password' => IMPORT_PASSWORD])
        ->and(ImportJob::find($job->id)->toArray())->not->toHaveKey('credentials')
        ->and(ImportJob::find($job->id)->toJson())->not->toContain(IMPORT_API_KEY);
});

// --- Webhooks ----------------------------------------------------------------

function webhookWithSecret(): Webhook
{
    return Webhook::create([
        'url' => 'https://hooks.example.com/escalated',
        'events' => ['ticket.created'],
        'secret' => WEBHOOK_SECRET,
        'active' => true,
    ]);
}

it('keeps the webhook secret out of the webhook index, edit and delivery-log pages', function () {
    $webhook = webhookWithSecret();
    WebhookDelivery::create([
        'webhook_id' => $webhook->id,
        'event' => 'ticket.created',
        'payload' => ['ok' => true],
        'response_code' => 200,
        'attempts' => 1,
    ]);

    $index = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.webhooks.index'))
        ->assertOk();
    assertHasNoSecrets($index, [WEBHOOK_SECRET]);
    expect($index->json('props.webhooks.0.has_secret'))->toBeTrue();

    $edit = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.webhooks.edit', $webhook))
        ->assertOk();
    assertHasNoSecrets($edit, [WEBHOOK_SECRET]);
    expect($edit->json('props.webhook.has_secret'))->toBeTrue()
        ->and($edit->json('props.webhook.url'))->toBe('https://hooks.example.com/escalated');

    $log = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.webhooks.deliveries', $webhook))
        ->assertOk();
    assertHasNoSecrets($log, [WEBHOOK_SECRET]);
});

it('keeps the stored webhook secret when the form is saved with the secret left blank', function () {
    $webhook = webhookWithSecret();

    $this->actingAs($this->admin)
        ->put(route('escalated.admin.webhooks.update', $webhook), [
            'url' => 'https://hooks.example.com/escalated',
            'events' => ['ticket.created', 'ticket.updated'],
            'secret' => '',
            'active' => true,
        ])
        ->assertRedirect();

    expect($webhook->fresh()->secret)->toBe(WEBHOOK_SECRET)
        ->and($webhook->fresh()->events)->toBe(['ticket.created', 'ticket.updated']);
});

it('replaces the webhook secret when a new one is entered, and clears it on request', function () {
    $webhook = webhookWithSecret();
    $payload = ['url' => 'https://hooks.example.com/escalated', 'events' => ['ticket.created'], 'active' => true];

    $this->actingAs($this->admin)
        ->put(route('escalated.admin.webhooks.update', $webhook), $payload + ['secret' => 'whsec-new'])
        ->assertRedirect();
    expect($webhook->fresh()->secret)->toBe('whsec-new');

    $this->actingAs($this->admin)
        ->put(route('escalated.admin.webhooks.update', $webhook), $payload + ['secret' => '', 'clear_secret' => true])
        ->assertRedirect();
    expect($webhook->fresh()->secret)->toBeNull();
});

// --- SSO ---------------------------------------------------------------------

it('keeps the sso jwt secret and oauth client secret out of the sso settings page', function () {
    config(['escalated.access.sso' => true]);
    EscalatedSettings::set('sso_provider', 'oauth');
    EscalatedSettings::set('sso_jwt_secret', 'jwt-secret-5e6f7a8b9c');
    EscalatedSettings::set('sso_oauth_client_secret', 'oauth-secret-1a2b3c4d5e');
    EscalatedSettings::set('sso_oauth_client_id', 'client-id-public');

    $response = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.settings.sso'))
        ->assertOk();

    assertHasNoSecrets($response, ['jwt-secret-5e6f7a8b9c', 'oauth-secret-1a2b3c4d5e', 'secret-']);
    expect($response->json('props.settings.sso_oauth_client_id'))->toBe('client-id-public')
        ->and($response->json('props.settings.sso_oauth_client_secret'))->toMatch('/^\*+$/')
        ->and($response->json('props.settings.sso_jwt_secret'))->toMatch('/^\*+$/');
});

it('keeps the stored sso secrets when the form posts back the masked values', function () {
    config(['escalated.access.sso' => true]);
    EscalatedSettings::set('sso_jwt_secret', 'jwt-secret-5e6f7a8b9c');
    EscalatedSettings::set('sso_oauth_client_secret', 'oauth-secret-1a2b3c4d5e');

    $settings = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.settings.sso'))
        ->json('props.settings');

    $this->actingAs($this->admin)
        ->post(route('escalated.admin.settings.sso.update'), array_merge($settings, [
            'sso_provider' => 'jwt',
            'sso_url' => 'https://id.example.com/jwt',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(EscalatedSettings::get('sso_jwt_secret'))->toBe('jwt-secret-5e6f7a8b9c')
        ->and(EscalatedSettings::get('sso_oauth_client_secret'))->toBe('oauth-secret-1a2b3c4d5e');

    $this->actingAs($this->admin)
        ->post(route('escalated.admin.settings.sso.update'), array_merge($settings, [
            'sso_provider' => 'jwt',
            'sso_url' => 'https://id.example.com/jwt',
            'sso_jwt_secret' => 'jwt-secret-rotated',
        ]))
        ->assertRedirect();

    expect(EscalatedSettings::get('sso_jwt_secret'))->toBe('jwt-secret-rotated');
});

// --- General settings (inbound email secrets) --------------------------------

it('does not reveal any characters of inbound email secrets on the settings page', function () {
    EscalatedSettings::set('mailgun_signing_key', 'mgk-9d8c7b6a5f4e');
    EscalatedSettings::set('imap_password', 'imp-8f7e6d5c4b');

    $response = $this->actingAs($this->admin)->withHeaders(leakTestInertiaHeaders())
        ->get(route('escalated.admin.settings'))
        ->assertOk();

    expect($response->json('props.settings.mailgun_signing_key'))->toMatch('/^\*+$/')
        ->and($response->json('props.settings.imap_password'))->toMatch('/^\*+$/');
    assertHasNoSecrets($response, ['mgk-9d8c7b6a5f4e', 'imp-8f7e6d5c4b']);
});

// --- Models never serialize their secrets ------------------------------------

it('hides secrets on every model that stores one', function () {
    $webhook = webhookWithSecret();
    $job = importJobWithCredentials($this->admin->getKey());
    $twoFactor = TwoFactor::create([
        'user_id' => $this->admin->getKey(),
        'secret' => 'TOTPSECRETBASE32XX',
        'recovery_codes' => ['rc-aaaa-bbbb'],
    ]);
    ['token' => $token] = ApiToken::createToken($this->admin, 'CI');

    expect($webhook->toArray())->not->toHaveKey('secret')
        ->and($job->toArray())->not->toHaveKey('credentials')
        ->and($twoFactor->toArray())->not->toHaveKeys(['secret', 'recovery_codes'])
        ->and($token->toArray())->not->toHaveKey('token');
});

class FakeLeakTestAdapter implements ImportAdapter
{
    public function name(): string
    {
        return 'fakedesk';
    }

    public function displayName(): string
    {
        return 'FakeDesk';
    }

    public function credentialFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'API key', 'type' => 'password', 'help' => ''],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'help' => ''],
        ];
    }

    public function testConnection(array $credentials): bool
    {
        return ($credentials['api_key'] ?? null) === IMPORT_API_KEY;
    }

    public function entityTypes(): array
    {
        return ['tickets'];
    }

    public function defaultFieldMappings(string $entityType): array
    {
        return ['subject' => 'subject'];
    }

    public function availableSourceFields(string $entityType, array $credentials): array
    {
        return ['subject', 'description'];
    }

    public function extract(string $entityType, array $credentials, ?string $cursor): ExtractResult
    {
        return new ExtractResult([], null);
    }
}

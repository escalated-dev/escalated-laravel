<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Phase 2.2f: `escalated:plugin:install <slug>` calls the marketplace
 * manifest endpoint, verifies sha256 (and optional RSA signature),
 * extracts the artifact, and registers the manifest's service
 * provider. All three failure modes (missing manifest, sha256
 * mismatch, signature mismatch) must abort with a non-zero exit.
 */
beforeEach(function (): void {
    $this->workspace = storage_path('app/test-plugins-'.Str::random(6));
    config([
        'escalated.plugins.path' => $this->workspace,
        'escalated.marketplace.url' => 'https://marketplace.test/api/v1',
        'escalated.hosted.api_key' => 'test-token',
    ]);

    File::ensureDirectoryExists($this->workspace);
});

afterEach(function (): void {
    if (isset($this->workspace) && File::exists($this->workspace)) {
        File::deleteDirectory($this->workspace);
    }
});

function makePluginZip(string $slug, string $body = "<?php // hello\n"): array
{
    $tempZip = storage_path('app/test-artifact-'.Str::random(6).'.zip');
    $zip = new ZipArchive;

    if ($zip->open($tempZip, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('cannot create test zip');
    }

    $zip->addFromString('Plugin.php', $body);
    $zip->addFromString('plugin.json', json_encode([
        'name' => $slug,
        'version' => '1.0.0',
        'main_file' => 'Plugin.php',
    ]));
    $zip->close();

    $bytes = file_get_contents($tempZip);

    return [
        'path' => $tempZip,
        'bytes' => $bytes,
        'sha256' => hash('sha256', $bytes),
    ];
}

it('downloads, verifies sha256, extracts, and writes plugin.json', function (): void {
    $artifact = makePluginZip('hello');

    Http::fake([
        'marketplace.test/api/v1/marketplace/publishers/acme/plugins/hello/versions/latest' => Http::response([
            'data' => [
                'artifact_sha256' => $artifact['sha256'],
                'artifact_uri' => 'https://cdn.test/hello-1.0.0.zip',
                'rsa_signature_base64' => null,
                'manifest' => [
                    'slug' => 'hello',
                    'name' => 'Hello',
                    'version' => '1.0.0',
                ],
            ],
        ], 200),
        'cdn.test/*' => Http::response($artifact['bytes'], 200),
    ]);

    $this->artisan('escalated:plugin:install', ['slug' => 'acme/hello'])
        ->assertSuccessful();

    expect(File::exists($this->workspace.'/hello/Plugin.php'))->toBeTrue();
    expect(File::exists($this->workspace.'/hello/plugin.json'))->toBeTrue();

    $manifest = json_decode(File::get($this->workspace.'/hello/plugin.json'), true);
    expect($manifest['_install']['artifact_sha256'])->toBe($artifact['sha256']);

    File::delete($artifact['path']);
});

it('aborts when the published sha256 does not match the downloaded bytes', function (): void {
    $artifact = makePluginZip('hello');

    Http::fake([
        'marketplace.test/api/v1/marketplace/publishers/acme/plugins/hello/versions/latest' => Http::response([
            'data' => [
                'artifact_sha256' => str_repeat('00', 32),
                'artifact_uri' => 'https://cdn.test/hello.zip',
                'rsa_signature_base64' => null,
                'manifest' => ['slug' => 'hello', 'version' => '1.0.0'],
            ],
        ], 200),
        'cdn.test/*' => Http::response($artifact['bytes'], 200),
    ]);

    $this->artisan('escalated:plugin:install', ['slug' => 'acme/hello'])
        ->assertFailed();

    expect(File::exists($this->workspace.'/hello/Plugin.php'))->toBeFalse();

    File::delete($artifact['path']);
});

it('honours a specific --plugin-version when fetching the manifest', function (): void {
    $artifact = makePluginZip('hello');

    Http::fake([
        'marketplace.test/api/v1/marketplace/publishers/acme/plugins/hello/versions/1.2.3' => Http::response([
            'data' => [
                'artifact_sha256' => $artifact['sha256'],
                'artifact_uri' => 'https://cdn.test/hello-1.2.3.zip',
                'manifest' => ['slug' => 'hello', 'version' => '1.2.3'],
            ],
        ], 200),
        'cdn.test/*' => Http::response($artifact['bytes'], 200),
    ]);

    $this->artisan('escalated:plugin:install', [
        'slug' => 'acme/hello',
        '--plugin-version' => '1.2.3',
    ])->assertSuccessful();

    File::delete($artifact['path']);
});

it('aborts when the marketplace returns 404', function (): void {
    Http::fake([
        'marketplace.test/api/v1/*' => Http::response(['error' => 'not found'], 404),
    ]);

    $this->artisan('escalated:plugin:install', ['slug' => 'acme/missing'])
        ->assertFailed();
});

it('rejects re-installing into an existing directory unless --force', function (): void {
    File::makeDirectory($this->workspace.'/hello', 0755, true);
    File::put($this->workspace.'/hello/sentinel.txt', 'existing');

    $artifact = makePluginZip('hello');

    Http::fake([
        'marketplace.test/api/v1/*' => Http::response([
            'data' => [
                'artifact_sha256' => $artifact['sha256'],
                'artifact_uri' => 'https://cdn.test/hello.zip',
                'manifest' => ['slug' => 'hello', 'version' => '1.0.0'],
            ],
        ], 200),
        'cdn.test/*' => Http::response($artifact['bytes'], 200),
    ]);

    $this->artisan('escalated:plugin:install', ['slug' => 'acme/hello'])
        ->assertFailed();

    expect(File::get($this->workspace.'/hello/sentinel.txt'))->toBe('existing');

    $this->artisan('escalated:plugin:install', [
        'slug' => 'acme/hello',
        '--force' => true,
    ])->assertSuccessful();

    expect(File::exists($this->workspace.'/hello/Plugin.php'))->toBeTrue();
    expect(File::exists($this->workspace.'/hello/sentinel.txt'))->toBeFalse();

    File::delete($artifact['path']);
});

it('verifies a valid RSA signature against a configured public key', function (): void {
    $artifact = makePluginZip('hello');

    $privKey = openssl_pkey_get_private('file://'.__DIR__.'/../Fixtures/rsa.priv');
    openssl_sign($artifact['bytes'], $signature, $privKey, OPENSSL_ALGO_SHA256);
    $signatureB64 = base64_encode($signature);

    Http::fake([
        'marketplace.test/api/v1/*' => Http::response([
            'data' => [
                'artifact_sha256' => $artifact['sha256'],
                'artifact_uri' => 'https://cdn.test/hello.zip',
                'rsa_signature_base64' => $signatureB64,
                'manifest' => ['slug' => 'hello', 'version' => '1.0.0'],
            ],
        ], 200),
        'cdn.test/*' => Http::response($artifact['bytes'], 200),
    ]);

    $this->artisan('escalated:plugin:install', [
        'slug' => 'acme/hello',
        '--public-key' => __DIR__.'/../Fixtures/rsa.pub',
    ])->assertSuccessful();

    expect(File::exists($this->workspace.'/hello/Plugin.php'))->toBeTrue();

    File::delete($artifact['path']);
});

it('rejects an artifact whose RSA signature does not match the public key', function (): void {
    $artifact = makePluginZip('hello');
    $bogusSignature = base64_encode(str_repeat('x', 256));

    Http::fake([
        'marketplace.test/api/v1/*' => Http::response([
            'data' => [
                'artifact_sha256' => $artifact['sha256'],
                'artifact_uri' => 'https://cdn.test/hello.zip',
                'rsa_signature_base64' => $bogusSignature,
                'manifest' => ['slug' => 'hello', 'version' => '1.0.0'],
            ],
        ], 200),
        'cdn.test/*' => Http::response($artifact['bytes'], 200),
    ]);

    $this->artisan('escalated:plugin:install', [
        'slug' => 'acme/hello',
        '--public-key' => __DIR__.'/../Fixtures/rsa.pub',
    ])->assertFailed();

    expect(File::exists($this->workspace.'/hello/Plugin.php'))->toBeFalse();

    File::delete($artifact['path']);
});

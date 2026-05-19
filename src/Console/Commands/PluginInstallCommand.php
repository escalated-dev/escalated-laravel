<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Services\PluginService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use RuntimeException;
use ZipArchive;

/**
 * Phase 2.2f counterpart to `esc-plugin publish`:
 *
 *   php artisan escalated:plugin:install <slug>
 *
 * Calls the marketplace manifest endpoint, downloads the artifact,
 * verifies the published sha256 (and the optional RSA-SHA256
 * signature if a public key is configured), extracts the tarball
 * into the configured plugin path, and asks the PluginService to
 * register any service-provider declared by the manifest.
 *
 * The cloud-side publish endpoint is documented in
 * cloud.escalated.dev `MarketplacePluginVersionController@store`;
 * the install endpoint shape consumed here is the GET counterpart
 * keyed on publisher + plugin slug.
 */
class PluginInstallCommand extends Command
{
    protected $signature = 'escalated:plugin:install
        {slug : Marketplace plugin slug (or publisher/slug shorthand)}
        {--publisher= : Publisher slug; defaults to the first segment of slug if it contains a "/"}
        {--plugin-version= : Specific semver to install; defaults to the latest published version}
        {--public-key= : Path to a PEM-encoded RSA public key used to verify rsa_signature_base64}
        {--force : Overwrite the plugin directory if it already exists}';

    protected $description = 'Install an Escalated marketplace plugin with verified artifact hash + optional signature';

    public function handle(PluginService $plugins): int
    {
        [$publisher, $slug] = $this->resolvePublisherAndSlug();

        if ($publisher === null || $publisher === '') {
            $this->error('Missing --publisher (or use the publisher/slug shorthand).');

            return self::FAILURE;
        }

        $this->info("Resolving manifest for {$publisher}/{$slug}…");

        $manifestEnvelope = $this->fetchManifest($publisher, $slug, $this->option('plugin-version'));

        if ($manifestEnvelope === null) {
            return self::FAILURE;
        }

        $artifactUri = (string) ($manifestEnvelope['artifact_uri'] ?? '');
        $expectedSha = strtolower((string) ($manifestEnvelope['artifact_sha256'] ?? ''));
        $signatureB64 = $manifestEnvelope['rsa_signature_base64'] ?? null;
        $manifest = $manifestEnvelope['manifest'] ?? [];

        if ($artifactUri === '' || $expectedSha === '') {
            $this->error('Marketplace response is missing artifact_uri or artifact_sha256.');

            return self::FAILURE;
        }

        $tempPath = $this->downloadArtifact($artifactUri, $slug);
        if ($tempPath === null) {
            return self::FAILURE;
        }

        if (! $this->verifySha256($tempPath, $expectedSha)) {
            File::delete($tempPath);

            return self::FAILURE;
        }

        if ($signatureB64 && ! $this->verifySignature($tempPath, (string) $signatureB64)) {
            File::delete($tempPath);

            return self::FAILURE;
        }

        $targetPath = $this->resolveTargetPath($slug);

        if (File::exists($targetPath)) {
            if (! $this->option('force')) {
                $this->error("Plugin directory already exists at {$targetPath}. Re-run with --force to overwrite.");
                File::delete($tempPath);

                return self::FAILURE;
            }

            File::deleteDirectory($targetPath);
        }

        if (! $this->extractArtifact($tempPath, $targetPath)) {
            File::delete($tempPath);

            return self::FAILURE;
        }

        File::delete($tempPath);

        $this->writeManifest($targetPath, $manifest, $manifestEnvelope);

        $this->registerServiceProvider($plugins, $slug, $manifest);

        $this->newLine();
        $this->components->info("Plugin \"{$slug}\" installed and verified.");

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function resolvePublisherAndSlug(): array
    {
        $raw = (string) $this->argument('slug');
        $publisher = $this->option('publisher');

        if (str_contains($raw, '/')) {
            [$publisherFromSlug, $slug] = explode('/', $raw, 2);

            return [
                $publisher ?: $publisherFromSlug,
                $slug,
            ];
        }

        return [$publisher, $raw];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchManifest(string $publisher, string $slug, ?string $version): ?array
    {
        $base = rtrim((string) config('escalated.marketplace.url', config('escalated.hosted.api_url', 'https://cloud.escalated.dev/api/v1')), '/');
        $path = $version
            ? "/marketplace/publishers/{$publisher}/plugins/{$slug}/versions/{$version}"
            : "/marketplace/publishers/{$publisher}/plugins/{$slug}/versions/latest";

        try {
            $response = $this->httpClient()->get($base.$path);
        } catch (\Throwable $e) {
            $this->error('Could not reach marketplace: '.$e->getMessage());

            return null;
        }

        if ($response->status() === 404) {
            $this->error("Plugin {$publisher}/{$slug} not found in marketplace.");

            return null;
        }

        if (! $response->successful()) {
            $this->error('Marketplace returned HTTP '.$response->status());

            return null;
        }

        $body = $response->json();
        $envelope = $body['data'] ?? $body;

        if (! is_array($envelope)) {
            $this->error('Marketplace response was not JSON-shaped.');

            return null;
        }

        return $envelope;
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::acceptJson()
            ->timeout(20)
            ->withHeaders([
                'X-Escalated-Version' => (string) config('escalated.version', '0.6.0'),
            ]);

        $token = (string) config('escalated.hosted.api_key', '');
        if ($token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function downloadArtifact(string $url, string $slug): ?string
    {
        $tempDir = storage_path('app/temp');
        if (! File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $tempPath = $tempDir.DIRECTORY_SEPARATOR.$slug.'-'.bin2hex(random_bytes(4));

        try {
            $response = Http::timeout(120)->get($url);
        } catch (\Throwable $e) {
            $this->error('Download failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            $this->error('Download failed: HTTP '.$response->status());

            return null;
        }

        if (file_put_contents($tempPath, $response->body()) === false) {
            $this->error('Could not write downloaded artifact to disk.');

            return null;
        }

        return $tempPath;
    }

    private function verifySha256(string $path, string $expected): bool
    {
        $actual = hash_file('sha256', $path);

        if (! is_string($actual) || strtolower($actual) !== $expected) {
            $this->error('Artifact sha256 mismatch.');
            $this->line("  Expected: {$expected}");
            $this->line('  Actual:   '.($actual ?: '<hash failed>'));

            return false;
        }

        $this->line('  sha256 ok');

        return true;
    }

    private function verifySignature(string $path, string $signatureBase64): bool
    {
        $keyPath = $this->option('public-key')
            ?: config('escalated.marketplace.public_key_path');

        if (! $keyPath) {
            $this->warn('Skipping signature verification: no --public-key path provided and escalated.marketplace.public_key_path is unset.');

            return true;
        }

        if (! File::exists($keyPath)) {
            $this->error("Public key not found at {$keyPath}.");

            return false;
        }

        $pem = File::get($keyPath);
        $signature = base64_decode($signatureBase64, true);

        if ($signature === false) {
            $this->error('rsa_signature_base64 is not valid base64.');

            return false;
        }

        $payload = file_get_contents($path);
        if ($payload === false) {
            $this->error('Could not re-read artifact for signature verification.');

            return false;
        }

        $result = openssl_verify($payload, $signature, $pem, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            $this->error('RSA-SHA256 signature does not match.');

            return false;
        }

        $this->line('  signature ok');

        return true;
    }

    private function resolveTargetPath(string $slug): string
    {
        $base = config('escalated.plugins.path', app_path('Plugins/Escalated'));

        return rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$slug;
    }

    private function extractArtifact(string $tempPath, string $targetPath): bool
    {
        File::makeDirectory($targetPath, 0755, true, true);

        $type = $this->detectArchiveType($tempPath);

        try {
            if ($type === 'zip') {
                $zip = new ZipArchive;

                if ($zip->open($tempPath) !== true) {
                    throw new RuntimeException('Failed to open downloaded ZIP.');
                }

                $this->assertSafeZipEntries($zip);

                $zip->extractTo($targetPath);
                $zip->close();

                return true;
            }

            if ($type === 'tar.gz' || $type === 'tar') {
                $phar = new PharData($tempPath);
                $phar->extractTo($targetPath, null, true);

                return true;
            }
        } catch (\Throwable $e) {
            $this->error('Failed to extract artifact: '.$e->getMessage());
            File::deleteDirectory($targetPath);

            return false;
        }

        $this->error('Unrecognised artifact format. Expected .zip, .tar, or .tar.gz.');
        File::deleteDirectory($targetPath);

        return false;
    }

    private function assertSafeZipEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i) ?: '';
            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, '..') || str_starts_with($entry, '/')) {
                throw new RuntimeException("Artifact contains unsafe path: {$entry}");
            }
        }
    }

    private function detectArchiveType(string $path): ?string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic === false) {
            return null;
        }

        if (str_starts_with($magic, "PK\x03\x04") || str_starts_with($magic, "PK\x05\x06")) {
            return 'zip';
        }

        if (str_starts_with($magic, "\x1f\x8b")) {
            return 'tar.gz';
        }

        if (str_ends_with(strtolower($path), '.tar')) {
            return 'tar';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $envelope
     */
    private function writeManifest(string $targetPath, array $manifest, array $envelope): void
    {
        $manifestPath = $targetPath.DIRECTORY_SEPARATOR.'plugin.json';

        $payload = $manifest;

        if (File::exists($manifestPath)) {
            $existing = json_decode(File::get($manifestPath), true);
            if (is_array($existing)) {
                $payload = array_merge($existing, $payload);
            }
        }

        $payload['_install'] = [
            'artifact_sha256' => $envelope['artifact_sha256'] ?? null,
            'artifact_uri' => $envelope['artifact_uri'] ?? null,
            'installed_at' => now()->toIso8601String(),
        ];

        File::put($manifestPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function registerServiceProvider(PluginService $plugins, string $slug, array $manifest): void
    {
        $provider = $manifest['service_provider'] ?? null;

        if (is_string($provider) && $provider !== '' && class_exists($provider)) {
            $this->getLaravel()->register($provider);
            $this->line("  registered service provider: {$provider}");
        }

        $plugins->loadPlugin($slug);
    }
}

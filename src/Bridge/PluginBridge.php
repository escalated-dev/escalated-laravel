<?php

namespace Escalated\Laravel\Bridge;

use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Core bridge between Laravel and the Node.js plugin runtime.
 *
 * Architecture
 * ────────────
 * The bridge spawns `node @escalated-dev/plugin-runtime` as a long-lived child
 * process. Communication is bidirectional JSON-RPC 2.0 over stdio (newline-
 * delimited JSON). The plugin runtime loads all installed SDK plugins, handles
 * their lifecycle, and routes hook dispatches from the host.
 *
 * The process is spawned LAZILY on the first hook dispatch, not at boot time.
 * This avoids slowing down requests that never touch plugins (health checks, etc.).
 * Boot registers plugin routes from the manifests cached by the last successful
 * start, so the runtime only starts at boot while nothing is cached. When the
 * default runtime entrypoint is not installed, nothing is spawned at all.
 *
 * Heartbeat & restart
 * ───────────────────
 * A heartbeat ping is sent every 30 s. If no pong is received within 60 s the
 * process is killed and restarted (with exponential backoff up to 5 minutes).
 * The backoff state lives in the cache, so it holds across PHP-FPM requests,
 * which each build a new bridge.
 *
 * Queue depth
 * ───────────
 * Action hook messages are queued internally up to 1 000 entries. Beyond that
 * new action hooks are dropped with a warning. Filter hooks return the
 * unmodified value instead of being dropped.
 */
class PluginBridge
{
    /** @var resource[]|null [stdin, stdout, stderr, process] */
    private ?array $process = null;

    /** @var resource|null */
    private mixed $stdin = null;

    /** @var resource|null */
    private mixed $stdout = null;

    private ?JsonRpcClient $rpc = null;

    private ContextHandler $contextHandler;

    private RouteRegistrar $routeRegistrar;

    /** @var array<string, array>  Plugin name → manifest */
    private array $manifests = [];

    private bool $booted = false;

    private bool $routesRegistered = false;

    private bool $supportsHttpContract = false;

    private const MAX_BACKOFF_SECS = 300; // 5 minutes

    /** Cache keys: restart backoff, last manifests, missing-runtime notice */
    private const BACKOFF_CACHE_KEY = 'escalated.plugin_bridge.backoff';

    private const MANIFEST_CACHE_KEY = 'escalated.plugin_bridge.manifests';

    private const MISSING_NOTICE_CACHE_KEY = 'escalated.plugin_bridge.missing_runtime_logged';

    private const MANIFEST_CACHE_TTL = 86400;

    /** Result of `node --version`, checked once per bridge */
    private ?bool $nodeAvailable = null;

    /** Queue depth limit for fire-and-forget action hooks */
    private const MAX_QUEUE_DEPTH = 1000;

    /** Pending action count (tracks in-flight actions to enforce queue limit) */
    private int $pendingActionCount = 0;

    /** Protocol version we speak */
    private const PROTOCOL_VERSION = '1.0';

    private const HOST_NAME = 'laravel';

    /**
     * Timeout constants (seconds) per call type.
     */
    private const TIMEOUT_ACTION = 30;

    private const TIMEOUT_FILTER = 5;

    private const TIMEOUT_ENDPOINT = 30;

    private const TIMEOUT_WEBHOOK = 60;

    private const TIMEOUT_HANDSHAKE = 15;

    private const TIMEOUT_MANIFEST = 15;

    public function __construct()
    {
        $this->contextHandler = new ContextHandler;
        $this->contextHandler->setBridge($this);
        $this->routeRegistrar = app(RouteRegistrar::class, ['bridge' => $this]);
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Boot the bridge: load the plugin manifests and register their routes.
     *
     * Called from EscalatedServiceProvider::boot() on every request and
     * command, so it does not spawn the runtime when the manifests from the
     * last successful start are cached; the runtime then starts on the first
     * dispatch. With nothing cached it starts the runtime to fetch them.
     * Safe to call when Node.js or the runtime package is not installed:
     * plugins simply won't be available.
     */
    public function boot(): void
    {
        app(TenantContext::class)->assertPluginRuntimeSupported();
        if ($this->booted) {
            return;
        }

        if (! $this->isRuntimeAvailable()) {
            return;
        }

        $cached = $this->cachedManifests();
        if ($cached !== null) {
            $this->manifests = $cached;
            SdkPluginActivation::recordInstalled(array_keys($this->manifests));
            $this->registerRoutes();
            $this->booted = true;

            return;
        }

        try {
            $this->ensureRunning();
        } catch (\Throwable $e) {
            Log::warning('Escalated PluginBridge: boot failed — SDK plugins disabled', [
                'error' => $e->getMessage(),
            ]);
            $this->teardown();
        }
    }

    /**
     * Dispatch a fire-and-forget action hook to SDK plugins.
     *
     * Blocks until the runtime acknowledges the action (or until the 30 s
     * timeout). Errors are caught and logged — action hooks are best-effort.
     */
    public function dispatchAction(string $hook, array $event): void
    {
        if (! $this->ensureAlive()) {
            return;
        }

        if ($this->pendingActionCount >= self::MAX_QUEUE_DEPTH) {
            Log::warning("Escalated PluginBridge: action queue full — dropping action '{$hook}'");

            return;
        }

        // Only active plugins get hooks. The runtime loads every installed
        // plugin, so the active names go along for a runtime that gates on them.
        $active = SdkPluginActivation::activeNames(array_keys($this->manifests));
        if ($active === []) {
            return;
        }

        $this->pendingActionCount++;

        try {
            $this->contextHandler->serve($active, fn () => $this->rpc->call(
                'action',
                ['hook' => $hook, 'event' => $event, 'plugins' => $active],
                self::TIMEOUT_ACTION,
                [$this->contextHandler, 'handle']
            ), hook: true);
        } catch (\Throwable $e) {
            Log::warning("Escalated PluginBridge: action '{$hook}' failed", [
                'error' => $e->getMessage(),
            ]);
            $this->handleCrash();
        } finally {
            $this->pendingActionCount--;
        }
    }

    /**
     * Apply a filter hook through SDK plugins.
     *
     * Returns the filtered value, or the original $value on timeout/error.
     */
    public function applyFilter(string $hook, mixed $value): mixed
    {
        if (! $this->ensureAlive()) {
            return $value;
        }

        $active = SdkPluginActivation::activeNames(array_keys($this->manifests));
        if ($active === []) {
            return $value;
        }

        try {
            $result = $this->contextHandler->serve($active, fn () => $this->rpc->call(
                'filter',
                ['hook' => $hook, 'value' => $value, 'plugins' => $active],
                self::TIMEOUT_FILTER,
                [$this->contextHandler, 'handle']
            ), hook: true);

            return $result ?? $value;
        } catch (\Throwable $e) {
            Log::warning("Escalated PluginBridge: filter '{$hook}' failed — returning unmodified value", [
                'error' => $e->getMessage(),
            ]);
            $this->handleCrash();

            return $value;
        }
    }

    /**
     * Run one of a plugin's cron schedules (`cron {plugin, schedule}`) within
     * the action timeout. Fire-and-forget: a failure is logged, never thrown.
     */
    public function cron(string $plugin, string $schedule): void
    {
        if (! $this->ensureAlive() || ! SdkPluginActivation::isActive($plugin)) {
            return;
        }

        try {
            $this->contextHandler->serve([$plugin], fn () => $this->rpc->call(
                'cron',
                ['plugin' => $plugin, 'schedule' => $schedule],
                self::TIMEOUT_ACTION,
                [$this->contextHandler, 'handle']
            ));
        } catch (\Throwable $e) {
            Log::warning("Escalated PluginBridge: {$plugin} cron '{$schedule}' failed", [
                'error' => $e->getMessage(),
            ]);
            $this->handleCrash();
        }
    }

    /**
     * Ask a widget's badge (`badge {plugin, component}`) within the filter
     * timeout, as the signed-in user of the current request. A number or a
     * string; null when the runtime fails or answers anything else.
     */
    public function badge(string $plugin, string $component): int|float|string|null
    {
        if (! $this->ensureAlive() || ! SdkPluginActivation::isActive($plugin)) {
            return null;
        }

        try {
            $answer = $this->contextHandler->serve([$plugin], fn () => $this->rpc->call(
                'badge',
                ['plugin' => $plugin, 'component' => $component],
                self::TIMEOUT_FILTER,
                [$this->contextHandler, 'handle']
            ));
        } catch (\Throwable $e) {
            Log::warning("Escalated PluginBridge: {$plugin} badge '{$component}' failed", [
                'error' => $e->getMessage(),
            ]);
            $this->handleCrash();

            return null;
        }

        return is_int($answer) || is_float($answer) || is_string($answer) ? $answer : null;
    }

    /**
     * Call a plugin's data endpoint (used by API route handlers and page props).
     */
    public function callEndpoint(string $plugin, string $method, string $path, array $request = []): mixed
    {
        if (! $this->ensureAlive()) {
            throw new RuntimeException('Plugin runtime is not available');
        }

        // An inactive plugin's endpoints and pages do not exist.
        abort_unless(SdkPluginActivation::isActive($plugin), 404);

        $this->requireHttpContract($request);

        return $this->contextHandler->serve([$plugin], fn () => $this->rpc->call(
            'endpoint',
            [
                'plugin' => $plugin,
                'method' => $method,
                'path' => $path,
                'body' => $request['body'] ?? null,
                'params' => $request['params'] ?? new \stdClass,
                'query' => $request['query'] ?? new \stdClass,
                'headers' => $request['headers'] ?? [],
                ...array_intersect_key($request, array_flip(['httpContract', 'rawBodyBase64', 'clientIp'])),
            ],
            self::TIMEOUT_ENDPOINT,
            [$this->contextHandler, 'handle']
        ));
    }

    /**
     * Call a plugin's webhook handler (used by webhook route handlers).
     */
    public function callWebhook(string $plugin, string $method, string $path, array $body, array $headers, array $transport = []): mixed
    {
        if (! $this->ensureAlive()) {
            throw new RuntimeException('Plugin runtime is not available');
        }

        abort_unless(SdkPluginActivation::isActive($plugin), 404);

        $this->requireHttpContract($transport);

        return $this->contextHandler->serve([$plugin], fn () => $this->rpc->call(
            'webhook',
            [
                'plugin' => $plugin,
                'method' => $method,
                'path' => $path,
                'body' => $body,
                'headers' => $headers,
                'params' => $transport['params'] ?? new \stdClass,
                'query' => $transport['query'] ?? new \stdClass,
                ...array_intersect_key($transport, array_flip(['httpContract', 'rawBodyBase64', 'clientIp'])),
            ],
            self::TIMEOUT_WEBHOOK,
            [$this->contextHandler, 'handle']
        ));
    }

    /**
     * Tell the runtime a plugin was activated (`activate {plugin}`), which
     * calls its onActivate(ctx). A failure is logged, never thrown: it does
     * not undo the activation.
     */
    public function activate(string $plugin): void
    {
        $this->lifecycle('activate', $plugin);
    }

    /**
     * Tell the runtime a plugin is being deactivated (`deactivate {plugin}`),
     * which calls its onDeactivate(ctx) while it is still active.
     */
    public function deactivate(string $plugin): void
    {
        $this->lifecycle('deactivate', $plugin);
    }

    private function lifecycle(string $method, string $plugin): void
    {
        if (! $this->ensureAlive()) {
            return;
        }

        try {
            $this->contextHandler->serve([$plugin], fn () => $this->rpc->call(
                $method,
                ['plugin' => $plugin],
                self::TIMEOUT_ACTION,
                [$this->contextHandler, 'handle']
            ));
        } catch (\Throwable $e) {
            Log::warning("Escalated PluginBridge: {$plugin} {$method} failed", [
                'plugin' => $plugin,
                'error' => $e->getMessage(),
            ]);
            $this->handleCrash();
        }
    }

    /**
     * Return the manifests keyed by plugin name (empty if bridge not booted).
     *
     * @return array<string, array>
     */
    public function getManifests(): array
    {
        return $this->manifests;
    }

    private function requireHttpContract(array $request): void
    {
        if (isset($request['httpContract'])) {
            abort_unless($request['httpContract'] === 1 && $this->supportsHttpContract, 503,
                'Upgrade the plugin runtime to support HTTP contract version 1.');
        }
    }

    /**
     * Return whether the bridge has successfully booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    // =========================================================================
    // Process lifecycle
    // =========================================================================

    /**
     * Check that SDK plugins are enabled and the runtime entrypoint exists.
     *
     * Cheap: no process is started. When the runtime command is
     * `node <script>`, the script must exist (relative to the runtime cwd);
     * a missing one is logged at info level once a day. Other command shapes
     * cannot be checked without running them, so they pass.
     */
    private function isRuntimeAvailable(): bool
    {
        if (! config('escalated.plugins.sdk_enabled', true)) {
            return false;
        }

        $entrypoint = $this->runtimeEntrypoint();
        if ($entrypoint === null || is_file($entrypoint)) {
            return true;
        }

        if ($this->cache(fn () => Cache::add(self::MISSING_NOTICE_CACHE_KEY, true, self::MANIFEST_CACHE_TTL), true)) {
            Log::info('Escalated PluginBridge: plugin runtime not installed — SDK plugins disabled', [
                'entrypoint' => $entrypoint,
                'hint' => 'Install @escalated-dev/plugin-runtime, or set ESCALATED_PLUGINS_SDK_ENABLED=false.',
            ]);
        }

        return false;
    }

    /**
     * Check that `node` is on PATH. Run once per bridge, right before a spawn.
     */
    private function isNodeAvailable(): bool
    {
        if ($this->nodeAvailable === null) {
            $nodeCheck = @shell_exec('node --version 2>&1');
            $this->nodeAvailable = is_string($nodeCheck) && str_starts_with(trim($nodeCheck), 'v');
        }

        return $this->nodeAvailable;
    }

    private function runtimeCommand(): string
    {
        return config('escalated.plugins.runtime_command')
            ?: 'node node_modules/@escalated-dev/plugin-runtime/build/bin/escalated-plugins.js';
    }

    /**
     * The working directory for the subprocess — default to the Laravel
     * base_path() so Node can resolve node_modules.
     */
    private function runtimeCwd(): string
    {
        return config('escalated.plugins.runtime_cwd') ?: base_path();
    }

    /**
     * The script a `node <script>` runtime command runs, as an absolute path,
     * or null when the command has another shape (npx, a wrapper script...).
     */
    private function runtimeEntrypoint(): ?string
    {
        $tokens = preg_split('/\s+/', trim($this->runtimeCommand())) ?: [];
        $tokens = array_map(fn (string $token) => trim($token, '"\''), $tokens);

        $binary = strtolower(basename(str_replace('\\', '/', $tokens[0] ?? '')));
        if (! in_array($binary, ['node', 'node.exe'], true)) {
            return null;
        }

        foreach (array_slice($tokens, 1) as $token) {
            if ($token === '' || str_starts_with($token, '-')) {
                continue;
            }

            if (! preg_match('/\.[cm]?js$/i', $token)) {
                return null;
            }

            if (str_starts_with($token, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $token)) {
                return $token;
            }

            return rtrim($this->runtimeCwd(), '\\/').DIRECTORY_SEPARATOR.$token;
        }

        return null;
    }

    /**
     * The manifests cached by the last successful start, while they still
     * belong to the installed runtime and node packages.
     *
     * @return array<string, array>|null
     */
    private function cachedManifests(): ?array
    {
        $cached = $this->cache(fn () => Cache::get(self::MANIFEST_CACHE_KEY));

        if (! is_array($cached)
            || ($cached['fingerprint'] ?? null) !== $this->installFingerprint()
            || ! is_array($cached['manifests'] ?? null)) {
            return null;
        }

        return $cached['manifests'];
    }

    /**
     * Changes when the runtime command, its entrypoint or the installed node
     * packages change, so installing or removing a plugin refetches manifests.
     */
    private function installFingerprint(): string
    {
        $cwd = rtrim($this->runtimeCwd(), '\\/');
        $parts = [$this->runtimeCommand(), $cwd];

        $files = [
            $this->runtimeEntrypoint(),
            $cwd.'/package.json',
            $cwd.'/package-lock.json',
            $cwd.'/pnpm-lock.yaml',
            $cwd.'/yarn.lock',
            $cwd.'/node_modules/.package-lock.json',
            $cwd.'/node_modules/.modules.yaml',
        ];

        foreach (array_filter($files) as $file) {
            $parts[] = $file.'@'.(is_file($file) ? (string) @filemtime($file) : '-');
        }

        return sha1(implode('|', $parts));
    }

    /**
     * Run a cache call. A cache that is down, or not set up yet, must not
     * break the bridge.
     */
    private function cache(callable $call, mixed $fallback = null): mixed
    {
        try {
            return $call();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * Spawn the Node.js plugin runtime subprocess.
     */
    private function spawn(): void
    {
        if (! $this->isNodeAvailable()) {
            throw new RuntimeException('Node.js is not on PATH');
        }

        $command = $this->runtimeCommand();
        $cwd = $this->runtimeCwd();

        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin  (we write to it)
            1 => ['pipe', 'w'],  // stdout (we read from it)
            2 => ['pipe', 'w'],  // stderr (we read errors from it)
        ];

        $proc = proc_open($command, $descriptorSpec, $pipes, $cwd);

        if (! is_resource($proc)) {
            throw new RuntimeException("Failed to spawn plugin runtime: {$command}");
        }

        // Make stdout non-blocking so we can use stream_select in the RPC client.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->process = [$proc, $pipes];
        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->rpc = new JsonRpcClient($this->stdin, $this->stdout);

        Log::info('Escalated PluginBridge: plugin runtime spawned');
    }

    /**
     * Perform the handshake with the runtime.
     */
    private function handshake(): void
    {
        $result = $this->contextHandler->serve([], fn () => $this->rpc->call(
            'handshake',
            [
                'protocol_version' => self::PROTOCOL_VERSION,
                'host' => self::HOST_NAME,
                'host_version' => $this->hostVersion(),
            ],
            self::TIMEOUT_HANDSHAKE,
            [$this->contextHandler, 'handle']
        ));

        if (! ($result['compatible'] ?? false)) {
            $runtimeVer = $result['runtime_version'] ?? 'unknown';
            $protocolVer = $result['protocol_version'] ?? 'unknown';

            throw new RuntimeException(
                "Plugin runtime protocol mismatch: runtime speaks v{$protocolVer} (v{$runtimeVer}), host speaks v".self::PROTOCOL_VERSION
            );
        }

        $this->supportsHttpContract = in_array(1, (array) ($result['http_contract_versions'] ?? []), true);

        Log::info('Escalated PluginBridge: handshake OK', [
            'runtime_version' => $result['runtime_version'] ?? 'unknown',
            'protocol_version' => $result['protocol_version'] ?? 'unknown',
        ]);
    }

    /**
     * Fetch the plugin manifest from the runtime and store locally.
     */
    private function fetchManifests(): void
    {
        $result = $this->contextHandler->serve([], fn () => $this->rpc->call(
            'manifest',
            [],
            self::TIMEOUT_MANIFEST,
            [$this->contextHandler, 'handle']
        ));

        // The runtime returns an array of plugin manifest objects.
        // Normalise to a map keyed by plugin name.
        if (is_array($result)) {
            foreach ($result as $manifest) {
                $name = $manifest['name'] ?? null;

                if ($name !== null) {
                    $this->manifests[$name] = $manifest;
                }
            }
        }

        Log::info('Escalated PluginBridge: received manifests', [
            'plugins' => array_keys($this->manifests),
        ]);

        $this->cache(fn () => Cache::put(self::MANIFEST_CACHE_KEY, [
            'fingerprint' => $this->installFingerprint(),
            'manifests' => $this->manifests,
        ], self::MANIFEST_CACHE_TTL));

        SdkPluginActivation::recordInstalled(array_keys($this->manifests));
    }

    /**
     * Register routes from the loaded manifests.
     */
    private function registerRoutes(): void
    {
        if ($this->routesRegistered || empty($this->manifests)) {
            return;
        }

        $this->routeRegistrar->registerAll($this->manifests);
        $this->routesRegistered = true;
    }

    /**
     * Ensure the runtime is running, spawning it lazily if needed.
     * Returns false if the runtime could not be started.
     */
    private function ensureRunning(): bool
    {
        if ($this->isProcessAlive()) {
            return true;
        }

        // Enforce exponential backoff on repeated restarts. The state is
        // cached because each PHP-FPM request builds a new bridge.
        $state = $this->cache(fn () => Cache::get(self::BACKOFF_CACHE_KEY));
        $attempts = is_array($state) ? (int) ($state['attempts'] ?? 0) : 0;
        $lastRestartAt = is_array($state) ? (int) ($state['at'] ?? 0) : 0;

        if ($attempts > 0) {
            $backoff = $this->backoffSeconds($attempts);

            if (time() - $lastRestartAt < $backoff) {
                Log::debug('Escalated PluginBridge: waiting for backoff before restart', [
                    'backoff_remaining' => $backoff - (time() - $lastRestartAt),
                ]);

                return false;
            }
        }

        try {
            $this->teardown();
            $this->spawn();
            $this->handshake();
            $this->fetchManifests();
            $this->registerRoutes();

            if ($attempts > 0) {
                $this->cache(fn () => Cache::forget(self::BACKOFF_CACHE_KEY));
            }
            $this->booted = true;

            return true;
        } catch (\Throwable $e) {
            $attempts++;
            $this->cache(fn () => Cache::put(
                self::BACKOFF_CACHE_KEY,
                ['attempts' => $attempts, 'at' => time()],
                self::MAX_BACKOFF_SECS * 2
            ));

            Log::error('Escalated PluginBridge: failed to start plugin runtime', [
                'error' => $e->getMessage(),
                'attempts' => $attempts,
                'retry_in_seconds' => $this->backoffSeconds($attempts),
            ]);

            $this->teardown();

            return false;
        }
    }

    /** 5 s, 10 s, 20 s... up to MAX_BACKOFF_SECS. */
    private function backoffSeconds(int $attempts): int
    {
        return min((int) (2 ** ($attempts - 1)) * 5, self::MAX_BACKOFF_SECS);
    }

    /**
     * Ensure the process is alive. Used before each RPC call.
     */
    private function ensureAlive(): bool
    {
        app(TenantContext::class)->assertPluginRuntimeSupported();
        if (! $this->isRuntimeAvailable()) {
            return false;
        }

        if ($this->isProcessAlive()) {
            return true;
        }

        return $this->ensureRunning();
    }

    /**
     * Check whether the subprocess is still running.
     */
    private function isProcessAlive(): bool
    {
        if ($this->process === null) {
            return false;
        }

        [$proc] = $this->process;

        if (! is_resource($proc)) {
            return false;
        }

        $status = proc_get_status($proc);

        return $status['running'] ?? false;
    }

    /**
     * Handle a process crash: log it and clean up so the next call triggers
     * a restart via ensureAlive().
     */
    private function handleCrash(): void
    {
        if (! $this->isProcessAlive()) {
            Log::warning('Escalated PluginBridge: plugin runtime process has crashed — will restart on next dispatch');
            $this->teardown();
        }
    }

    /**
     * Close the subprocess and clean up all handles.
     */
    private function teardown(): void
    {
        if ($this->stdin !== null && is_resource($this->stdin)) {
            @fclose($this->stdin);
        }

        if ($this->stdout !== null && is_resource($this->stdout)) {
            @fclose($this->stdout);
        }

        if ($this->process !== null) {
            [$proc, $pipes] = $this->process;

            // Close stderr pipe if it exists
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                @fclose($pipes[2]);
            }

            if (is_resource($proc)) {
                @proc_close($proc);
            }
        }

        $this->process = null;
        $this->stdin = null;
        $this->stdout = null;
        $this->rpc = null;
        $this->supportsHttpContract = false;
    }

    /**
     * Return the current host (package) version string.
     */
    private function hostVersion(): string
    {
        // Read from composer.json if available
        $composerPath = __DIR__.'/../../composer.json';

        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);

            return $composer['version'] ?? '0.0.0';
        }

        return '0.0.0';
    }

    // =========================================================================
    // Destructor — clean up subprocess on PHP shutdown
    // =========================================================================

    public function __destruct()
    {
        $this->teardown();
    }
}

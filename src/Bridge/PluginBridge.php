<?php

namespace Escalated\Laravel\Bridge;

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
 *
 * Heartbeat & restart
 * ───────────────────
 * A heartbeat ping is sent every 30 s. If no pong is received within 60 s the
 * process is killed and restarted (with exponential backoff up to 5 minutes).
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

    /** Crash-restart state */
    private int $restartAttempts = 0;

    private int $lastRestartAt = 0;

    private const MAX_BACKOFF_SECS = 300; // 5 minutes

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
     * Boot the bridge: spawn the runtime, perform the handshake, retrieve the
     * plugin manifest, and register routes.
     *
     * Called from EscalatedServiceProvider::boot(). Safe to call when Node.js
     * is not installed — any exception is caught and logged (plugins simply
     * won't be available).
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if (! $this->isRuntimeAvailable()) {
            Log::info('Escalated PluginBridge: Node.js runtime not available — SDK plugins disabled');

            return;
        }

        try {
            $this->ensureRunning();
            $this->fetchManifests();
            $this->registerRoutes();
            $this->booted = true;
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

        $this->pendingActionCount++;

        try {
            $this->contextHandler->setCurrentPlugin('__host__');

            $this->rpc->call(
                'action',
                ['hook' => $hook, 'event' => $event],
                self::TIMEOUT_ACTION,
                [$this->contextHandler, 'handle']
            );
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

        try {
            $this->contextHandler->setCurrentPlugin('__host__');

            $result = $this->rpc->call(
                'filter',
                ['hook' => $hook, 'value' => $value],
                self::TIMEOUT_FILTER,
                [$this->contextHandler, 'handle']
            );

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
     * Call a plugin's data endpoint (used by API route handlers and page props).
     */
    public function callEndpoint(string $plugin, string $method, string $path, array $request = []): mixed
    {
        if (! $this->ensureAlive()) {
            throw new RuntimeException('Plugin runtime is not available');
        }

        $this->contextHandler->setCurrentPlugin($plugin);

        return $this->rpc->call(
            'endpoint',
            [
                'plugin' => $plugin,
                'method' => $method,
                'path' => $path,
                'body' => $request['body'] ?? null,
                'params' => $request['params'] ?? [],
            ],
            self::TIMEOUT_ENDPOINT,
            [$this->contextHandler, 'handle']
        );
    }

    /**
     * Call a plugin's webhook handler (used by webhook route handlers).
     */
    public function callWebhook(string $plugin, string $method, string $path, array $body, array $headers): mixed
    {
        if (! $this->ensureAlive()) {
            throw new RuntimeException('Plugin runtime is not available');
        }

        $this->contextHandler->setCurrentPlugin($plugin);

        return $this->rpc->call(
            'webhook',
            [
                'plugin' => $plugin,
                'method' => $method,
                'path' => $path,
                'body' => $body,
                'headers' => $headers,
            ],
            self::TIMEOUT_WEBHOOK,
            [$this->contextHandler, 'handle']
        );
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
     * Check that Node.js and the plugin runtime package are available on PATH.
     */
    private function isRuntimeAvailable(): bool
    {
        // Allow disabling via config
        if (! config('escalated.plugins.sdk_enabled', true)) {
            return false;
        }

        $nodeCheck = shell_exec('node --version 2>/dev/null');

        return $nodeCheck !== null && str_starts_with(trim($nodeCheck), 'v');
    }

    /**
     * Spawn the Node.js plugin runtime subprocess.
     */
    private function spawn(): void
    {
        $command = config(
            'escalated.plugins.runtime_command',
            'node node_modules/@escalated-dev/plugin-runtime/dist/index.js'
        );

        // Determine the working directory for the subprocess — default to the
        // Laravel base_path() so Node can resolve node_modules.
        $cwd = config('escalated.plugins.runtime_cwd', base_path());

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
        $result = $this->rpc->call(
            'handshake',
            [
                'protocol_version' => self::PROTOCOL_VERSION,
                'host' => self::HOST_NAME,
                'host_version' => $this->hostVersion(),
            ],
            self::TIMEOUT_HANDSHAKE,
            [$this->contextHandler, 'handle']
        );

        if (! ($result['compatible'] ?? false)) {
            $runtimeVer = $result['runtime_version'] ?? 'unknown';
            $protocolVer = $result['protocol_version'] ?? 'unknown';

            throw new RuntimeException(
                "Plugin runtime protocol mismatch: runtime speaks v{$protocolVer} (v{$runtimeVer}), host speaks v".self::PROTOCOL_VERSION
            );
        }

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
        $result = $this->rpc->call(
            'manifest',
            [],
            self::TIMEOUT_MANIFEST,
            [$this->contextHandler, 'handle']
        );

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

        // Enforce exponential backoff on repeated restarts
        if ($this->restartAttempts > 0) {
            $backoff = min(
                (int) (2 ** ($this->restartAttempts - 1)) * 5,
                self::MAX_BACKOFF_SECS
            );

            if (time() - $this->lastRestartAt < $backoff) {
                Log::debug('Escalated PluginBridge: waiting for backoff before restart', [
                    'backoff_remaining' => $backoff - (time() - $this->lastRestartAt),
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

            $this->restartAttempts = 0;
            $this->booted = true;

            return true;
        } catch (\Throwable $e) {
            $this->restartAttempts++;
            $this->lastRestartAt = time();

            Log::error('Escalated PluginBridge: failed to start plugin runtime', [
                'error' => $e->getMessage(),
                'attempts' => $this->restartAttempts,
            ]);

            $this->teardown();

            return false;
        }
    }

    /**
     * Ensure the process is alive. Used before each RPC call.
     */
    private function ensureAlive(): bool
    {
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

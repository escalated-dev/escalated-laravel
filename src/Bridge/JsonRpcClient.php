<?php

namespace Escalated\Laravel\Bridge;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Low-level JSON-RPC 2.0 client over stdio.
 *
 * Writes newline-delimited JSON to the process stdin and reads responses
 * line-by-line from stdout. The communication is bidirectional — the plugin
 * runtime can send ctx.* callback requests back to the host while we are
 * waiting for a response to our own request.
 */
class JsonRpcClient
{
    /** @var resource */
    private mixed $stdin;

    /** @var resource */
    private mixed $stdout;

    /** @var array<int, array{resolve: callable, reject: callable}> */
    private array $pending = [];

    private int $nextId = 1;

    /** Maximum message size: 10 MB */
    private const MAX_MESSAGE_SIZE = 10 * 1024 * 1024;

    public function __construct(mixed $stdin, mixed $stdout)
    {
        $this->stdin = $stdin;
        $this->stdout = $stdout;
    }

    /**
     * Send a JSON-RPC request and block until the matching response arrives.
     * While waiting, any incoming ctx.* requests from the runtime are handled
     * by the provided $ctxHandler callable.
     *
     * @param  callable  $ctxHandler  fn(string $method, array $params, int $id): mixed
     * @return mixed The JSON-RPC result
     *
     * @throws RuntimeException On timeout or protocol error
     */
    public function call(string $method, array $params, int $timeoutSeconds, callable $ctxHandler): mixed
    {
        $id = $this->nextId++;

        $message = json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->writeLine($message);

        return $this->waitForResponse($id, $timeoutSeconds, $ctxHandler);
    }

    /**
     * Send a JSON-RPC notification (no response expected).
     */
    public function notify(string $method, array $params): void
    {
        $message = json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->writeLine($message);
    }

    /**
     * Send a JSON-RPC response back to the runtime (for ctx.* callbacks).
     */
    public function respond(int $id, mixed $result): void
    {
        $message = json_encode([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->writeLine($message);
    }

    /**
     * Send a JSON-RPC error response back to the runtime.
     */
    public function respondError(int $id, int $code, string $message): void
    {
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'error' => ['code' => $code, 'message' => $message],
            'id' => $id,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->writeLine($payload);
    }

    /**
     * Read one line from stdout (blocks until data is available or timeout).
     * Returns null on EOF/error.
     */
    public function readLine(int $timeoutSeconds): ?string
    {
        $read = [$this->stdout];
        $write = null;
        $except = null;

        $ready = stream_select($read, $write, $except, $timeoutSeconds);

        if ($ready === false || $ready === 0) {
            return null;
        }

        $line = fgets($this->stdout);

        if ($line === false) {
            return null;
        }

        if (strlen($line) > self::MAX_MESSAGE_SIZE) {
            throw new RuntimeException('JSON-RPC message exceeds maximum size of 10MB');
        }

        return rtrim($line, "\n\r");
    }

    /**
     * Block until we receive the response for $expectedId, dispatching any
     * interleaved ctx.* requests to $ctxHandler in the meantime.
     */
    private function waitForResponse(int $expectedId, int $timeoutSeconds, callable $ctxHandler): mixed
    {
        $deadline = time() + $timeoutSeconds;

        while (true) {
            $remaining = $deadline - time();

            if ($remaining <= 0) {
                throw new RuntimeException(
                    "JSON-RPC timeout waiting for response to request #{$expectedId}"
                );
            }

            $line = $this->readLine($remaining);

            if ($line === null) {
                throw new RuntimeException(
                    "JSON-RPC connection lost waiting for response to request #{$expectedId}"
                );
            }

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (! is_array($decoded) || ! isset($decoded['jsonrpc'])) {
                Log::warning('Escalated PluginBridge: received invalid JSON-RPC message', [
                    'raw' => substr($line, 0, 200),
                ]);

                continue;
            }

            // This is a request FROM the runtime (ctx.* callback)
            if (isset($decoded['method'])) {
                $this->handleIncomingRequest($decoded, $ctxHandler);

                continue;
            }

            // This is a response to one of our requests
            if (isset($decoded['id'])) {
                $msgId = (int) $decoded['id'];

                if ($msgId === $expectedId) {
                    if (isset($decoded['error'])) {
                        $err = $decoded['error'];
                        throw new RuntimeException(
                            'JSON-RPC error from plugin runtime: '.($err['message'] ?? 'unknown error'),
                            (int) ($err['code'] ?? 0)
                        );
                    }

                    return $decoded['result'] ?? null;
                }

                // Response to a different request — this should not happen in the
                // synchronous single-threaded model but log it and skip.
                Log::warning('Escalated PluginBridge: unexpected response id', [
                    'expected' => $expectedId,
                    'got' => $msgId,
                ]);
            }
        }
    }

    /**
     * Handle an incoming JSON-RPC request from the plugin runtime.
     * Calls $ctxHandler and sends back the response (or error).
     */
    private function handleIncomingRequest(array $message, callable $ctxHandler): void
    {
        $id = isset($message['id']) ? (int) $message['id'] : null;
        $method = $message['method'] ?? '';
        $params = $message['params'] ?? [];

        try {
            $result = $ctxHandler($method, $params);

            if ($id !== null) {
                $this->respond($id, $result);
            }
        } catch (\Throwable $e) {
            Log::warning('Escalated PluginBridge: ctx handler threw', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            if ($id !== null) {
                $this->respondError($id, -32000, $e->getMessage());
            }
        }
    }

    /**
     * Write a line to the process stdin.
     */
    private function writeLine(string $data): void
    {
        if (! is_resource($this->stdin)) {
            throw new RuntimeException('Plugin runtime stdin is not available');
        }

        $written = fwrite($this->stdin, $data."\n");

        if ($written === false) {
            throw new RuntimeException('Failed to write to plugin runtime stdin');
        }
    }
}

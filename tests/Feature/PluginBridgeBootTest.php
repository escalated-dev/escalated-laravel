<?php

use Escalated\Laravel\Bridge\PluginBridge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/*
 * Issue #314: the bridge boots on every request and command, so boot must not
 * spawn Node when the runtime is not installed, must reuse the manifests of
 * the last start, and must keep restart backoff across requests.
 */

/**
 * Write a fake plugin runtime to a temp dir. Each start appends a line to
 * starts.log; with $answer it speaks enough JSON-RPC for a handshake and a
 * manifest, without it exits at once (like a missing package).
 */
function fakePluginRuntime(bool $answer = true): string
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escalated-runtime-'.bin2hex(random_bytes(4));
    mkdir($dir);

    $script = <<<'JS'
const fs = require('fs');
const path = require('path');
fs.appendFileSync(path.join(__dirname, 'starts.log'), 'start\n');
if (process.argv[2] !== 'answer') process.exit(1);
const rl = require('readline').createInterface({ input: process.stdin });
const reply = (id, result) => process.stdout.write(JSON.stringify({ jsonrpc: '2.0', id, result }) + '\n');
rl.on('line', (line) => {
  const msg = JSON.parse(line);
  if (msg.id === undefined) return;
  if (msg.method === 'handshake') reply(msg.id, { compatible: true, protocol_version: '1.0', runtime_version: 'test' });
  else if (msg.method === 'manifest') reply(msg.id, [{ name: '@acme/plugin-crm' }]);
  else reply(msg.id, null);
});
rl.on('close', () => process.exit(0));
JS;
    file_put_contents($dir.DIRECTORY_SEPARATOR.'runtime.js', $script);

    config([
        'escalated.plugins.sdk_enabled' => true,
        'escalated.plugins.runtime_command' => 'node runtime.js'.($answer ? ' answer' : ''),
        'escalated.plugins.runtime_cwd' => $dir,
    ]);

    return $dir;
}

function runtimeStarts(string $dir): int
{
    $log = $dir.DIRECTORY_SEPARATOR.'starts.log';

    return is_file($log) ? substr_count((string) file_get_contents($log), 'start') : 0;
}

function skipWithoutNode(): void
{
    $version = @shell_exec('node --version 2>&1');
    if (! is_string($version) || ! str_starts_with(trim($version), 'v')) {
        test()->markTestSkipped('Node.js is not on PATH');
    }
    if (PHP_OS_FAMILY === 'Windows') {
        test()->markTestSkipped('stream_select does not work on proc_open pipes on Windows');
    }
}

beforeEach(fn () => Cache::flush());

it('does not spawn anything when the runtime entrypoint is missing, and says so once', function () {
    config([
        'escalated.plugins.sdk_enabled' => true,
        'escalated.plugins.runtime_command' => null,
        'escalated.plugins.runtime_cwd' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'no-node-modules-here',
    ]);
    Log::spy();

    $first = new PluginBridge;
    $first->boot();
    $second = new PluginBridge;
    $second->boot();
    $second->dispatchAction('ticket.created', ['id' => 1]);

    expect($first->isBooted())->toBeFalse()->and($second->isBooted())->toBeFalse();
    Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains($message, 'plugin runtime not installed'))->once();
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
});

it('boots later requests from the cached manifests and starts the runtime on the first dispatch', function () {
    skipWithoutNode();
    $dir = fakePluginRuntime();

    $first = new PluginBridge;
    $first->boot();
    expect($first->isBooted())->toBeTrue()
        ->and(array_keys($first->getManifests()))->toBe(['@acme/plugin-crm'])
        ->and(runtimeStarts($dir))->toBe(1);
    unset($first);

    // A new request: routes come from the cache, nothing is spawned.
    $second = new PluginBridge;
    $second->boot();
    expect($second->isBooted())->toBeTrue()
        ->and(array_keys($second->getManifests()))->toBe(['@acme/plugin-crm'])
        ->and(runtimeStarts($dir))->toBe(1);

    // The first hook dispatch starts the runtime.
    $second->dispatchAction('ticket.created', ['id' => 1]);
    expect(runtimeStarts($dir))->toBe(2);
});

it('refetches the manifests when the installed packages change', function () {
    skipWithoutNode();
    $dir = fakePluginRuntime();

    (new PluginBridge)->boot();
    expect(runtimeStarts($dir))->toBe(1);

    file_put_contents($dir.DIRECTORY_SEPARATOR.'package-lock.json', '{}');
    (new PluginBridge)->boot();

    expect(runtimeStarts($dir))->toBe(2);
});

it('keeps the restart backoff across requests when the runtime fails to start', function () {
    skipWithoutNode();
    $dir = fakePluginRuntime(answer: false);
    Log::spy();

    $first = new PluginBridge;
    $first->boot();
    unset($first);
    $second = new PluginBridge;
    $second->boot();
    $second->dispatchAction('ticket.created', ['id' => 1]);

    expect(runtimeStarts($dir))->toBe(1);
    Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'failed to start plugin runtime'))->once();
});

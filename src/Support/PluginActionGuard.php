<?php

namespace Escalated\Laravel\Support;

use Illuminate\Support\Facades\Log;

/**
 * Re-entrancy guard for plugin actions, after the workflow engine's.
 *
 * Plugin ctx writes go through the ticket service, so they raise canonical
 * events, and those are delivered to plugins while the plugin that wrote is
 * still handling its own action. A ticket.created handler that opens a
 * ticket would otherwise hand itself ticket.created without end. While an
 * action is being delivered, the same action is not delivered to plugins
 * again, and nesting stops at MAX_DEPTH. Webhooks and workflows still see
 * every event; only plugin delivery is skipped.
 */
final class PluginActionGuard
{
    /** How many plugin actions may nest. */
    public const MAX_DEPTH = 3;

    /** @var list<string> Actions being delivered, innermost last. */
    private static array $running = [];

    /**
     * Run $deliver for $hook unless that hook is already being delivered or
     * nesting is at MAX_DEPTH. Returns whether it ran.
     */
    public static function run(string $hook, callable $deliver): bool
    {
        if (in_array($hook, self::$running, true) || count(self::$running) >= self::MAX_DEPTH) {
            Log::debug('Escalated plugin action skipped: raised while it is being delivered', [
                'hook' => $hook,
                'running' => self::$running,
            ]);

            return false;
        }

        self::$running[] = $hook;

        try {
            $deliver();
        } finally {
            array_pop(self::$running);
        }

        return true;
    }
}

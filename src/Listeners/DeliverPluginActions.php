<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Facades\Hook;
use Escalated\Laravel\Support\ImportContext;
use Escalated\Laravel\Support\PluginEvents;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Delivers the canonical events to plugins' actions, under the canonical
 * name only, once the change is committed (domain-model/plugin-contract.md).
 *
 * Native plugins receive them through the hook manager: only active native
 * plugins are loaded, so only they have callbacks registered. SDK plugins
 * receive them through the runtime bridge. A failing plugin is logged and
 * never fails the request that caused the event.
 */
class DeliverPluginActions implements ShouldHandleEventsAfterCommit
{
    public function handle(object $event): void
    {
        if (ImportContext::isImporting() || ! config('escalated.plugins.enabled', true)) {
            return;
        }

        // No plugin code runs in tenant mode.
        if (app(TenantContext::class)->enabled()) {
            return;
        }

        $name = PluginEvents::nameOf($event);
        if ($name === null) {
            return;
        }

        try {
            $payload = PluginEvents::payload($event);
        } catch (\Throwable $e) {
            Log::warning("Escalated: could not build the plugin payload for '{$name}'", ['error' => $e->getMessage()]);

            return;
        }

        try {
            Hook::doAction($name, $payload);
        } catch (\Throwable $e) {
            Log::error("Escalated: plugin action '{$name}' failed", ['error' => $e->getMessage()]);
        }

        try {
            if (app()->bound(PluginBridge::class)) {
                $bridge = app(PluginBridge::class);
                if ($bridge->isBooted()) {
                    $bridge->dispatchAction($name, $payload);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Escalated: SDK plugin action '{$name}' failed", ['error' => $e->getMessage()]);
        }
    }
}

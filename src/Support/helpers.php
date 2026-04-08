<?php

use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Facades\Hook;
use Escalated\Laravel\Services\PluginUIService;
use Illuminate\Support\Facades\Log;

// ========================================
// ACTION HOOKS
// ========================================

if (! function_exists('escalated_add_action')) {
    /**
     * Add an action hook.
     */
    function escalated_add_action(string $tag, callable $callback, int $priority = 10): void
    {
        Hook::addAction($tag, $callback, $priority);
    }
}

if (! function_exists('escalated_do_action')) {
    /**
     * Execute all callbacks for an action.
     *
     * Dispatches to BOTH the legacy PHP hook system and any SDK-based plugins
     * registered via the plugin bridge (dual dispatch). This allows old PHP
     * plugins and new TypeScript SDK plugins to coexist during migration.
     *
     * The bridge dispatch is best-effort: if the runtime is unavailable or
     * times out, only a warning is logged and the PHP hooks still run.
     *
     * @param  mixed  ...$args
     */
    function escalated_do_action(string $tag, ...$args): void
    {
        // 1. Dispatch to legacy PHP plugins
        Hook::doAction($tag, ...$args);

        // 2. Dual-dispatch to SDK plugins via the bridge
        // The bridge normalises the variadic args into a single event array.
        // Convention: first arg is the primary payload (e.g. $ticket->toArray()).
        try {
            $bridge = app(PluginBridge::class);

            if ($bridge->isBooted()) {
                $event = [];

                if (count($args) === 1 && is_array($args[0])) {
                    $event = $args[0];
                } elseif (count($args) === 1 && is_object($args[0]) && method_exists($args[0], 'toArray')) {
                    $event = $args[0]->toArray();
                } elseif (! empty($args)) {
                    $event = ['args' => $args];
                }

                $bridge->dispatchAction($tag, $event);
            }
        } catch (Throwable $e) {
            // Never let bridge errors bubble up into the host application.
            Log::debug('Escalated: bridge action dispatch failed', [
                'hook' => $tag,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

if (! function_exists('escalated_has_action')) {
    /**
     * Check if an action has callbacks.
     */
    function escalated_has_action(string $tag): bool
    {
        return Hook::hasAction($tag);
    }
}

if (! function_exists('escalated_remove_action')) {
    /**
     * Remove an action hook.
     */
    function escalated_remove_action(string $tag, ?callable $callback = null): void
    {
        Hook::removeAction($tag, $callback);
    }
}

// ========================================
// FILTER HOOKS
// ========================================

if (! function_exists('escalated_add_filter')) {
    /**
     * Add a filter hook.
     */
    function escalated_add_filter(string $tag, callable $callback, int $priority = 10): void
    {
        Hook::addFilter($tag, $callback, $priority);
    }
}

if (! function_exists('escalated_apply_filters')) {
    /**
     * Apply all callbacks for a filter.
     *
     * Applies filters through BOTH the legacy PHP hook system and any SDK-based
     * plugins registered via the plugin bridge (dual dispatch). The value flows
     * through PHP hooks first, then through the SDK bridge, so SDK plugins see
     * the PHP-filtered value and can further transform it.
     *
     * On bridge timeout or error the bridge step is skipped and the PHP-filtered
     * value is returned unchanged.
     *
     * @param  mixed  ...$args
     */
    function escalated_apply_filters(string $tag, mixed $value, ...$args): mixed
    {
        // 1. Apply legacy PHP plugin filters
        $value = Hook::applyFilters($tag, $value, ...$args);

        // 2. Dual-dispatch to SDK plugins via the bridge
        try {
            $bridge = app(PluginBridge::class);

            if ($bridge->isBooted()) {
                $value = $bridge->applyFilter($tag, $value);
            }
        } catch (Throwable $e) {
            // Never let bridge errors bubble up into the host application.
            Log::debug('Escalated: bridge filter dispatch failed', [
                'hook' => $tag,
                'error' => $e->getMessage(),
            ]);
        }

        return $value;
    }
}

if (! function_exists('escalated_has_filter')) {
    /**
     * Check if a filter has callbacks.
     */
    function escalated_has_filter(string $tag): bool
    {
        return Hook::hasFilter($tag);
    }
}

if (! function_exists('escalated_remove_filter')) {
    /**
     * Remove a filter hook.
     */
    function escalated_remove_filter(string $tag, ?callable $callback = null): void
    {
        Hook::removeFilter($tag, $callback);
    }
}

// ========================================
// PLUGIN UI HELPERS
// ========================================

if (! function_exists('escalated_register_menu_item')) {
    /**
     * Register a custom menu item.
     *
     * @param  array  $item  Menu item configuration
     */
    function escalated_register_menu_item(array $item): void
    {
        app(PluginUIService::class)->addMenuItem($item);
    }
}

if (! function_exists('escalated_register_page')) {
    /**
     * Register a custom page route.
     *
     * @param  string  $route  Route name
     * @param  string  $component  Inertia component name
     * @param  array  $options  Additional options
     */
    function escalated_register_page(string $route, string $component, array $options = []): void
    {
        app(PluginUIService::class)->registerPage($route, $component, $options);
    }
}

if (! function_exists('escalated_register_dashboard_widget')) {
    /**
     * Register a dashboard widget.
     *
     * @param  array  $widget  Widget configuration
     */
    function escalated_register_dashboard_widget(array $widget): void
    {
        app(PluginUIService::class)->addDashboardWidget($widget);
    }
}

if (! function_exists('escalated_add_page_component')) {
    /**
     * Add a component to an existing page.
     *
     * @param  string  $page  Page identifier
     * @param  string  $slot  Slot name
     * @param  array  $component  Component configuration
     */
    function escalated_add_page_component(string $page, string $slot, array $component): void
    {
        app(PluginUIService::class)->addPageComponent($page, $slot, $component);
    }
}

if (! function_exists('escalated_get_page_components')) {
    /**
     * Get components for a specific page and slot.
     *
     * @param  string  $page  Page identifier
     * @param  string  $slot  Slot name
     */
    function escalated_get_page_components(string $page, string $slot): array
    {
        return app(PluginUIService::class)->getPageComponents($page, $slot);
    }
}

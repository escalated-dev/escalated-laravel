<?php

use Escalated\Laravel\Facades\Hook;

// ========================================
// ACTION HOOKS
// ========================================

if (! function_exists('escalated_add_action')) {
    /**
     * Add an action hook.
     *
     * @param string $tag
     * @param callable $callback
     * @param int $priority
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
     * @param string $tag
     * @param mixed ...$args
     */
    function escalated_do_action(string $tag, ...$args): void
    {
        Hook::doAction($tag, ...$args);
    }
}

if (! function_exists('escalated_has_action')) {
    /**
     * Check if an action has callbacks.
     *
     * @param string $tag
     * @return bool
     */
    function escalated_has_action(string $tag): bool
    {
        return Hook::hasAction($tag);
    }
}

if (! function_exists('escalated_remove_action')) {
    /**
     * Remove an action hook.
     *
     * @param string $tag
     * @param callable|null $callback
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
     *
     * @param string $tag
     * @param callable $callback
     * @param int $priority
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
     * @param string $tag
     * @param mixed $value
     * @param mixed ...$args
     * @return mixed
     */
    function escalated_apply_filters(string $tag, mixed $value, ...$args): mixed
    {
        return Hook::applyFilters($tag, $value, ...$args);
    }
}

if (! function_exists('escalated_has_filter')) {
    /**
     * Check if a filter has callbacks.
     *
     * @param string $tag
     * @return bool
     */
    function escalated_has_filter(string $tag): bool
    {
        return Hook::hasFilter($tag);
    }
}

if (! function_exists('escalated_remove_filter')) {
    /**
     * Remove a filter hook.
     *
     * @param string $tag
     * @param callable|null $callback
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
     * @param array $item Menu item configuration
     */
    function escalated_register_menu_item(array $item): void
    {
        app(\Escalated\Laravel\Services\PluginUIService::class)->addMenuItem($item);
    }
}

if (! function_exists('escalated_register_page')) {
    /**
     * Register a custom page route.
     *
     * @param string $route Route name
     * @param string $component Inertia component name
     * @param array $options Additional options
     */
    function escalated_register_page(string $route, string $component, array $options = []): void
    {
        app(\Escalated\Laravel\Services\PluginUIService::class)->registerPage($route, $component, $options);
    }
}

if (! function_exists('escalated_register_dashboard_widget')) {
    /**
     * Register a dashboard widget.
     *
     * @param array $widget Widget configuration
     */
    function escalated_register_dashboard_widget(array $widget): void
    {
        app(\Escalated\Laravel\Services\PluginUIService::class)->addDashboardWidget($widget);
    }
}

if (! function_exists('escalated_add_page_component')) {
    /**
     * Add a component to an existing page.
     *
     * @param string $page Page identifier
     * @param string $slot Slot name
     * @param array $component Component configuration
     */
    function escalated_add_page_component(string $page, string $slot, array $component): void
    {
        app(\Escalated\Laravel\Services\PluginUIService::class)->addPageComponent($page, $slot, $component);
    }
}

if (! function_exists('escalated_get_page_components')) {
    /**
     * Get components for a specific page and slot.
     *
     * @param string $page Page identifier
     * @param string $slot Slot name
     * @return array
     */
    function escalated_get_page_components(string $page, string $slot): array
    {
        return app(\Escalated\Laravel\Services\PluginUIService::class)->getPageComponents($page, $slot);
    }
}

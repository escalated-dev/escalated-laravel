<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Bridge\PluginBridge;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Service for plugins to register custom UI elements (pages, menus, widgets, etc.)
 */
class PluginUIService
{
    protected array $menuItems = [];

    protected array $customPages = [];

    protected array $dashboardWidgets = [];

    protected array $pageComponents = [];

    /**
     * Register a custom menu item.
     *
     * @param  array  $item  Menu item configuration
     */
    public function addMenuItem(array $item): void
    {
        $defaults = [
            'label' => 'Custom Item',
            'route' => null,
            'url' => null,
            'icon' => null,
            'permission' => null,
            'position' => 100,
            'parent' => null,
            'badge' => null,
            'target' => 'agent', // 'agent' or 'admin'
            'active_routes' => [],
            'submenu' => [],
        ];

        $this->menuItems[] = array_merge($defaults, $item);
    }

    /**
     * Register multiple menu items at once.
     *
     * @param  array  $items  Array of menu item configurations
     */
    public function addMenuItems(array $items): void
    {
        foreach ($items as $item) {
            $this->addMenuItem($item);
        }
    }

    /**
     * Add a submenu item to an existing menu item.
     *
     * @param  string  $parentLabel  The label of the parent menu item
     * @param  array  $submenuItem  Submenu item configuration
     */
    public function addSubmenuItem(string $parentLabel, array $submenuItem): void
    {
        $defaults = [
            'label' => 'Submenu Item',
            'route' => null,
            'url' => null,
            'icon' => null,
            'permission' => null,
            'active_routes' => [],
        ];

        $submenuItem = array_merge($defaults, $submenuItem);

        // Find the parent menu item and add the submenu item
        foreach ($this->menuItems as &$menuItem) {
            if ($menuItem['label'] === $parentLabel) {
                if (! isset($menuItem['submenu'])) {
                    $menuItem['submenu'] = [];
                }
                $menuItem['submenu'][] = $submenuItem;
                break;
            }
        }
    }

    /**
     * Get all registered menu items.
     */
    public function getMenuItems(?string $target = null): array
    {
        $items = $this->menuItems;

        if ($target !== null) {
            $items = array_filter($items, fn ($item) => $item['target'] === $target);
        }

        // Sort by position
        usort($items, fn ($a, $b) => $a['position'] <=> $b['position']);

        return array_values($items);
    }

    /**
     * Register a custom page route.
     *
     * @param  string  $route  Route name
     * @param  string  $component  Inertia component name
     * @param  array  $options  Additional options
     */
    public function registerPage(string $route, string $component, array $options = []): void
    {
        $defaults = [
            'middleware' => ['auth'],
            'permission' => null,
            'title' => 'Custom Page',
        ];

        $this->customPages[$route] = array_merge($defaults, [
            'route' => $route,
            'component' => $component,
        ], $options);
    }

    /**
     * Get all registered custom pages.
     */
    public function getCustomPages(): array
    {
        return $this->customPages;
    }

    /**
     * Register a dashboard widget.
     *
     * @param  array  $widget  Widget configuration
     */
    public function addDashboardWidget(array $widget): void
    {
        $defaults = [
            'id' => uniqid('escalated_widget_'),
            'title' => 'Custom Widget',
            'component' => null,
            'data' => [],
            'position' => 100,
            'width' => 'full', // 'full', 'half', 'third', 'quarter'
            'permission' => null,
            'target' => 'agent', // 'agent' or 'admin'
        ];

        $this->dashboardWidgets[] = array_merge($defaults, $widget);
    }

    /**
     * Get all registered dashboard widgets.
     */
    public function getDashboardWidgets(?string $target = null): array
    {
        $widgets = $this->dashboardWidgets;

        if ($target !== null) {
            $widgets = array_filter($widgets, fn ($w) => $w['target'] === $target);
        }

        // Sort by position
        usort($widgets, fn ($a, $b) => $a['position'] <=> $b['position']);

        return array_values($widgets);
    }

    /**
     * Register a component to be injected into an existing page.
     *
     * @param  string  $page  Page identifier (e.g., 'ticket.show', 'dashboard', 'ticket.index')
     * @param  string  $slot  Slot name (e.g., 'sidebar', 'header', 'footer', 'actions', 'tabs')
     * @param  array  $component  Component configuration
     */
    public function addPageComponent(string $page, string $slot, array $component): void
    {
        $defaults = [
            'component' => null,
            'data' => [],
            'position' => 100,
            'permission' => null,
        ];

        if (! isset($this->pageComponents[$page])) {
            $this->pageComponents[$page] = [];
        }

        if (! isset($this->pageComponents[$page][$slot])) {
            $this->pageComponents[$page][$slot] = [];
        }

        $this->pageComponents[$page][$slot][] = array_merge($defaults, $component);
    }

    /**
     * Get components registered for a specific page and slot.
     *
     * @param  string  $page  Page identifier
     * @param  string  $slot  Slot name
     */
    public function getPageComponents(string $page, string $slot): array
    {
        if (! isset($this->pageComponents[$page][$slot])) {
            return [];
        }

        $components = $this->pageComponents[$page][$slot];

        // Sort by position
        usort($components, fn ($a, $b) => $a['position'] <=> $b['position']);

        return $components;
    }

    /**
     * Get all components for a specific page.
     *
     * @param  string  $page  Page identifier
     */
    public function getAllPageComponents(string $page): array
    {
        return $this->pageComponents[$page] ?? [];
    }

    /**
     * `escalated.plugins` for the shared screens: the shape the frontend's
     * usePluginExtensions() reads (domain-model/plugin-contract.md), built
     * from what active native plugins registered and from the SDK plugins'
     * manifests. Items whose permission or capability the viewer lacks are
     * left out, and admin menu items go to admins only. Empty when there is
     * nothing to show.
     */
    public function sharedExtensions(mixed $user): array
    {
        $isAdmin = $user ? StaffAccess::isAdmin($user) : false;
        $allows = fn (?string $ability) => $ability === null || $ability === '' || ($user && $user->can($ability));

        $menuItems = [];
        $dashboardWidgets = [];
        $pageComponents = [];

        foreach ($this->getMenuItems() as $item) {
            $section = $item['section'] ?? ($item['target'] ?? 'admin');
            if (($section === 'admin' && ! $isAdmin) || ! $allows($item['permission'] ?? null)) {
                continue;
            }
            $menuItems[] = [
                'label' => $item['label'],
                'route' => $this->menuHref($item),
                'icon' => $item['icon'] ?? null,
                'position' => $item['priority'] ?? $item['position'] ?? 100,
                'section' => $section,
                'plugin' => $item['plugin'] ?? null,
                'badge' => $item['badge'] ?? null,
                'submenu' => $item['submenu'] ?? [],
            ];
        }

        foreach ($this->getDashboardWidgets() as $widget) {
            if ((($widget['section'] ?? $widget['target'] ?? 'agent') === 'admin' && ! $isAdmin) || ! $allows($widget['permission'] ?? null)) {
                continue;
            }
            $dashboardWidgets[] = [
                'id' => $widget['id'],
                'title' => $widget['label'] ?? $widget['title'] ?? null,
                'component' => $widget['component'] ?? null,
                'plugin' => $widget['plugin'] ?? null,
                'data' => $widget['props'] ?? $widget['data'] ?? [],
                'position' => $widget['priority'] ?? $widget['position'] ?? 100,
                'width' => $widget['width'] ?? 'full',
            ];
            if (isset($widget['badge'])) {
                $dashboardWidgets[array_key_last($dashboardWidgets)]['badge'] = $this->nativeBadge($widget['badge'], $user);
            }
        }

        foreach ($this->pageComponents as $page => $slots) {
            foreach (array_keys($slots) as $slot) {
                foreach ($this->getPageComponents($page, $slot) as $component) {
                    if (! $allows($component['permission'] ?? null)) {
                        continue;
                    }
                    $pageComponents["{$page}.{$slot}"][] = [
                        'plugin' => $component['plugin'] ?? null,
                        'component' => $component['component'] ?? null,
                        'data' => $component['props'] ?? $component['data'] ?? [],
                        'position' => $component['priority'] ?? $component['position'] ?? 100,
                    ];
                }
            }
        }

        $prefix = trim((string) config('escalated.routes.prefix', 'support'), '/');

        foreach ($this->sdkManifests() as $name => $manifest) {
            $slug = self::slug((string) $name);

            foreach ((array) ($manifest['pages'] ?? []) as $page) {
                $route = ltrim((string) ($page['route'] ?? ''), '/');
                $menu = $page['menu'] ?? null;
                if (! is_array($menu) || $route === '' || empty($page['component'])) {
                    continue;
                }
                $section = $menu['section'] ?? 'admin';
                if (($section === 'admin' && ! $isAdmin) || ! $allows($page['capability'] ?? null)) {
                    continue;
                }
                $menuItems[] = [
                    'label' => $menu['label'] ?? $page['component'],
                    // Where RouteRegistrar mounts the page.
                    'route' => '/'.ltrim("{$prefix}/admin/plugins/{$name}/{$route}", '/'),
                    'icon' => $menu['icon'] ?? null,
                    'position' => $menu['position'] ?? 100,
                    'section' => $section,
                    'plugin' => $slug,
                ];
            }

            foreach ((array) ($manifest['widgets'] ?? []) as $widget) {
                if (empty($widget['component']) || ! $allows($widget['capability'] ?? null)) {
                    continue;
                }
                $dashboardWidgets[] = [
                    'id' => "{$slug}.{$widget['component']}",
                    'title' => $widget['label'] ?? null,
                    'component' => $widget['component'],
                    'plugin' => $slug,
                    'position' => $widget['order'] ?? 100,
                    'width' => $widget['size'] ?? 'half',
                ];
                if (($widget['hasBadge'] ?? false) === true) {
                    // Asked as the viewer: the bridge's ctx acts as this request's user.
                    $dashboardWidgets[array_key_last($dashboardWidgets)]['badge'] = $this->sdkBadge((string) $name, (string) $widget['component']);
                }
            }

            foreach ((array) ($manifest['components'] ?? []) as $component) {
                if (empty($component['page']) || empty($component['slot']) || empty($component['component'])
                    || ! $allows($component['capability'] ?? null)) {
                    continue;
                }
                $pageComponents["{$component['page']}.{$component['slot']}"][] = [
                    'plugin' => $slug,
                    'component' => $component['component'],
                    'data' => $component['props'] ?? [],
                    'position' => $component['order'] ?? 100,
                ];
            }
        }

        if ($menuItems === [] && $dashboardWidgets === [] && $pageComponents === []) {
            return [];
        }

        usort($menuItems, fn ($a, $b) => $a['position'] <=> $b['position']);
        usort($dashboardWidgets, fn ($a, $b) => $a['position'] <=> $b['position']);
        foreach ($pageComponents as $key => $list) {
            usort($list, fn ($a, $b) => $a['position'] <=> $b['position']);
            $pageComponents[$key] = $list;
        }

        return ['menuItems' => $menuItems, 'pageComponents' => $pageComponents, 'dashboardWidgets' => $dashboardWidgets];
    }

    /**
     * The contract's URL-safe slug for an SDK plugin name:
     * `@escalated-dev/plugin-slack` is `escalated-dev--plugin-slack`.
     */
    public static function slug(string $name): string
    {
        $slug = str_replace('/', '--', ltrim($name, '@'));

        return (string) preg_replace('/[^A-Za-z0-9_-]/', '-', $slug);
    }

    private function menuHref(array $item): ?string
    {
        if (! empty($item['url'])) {
            return $item['url'];
        }
        $route = $item['route'] ?? null;
        if (is_string($route) && $route !== '' && Route::has($route)) {
            return route($route, [], false);
        }

        return $route;
    }

    /**
     * A native widget's `badge`: a callable is called with the viewer; a
     * number or string is used as is. Anything else, or a throw, is null.
     */
    private function nativeBadge(mixed $badge, mixed $user): int|float|string|null
    {
        try {
            if (is_object($badge) && is_callable($badge)) {
                $badge = $badge($user);
            }
        } catch (\Throwable $e) {
            Log::warning('Escalated plugins: widget badge failed: '.$e->getMessage());

            return null;
        }

        return is_int($badge) || is_float($badge) || is_string($badge) ? $badge : null;
    }

    /**
     * An SDK widget's `badge`, asked from the runtime within 5 s; null when it fails.
     */
    private function sdkBadge(string $plugin, string $component): int|float|string|null
    {
        try {
            return app(PluginBridge::class)->badge($plugin, $component);
        } catch (\Throwable $e) {
            Log::warning("Escalated plugins: {$plugin} badge '{$component}' failed: ".$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<string, array>
     */
    private function sdkManifests(): array
    {
        try {
            if (! config('escalated.plugins.sdk_enabled', true) || ! app()->bound(PluginBridge::class)) {
                return [];
            }
            $bridge = app(PluginBridge::class);

            return $bridge->isBooted() ? $bridge->getManifests() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Clear all registered UI elements (useful for testing).
     */
    public function clear(): void
    {
        $this->menuItems = [];
        $this->customPages = [];
        $this->dashboardWidgets = [];
        $this->pageComponents = [];
    }
}

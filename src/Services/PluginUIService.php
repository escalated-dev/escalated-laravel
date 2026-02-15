<?php

namespace Escalated\Laravel\Services;

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
     * @param array $item Menu item configuration
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
     * @param array $items Array of menu item configurations
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
     * @param string $parentLabel The label of the parent menu item
     * @param array $submenuItem Submenu item configuration
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
     * @param string $route Route name
     * @param string $component Inertia component name
     * @param array $options Additional options
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
     * @param array $widget Widget configuration
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
     * @param string $page Page identifier (e.g., 'ticket.show', 'dashboard', 'ticket.index')
     * @param string $slot Slot name (e.g., 'sidebar', 'header', 'footer', 'actions', 'tabs')
     * @param array $component Component configuration
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
     * @param string $page Page identifier
     * @param string $slot Slot name
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
     * @param string $page Page identifier
     */
    public function getAllPageComponents(string $page): array
    {
        return $this->pageComponents[$page] ?? [];
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

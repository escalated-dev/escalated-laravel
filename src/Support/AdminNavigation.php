<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Http\Middleware\CheckPermission;
use Escalated\Laravel\Services\PluginUIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Escalated's admin menu as data, so a host can list it in its own sidebar
 * (ADR 2026-11-05-embeddable-admin). Shared as `escalated.navigation` while
 * the admin is embedded, and available as Escalated::adminNavigation() for
 * server-rendered layouts.
 *
 * Filtered server-side so a host sidebar never links to a screen that would
 * answer 403 or 404: the admin gate (unless the host replaced it), routes that
 * are not registered, feature switches and, while roles & permissions is on,
 * each route's permission. Plugin admin menu items are merged in.
 */
final class AdminNavigation
{
    /**
     * Core items: key (also the icon key), label, route name, position. Labels
     * and positions match the standalone admin sidebar in the shared frontend.
     */
    public const ITEMS = [
        ['reports', 'Reports', 'escalated.admin.reports', 10],
        ['tickets', 'Tickets', 'escalated.admin.tickets.index', 20],
        ['departments', 'Departments', 'escalated.admin.departments.index', 30],
        ['sla-policies', 'SLA Policies', 'escalated.admin.sla-policies.index', 40],
        ['knowledge-base', 'Knowledge Base', 'escalated.admin.kb-articles.index', 45],
        ['escalation-rules', 'Escalation Rules', 'escalated.admin.escalation-rules.index', 50],
        ['workflows', 'Workflows', 'escalated.admin.workflows.index', 53],
        ['automations', 'Automations', 'escalated.admin.automations.index', 53.5],
        ['webhooks', 'Webhooks', 'escalated.admin.webhooks.index', 54],
        ['tags', 'Tags', 'escalated.admin.tags.index', 60],
        ['custom-fields', 'Custom Fields', 'escalated.admin.custom-fields.index', 62],
        ['statuses', 'Statuses', 'escalated.admin.statuses.index', 64],
        ['canned-responses', 'Canned Responses', 'escalated.admin.canned-responses.index', 70],
        ['macros', 'Macros', 'escalated.admin.macros.index', 80],
        ['newsletters', 'Newsletters', 'escalated.admin.newsletters.index', 81],
        ['skills', 'Skills', 'escalated.admin.skills.index', 82],
        ['users', 'Users', 'escalated.admin.users.index', 83],
        ['roles', 'Roles', 'escalated.admin.roles.index', 84],
        ['capacity', 'Capacity', 'escalated.admin.capacity.index', 85],
        ['business-hours', 'Business Hours', 'escalated.admin.business-hours.index', 86],
        ['audit-log', 'Audit Log', 'escalated.admin.audit-log', 88],
        ['api-tokens', 'API Tokens', 'escalated.admin.api-tokens.index', 90],
        ['custom-objects', 'Custom Objects', 'escalated.admin.custom-objects.index', 92],
        ['plugins', 'Plugins', 'escalated.admin.plugins.index', 95],
        ['import', 'Import', 'escalated.admin.import.index', 97],
        ['settings', 'Settings', 'escalated.admin.settings', 100],
    ];

    /**
     * @return list<array{key: string, label: string, route: ?string, url: string, icon: ?string,
     *                     position: int|float, active: bool, badge: mixed, plugin: ?string, children: array}>
     */
    public static function for(mixed $user, ?Request $request = null): array
    {
        if (! $user) {
            return [];
        }

        if (! EmbedConfig::replacesAdminGate() && ! StaffAccess::isAdmin($user)) {
            return [];
        }

        $request ??= request();
        $path = '/'.ltrim($request->path(), '/');
        $granted = AccessFeatures::rolesPermissions() ? CheckPermission::userPermissions($user->getKey()) : null;
        $neutral = Request::create('/');

        $items = [];
        foreach (self::ITEMS as [$key, $label, $route, $position]) {
            if (! Route::has($route)) {
                continue;
            }
            if ($key === 'roles' && ! AccessFeatures::rolesPermissions()) {
                continue;
            }
            if ($granted !== null) {
                $required = RoutePermissions::requiredFor($route, $neutral);
                if ($required === null || array_diff($required, $granted) !== []) {
                    continue;
                }
            }

            $url = route($route, [], false);
            $items[] = self::item($key, $label, $route, $url, $key, $position, $path);
        }

        foreach (self::pluginItems($user) as $index => $plugin) {
            $url = $plugin['route'] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }
            $item = self::item(
                'plugin:'.($plugin['plugin'] ?? 'native').':'.$index,
                (string) ($plugin['label'] ?? ''),
                null,
                $url,
                is_string($plugin['icon'] ?? null) ? $plugin['icon'] : null,
                $plugin['position'] ?? 100,
                $path,
            );
            $item['badge'] = $plugin['badge'] ?? null;
            $item['plugin'] = $plugin['plugin'] ?? null;
            $item['children'] = array_values(array_map(fn ($child) => [
                'label' => (string) ($child['label'] ?? ''),
                'url' => (string) ($child['url'] ?? $child['route'] ?? ''),
                'icon' => $child['icon'] ?? null,
            ], (array) ($plugin['submenu'] ?? [])));
            $items[] = $item;
        }

        // Stable sort by position, as the standalone sidebar orders them.
        $order = array_keys($items);
        array_multisort(array_column($items, 'position'), SORT_ASC, SORT_NUMERIC, $order, SORT_ASC, $items);

        return array_values($items);
    }

    private static function item(string $key, string $label, ?string $route, string $url, ?string $icon, int|float $position, string $path): array
    {
        $target = parse_url($url, PHP_URL_PATH) ?: $url;

        return [
            'key' => $key,
            'label' => $label,
            'route' => $route,
            'url' => $url,
            'icon' => $icon,
            'position' => $position,
            'active' => $path === $target || str_starts_with($path, rtrim($target, '/').'/'),
            'badge' => null,
            'plugin' => null,
            'children' => [],
        ];
    }

    /** Plugin menu items for the admin section, already filtered by the plugin contract. */
    private static function pluginItems(mixed $user): array
    {
        if (! config('escalated.plugins.enabled', true)) {
            return [];
        }

        try {
            $items = app(PluginUIService::class)->sharedExtensions($user)['menuItems'] ?? [];
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter($items, fn ($item) => ($item['section'] ?? 'admin') === 'admin'));
    }
}

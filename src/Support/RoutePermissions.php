<?php

namespace Escalated\Laravel\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The permission each admin, agent and staff API route requires while roles &
 * permissions is enforced (ADR 2026-10-29-configurable-access-features).
 *
 * Keys are route names (or Str::is patterns); the first match wins, so exact
 * names come before patterns. A route that matches nothing is refused by
 * EnforceRoutePermissions, and a test enumerates the router so a new route
 * cannot ship without an entry here. Ports copy this map.
 *
 * Every slug is one PermissionSeeder already seeds; none are invented here.
 */
final class RoutePermissions
{
    /** Statuses whose change needs ticket.close as well as ticket.edit. */
    public const CLOSING_STATUSES = ['resolved', 'closed', 'reopened'];

    /** Bulk actions => the permission each needs. */
    public const BULK_ACTIONS = [
        'status' => ['ticket.edit'],
        'priority' => ['ticket.edit'],
        'assign' => ['ticket.assign'],
        'tags' => ['ticket.edit'],
        'department' => ['ticket.edit'],
        'delete' => ['ticket.delete'],
    ];

    /**
     * Route name => required permission slugs. An empty list means "staff,
     * no particular permission" and is reserved for personal screens.
     *
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        return [
            // ---- Personal ------------------------------------------------
            'escalated.admin.two-factor.*' => [],

            // ---- Tickets (admin and agent panels, staff API) --------------
            'escalated.agent.dashboard' => ['ticket.view'],
            'escalated.api.dashboard' => ['ticket.view'],
            'escalated.api.auth.validate' => [],
            'escalated.api.realtime' => ['ticket.view'],
            'escalated.admin.agents.search' => ['ticket.view'],
            'escalated.api.agents' => ['ticket.view'],
            'escalated.api.departments' => ['ticket.view'],
            'escalated.api.tags' => ['ticket.view'],
            'escalated.api.canned-responses' => ['ticket.view'],
            'escalated.api.macros' => ['macro.view'],

            'escalated.*.tickets.index' => ['ticket.view'],
            'escalated.*.tickets.show' => ['ticket.view'],
            'escalated.admin.tickets.merge-search' => ['ticket.view'],
            'escalated.*.tickets.follow' => ['ticket.view'],
            'escalated.*.tickets.presence' => ['ticket.view'],
            'escalated.*.tickets.typing' => ['ticket.view'],
            'escalated.admin.tickets.links.index' => ['ticket.view'],
            'escalated.admin.tickets.side-conversations.index' => ['ticket.view'],
            'escalated.api.tickets.subjects.index' => ['ticket.view'],

            'escalated.api.tickets.store' => ['ticket.create'],
            'escalated.admin.tickets.split' => ['ticket.create'],
            'escalated.*.tickets.update' => ['ticket.edit'],
            'escalated.*.tickets.status' => ['ticket.edit'],
            'escalated.*.tickets.priority' => ['ticket.edit'],
            'escalated.*.tickets.tags' => ['ticket.edit'],
            'escalated.*.tickets.department' => ['ticket.edit'],
            'escalated.*.tickets.pin' => ['ticket.edit'],
            'escalated.*.tickets.custom-action' => ['ticket.edit'],
            'escalated.admin.tickets.snooze' => ['ticket.edit'],
            'escalated.admin.tickets.unsnooze' => ['ticket.edit'],
            'escalated.admin.tickets.subjects.*' => ['ticket.edit'],
            'escalated.api.tickets.subjects.update' => ['ticket.edit'],
            'escalated.admin.tickets.links.store' => ['ticket.edit'],
            'escalated.admin.tickets.links.destroy' => ['ticket.edit'],
            'escalated.*.tickets.assign' => ['ticket.assign'],
            'escalated.admin.tickets.merge' => ['ticket.merge'],
            'escalated.api.tickets.destroy' => ['ticket.delete'],
            'escalated.*.tickets.macro' => ['macro.view', 'ticket.edit'],
            // Bulk: per action, see requiredFor().
            'escalated.*.tickets.bulk' => ['ticket.view'],

            'escalated.*.tickets.reply' => ['reply.create'],
            'escalated.*.tickets.note' => ['reply.create_internal'],
            'escalated.admin.tickets.side-conversations.*' => ['reply.create'],

            'escalated.admin.saved-views.*' => ['ticket.view'],

            // ---- Live chat -------------------------------------------------
            'escalated.admin.chat.routing-rules.index' => ['settings.view'],
            'escalated.admin.chat.routing-rules.*' => ['settings.manage'],
            'escalated.admin.chat.settings' => ['settings.view'],
            'escalated.admin.chat.settings.update' => ['settings.manage'],
            'escalated.admin.chat.active' => ['ticket.view'],
            'escalated.admin.chat.queue' => ['ticket.view'],
            'escalated.admin.chat.*' => ['reply.create'],

            // ---- Reports -----------------------------------------------------
            'escalated.admin.reports.export' => ['report.export'],
            'escalated.api.reports.export' => ['report.export'],
            'escalated.admin.reports' => ['report.view'],
            'escalated.admin.reports.*' => ['report.view'],
            'escalated.api.reports.*' => ['report.view'],

            // ---- Knowledge base ----------------------------------------------
            'escalated.admin.kb-articles.index' => ['kb.view'],
            'escalated.admin.kb-articles.create' => ['kb.create'],
            'escalated.admin.kb-articles.store' => ['kb.create'],
            'escalated.admin.kb-articles.edit' => ['kb.edit'],
            'escalated.admin.kb-articles.update' => ['kb.edit'],
            'escalated.admin.kb-articles.destroy' => ['kb.delete'],
            'escalated.admin.kb-categories.index' => ['kb.view'],
            'escalated.admin.kb-categories.store' => ['kb.edit'],
            'escalated.admin.kb-categories.update' => ['kb.edit'],
            'escalated.admin.kb-categories.destroy' => ['kb.delete'],

            // ---- Departments ---------------------------------------------------
            'escalated.admin.departments.index' => ['department.view'],
            'escalated.admin.departments.create' => ['department.create'],
            'escalated.admin.departments.store' => ['department.create'],
            'escalated.admin.departments.edit' => ['department.edit'],
            'escalated.admin.departments.update' => ['department.edit'],
            'escalated.admin.departments.destroy' => ['department.delete'],

            // ---- Admin resources: index => *.view, everything else => *.manage
            ...self::resource('sla-policies', 'sla'),
            ...self::resource('business-hours', 'sla'),
            ...self::resource('escalation-rules', 'escalation'),
            ...self::resource('automations', 'automation'),
            ...self::resource('workflows', 'automation', ['logs', 'workflow-logs']),
            ...self::resource('tags', 'tag'),
            ...self::resource('custom-fields', 'custom_field'),
            ...self::resource('statuses', 'settings'),
            ...self::resource('roles', 'role'),
            ...self::resource('skills', 'user'),
            ...self::resource('capacity', 'user'),
            ...self::resource('webhooks', 'webhook', ['deliveries']),
            ...self::resource('api-tokens', 'api_token'),
            ...self::resource('plugins', 'plugin'),
            ...self::resource('canned-responses', 'macro'),

            // Macros: anyone who may create one may save it; shared edits manage.
            'escalated.admin.macros.index' => ['macro.view'],
            'escalated.admin.macros.store' => ['macro.create'],
            'escalated.admin.macros.*' => ['macro.manage'],

            // Custom objects: schemas manage, records data.
            'escalated.admin.custom-objects.records' => ['custom_object.view'],
            'escalated.admin.custom-objects.records.*' => ['custom_object.data'],
            ...self::resource('custom-objects', 'custom_object'),

            // Users
            'escalated.admin.users.index' => ['user.view'],
            'escalated.admin.users.role' => ['user.manage'],
            'escalated.admin.users.roles' => ['role.manage'],

            'escalated.admin.audit-log' => ['audit.view'],

            // ---- Settings ------------------------------------------------------
            'escalated.admin.settings' => ['settings.view'],
            'escalated.admin.settings.update' => ['settings.manage'],
            'escalated.admin.settings.database.test' => ['settings.manage'],
            'escalated.admin.settings.*.update' => ['settings.manage'],
            'escalated.admin.settings.*' => ['settings.view'],
            'escalated.admin.import.*' => ['settings.manage'],

            // ---- Newsletters (ADR 2026-05-19-newsletter-system) ----------------
            'escalated.admin.newsletters.*' => ['newsletters.manage'],
        ];
    }

    public static function isMapped(string $routeName): bool
    {
        return self::lookup($routeName) !== null;
    }

    /**
     * The slugs a request to the named route needs, or null when the route is
     * not mapped (the caller refuses it).
     *
     * @return list<string>|null
     */
    public static function requiredFor(string $routeName, Request $request): ?array
    {
        $required = self::lookup($routeName);

        if ($required === null) {
            return null;
        }

        if (Str::is('escalated.*.tickets.bulk', $routeName)) {
            $action = (string) $request->input('action');
            $required = [...$required, ...(self::BULK_ACTIONS[$action] ?? [])];
            if ($action === 'status') {
                $required = [...$required, ...self::closing($request->input('value'))];
            }
        } elseif ($request->has('status') && in_array('ticket.edit', $required, true)) {
            $required = [...$required, ...self::closing($request->input('status'))];
        }

        return array_values(array_unique($required));
    }

    /** @return list<string>|null */
    private static function lookup(string $routeName): ?array
    {
        foreach (self::map() as $pattern => $slugs) {
            if ($pattern === $routeName || Str::is($pattern, $routeName)) {
                return $slugs;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function closing(mixed $status): array
    {
        return is_string($status) && in_array($status, self::CLOSING_STATUSES, true) ? ['ticket.close'] : [];
    }

    /**
     * index (and any extra read actions) => {group}.view; anything else under
     * the resource => {group}.manage.
     *
     * @param  list<string>  $extraReads
     * @return array<string, list<string>>
     */
    private static function resource(string $name, string $group, array $extraReads = []): array
    {
        $entries = ["escalated.admin.{$name}.index" => ["{$group}.view"]];

        foreach ($extraReads as $read) {
            $entries["escalated.admin.{$name}.{$read}"] = ["{$group}.view"];
        }

        $entries["escalated.admin.{$name}.*"] = ["{$group}.manage"];

        return $entries;
    }
}

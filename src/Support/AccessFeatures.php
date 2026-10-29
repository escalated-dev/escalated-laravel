<?php

namespace Escalated\Laravel\Support;

/**
 * The install-level access switches (ADR 2026-10-29-configurable-access-features).
 *
 * Read from configuration only, never from the settings table: each one
 * changes who may sign in or what they may do, so it must not be something a
 * stored setting can flip.
 */
final class AccessFeatures
{
    public static function rolesPermissions(): bool
    {
        return (bool) config('escalated.access.roles_permissions', false);
    }

    /** @return array<string, bool> the `features` shared with every page */
    public static function shared(): array
    {
        return [
            'roles_permissions' => self::rolesPermissions(),
        ];
    }
}

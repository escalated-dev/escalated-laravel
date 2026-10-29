<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Support\AccessFeatures;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 404 while a feature switched by a runtime admin setting is off.
 *
 * Guest tickets, the support widget and live chat are turned on and off in
 * Admin > Settings, so their routes stay registered (route caching cannot
 * see the database) and this middleware hides them while the feature is off.
 * The access features (roles & permissions, two-factor) are config switches handled the
 * same way, so a cached route list follows the switch too.
 *
 * Usage: EnsureFeatureEnabled::class.':widget'
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(self::enabled($feature), 404);

        return $next($request);
    }

    public static function enabled(string $feature): bool
    {
        return match ($feature) {
            'guest' => EscalatedSettings::guestTicketsEnabled(),
            'widget' => EscalatedSettings::getBool('widget_enabled', false),
            'chat' => EscalatedSettings::getBool('chat_enabled', false),
            // Install-level switches (config only), ADR 2026-10-29-configurable-access-features.
            'roles_permissions' => AccessFeatures::rolesPermissions(),
            'two_factor' => AccessFeatures::twoFactor(),
            default => throw new InvalidArgumentException("Unknown Escalated feature [{$feature}]."),
        };
    }
}

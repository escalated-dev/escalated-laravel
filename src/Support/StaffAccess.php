<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Staff authorization shared by middleware, policies, channels and services.
 *
 * Single-tenant installations use the host's `escalated-agent` / `escalated-admin`
 * gates unchanged. In tenant mode the gate must also be backed by current
 * membership and a tenant-local seat from the host resolver, so a global host
 * flag never grants staff access to an account where the user is a customer.
 */
final class StaffAccess
{
    public static function isAgent(mixed $user): bool
    {
        return self::allows($user, 'agent');
    }

    public static function isAdmin(mixed $user): bool
    {
        return self::allows($user, 'admin');
    }

    /** Agent or admin: may use agent surfaces. */
    public static function isStaff(mixed $user): bool
    {
        return self::isAgent($user) || self::isAdmin($user);
    }

    /** @param  'agent'|'admin'  $seat */
    private static function allows(mixed $user, string $seat): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        $gate = config("escalated.authorization.{$seat}_gate", "escalated-{$seat}");
        $context = app(TenantContext::class);

        return Gate::forUser($user)->allows($gate)
            && (! $context->enabled() || ($user instanceof Model && $context->hasStaffSeat($user, $seat)));
    }
}

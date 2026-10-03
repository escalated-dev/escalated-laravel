<?php

namespace Escalated\Laravel\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** The host owns account selection, membership and host-entity visibility. */
interface TenantResolver
{
    public function resolve(Request $request): int|string|null;

    public function canAccess(Model $user, string $tenantId): bool;

    public function canReference(Model $model, string $tenantId): bool;

    /** Constrain host identity/subject discovery on that model's own connection. */
    public function scope(Builder $query, string $tenantId): void;

    /**
     * Tenant-local staff seat. In tenant mode the package's agent surfaces
     * require this in addition to the host `escalated-agent` gate, so a global
     * host flag never makes a customer of this account its agent. Return true
     * for account admins who also answer tickets.
     */
    public function isAgent(Model $user, string $tenantId): bool;

    /** Tenant-local administrator seat, required with the `escalated-admin` gate. */
    public function isAdmin(Model $user, string $tenantId): bool;

    /** Platform access is separate from being an administrator of one account. */
    public function isPlatformAdmin(Model $user): bool;
}

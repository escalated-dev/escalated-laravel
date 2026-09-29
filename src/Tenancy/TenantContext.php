<?php

namespace Escalated\Laravel\Tenancy;

use Closure;
use Escalated\Laravel\Contracts\TenantResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class TenantContext
{
    private ?string $tenantId = null;

    public function enabled(): bool
    {
        return (bool) config('escalated.tenancy.enabled', false);
    }

    public function current(): ?string
    {
        return $this->tenantId;
    }

    public function id(): string
    {
        if (! $this->enabled()) {
            return '';
        }

        if (config('escalated.mode', 'self-hosted') !== 'self-hosted') {
            throw new LogicException('Escalated tenancy requires self-hosted mode. Cloud tenant propagation is not supported.');
        }

        return $this->tenantId ?? throw new AuthorizationException('No Escalated tenant has been resolved.');
    }

    public function set(int|string|null $tenantId): void
    {
        if ($tenantId !== null) {
            $tenantId = (string) $tenantId;
            if ($tenantId === '' || $tenantId !== trim($tenantId) || strlen($tenantId) > 128 || preg_match('/[\x00-\x1F\x7F]/', $tenantId)) {
                throw new AuthorizationException('Invalid Escalated tenant identifier.');
            }
        }

        $this->tenantId = $tenantId;
    }

    /** Trusted host/worker entry point. Always restores the caller's context. */
    public function run(int|string $tenantId, Closure $callback): mixed
    {
        $previous = $this->tenantId;
        try {
            $this->set($tenantId);
            $this->id();

            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }

    public function resolver(): TenantResolver
    {
        return app(TenantResolver::class);
    }

    public function canAccess(?Model $user): bool
    {
        return ! $this->enabled() || ($user !== null && $this->tenantId !== null
            && $this->resolver()->canAccess($user, $this->id()));
    }

    public function owns(Model $model): bool
    {
        return ! $this->enabled() || ($this->tenantId !== null
            && (string) $model->getAttribute('tenant_id') === $this->id()
            && (! $model->exists || $model->getRawOriginal('tenant_id') === $this->id()));
    }

    public function assertOwns(Model $model): void
    {
        if (! $this->owns($model)) {
            throw new AuthorizationException('The record does not belong to the current Escalated tenant.');
        }
    }

    public function scopeHost(Builder $query): Builder
    {
        if ($this->enabled()) {
            $this->resolver()->scope($query, $this->id());
        }

        return $query;
    }
}

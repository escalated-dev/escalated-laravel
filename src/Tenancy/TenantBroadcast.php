<?php

namespace Escalated\Laravel\Tenancy;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class TenantBroadcast
{
    public static function prefix(): string
    {
        $context = app(TenantContext::class);

        return $context->enabled() ? 'escalated.tenants.'.hash('sha256', $context->id()) : 'escalated';
    }

    public static function channel(string $suffix, Model ...$records): string
    {
        foreach ($records as $record) {
            app(TenantContext::class)->assertOwns($record);
        }

        return static::prefix().'.'.$suffix;
    }

    /** Works on the host's broadcast auth route without replacing that route. */
    public static function authorize($user, ?string $namespace, Closure $authorize): mixed
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            return $namespace === null ? $authorize() : false;
        }
        if (! $user instanceof Model || $namespace === null) {
            return false;
        }
        $previous = $context->current();
        try {
            // Never infer tenant authority from an attacker-controlled channel.
            $context->set(null);
            $tenant = $context->resolver()->resolve(request());
            if ($tenant === null) {
                return false;
            }

            return $context->run($tenant, function () use ($context, $user, $namespace, $authorize) {
                if (! hash_equals(hash('sha256', $context->id()), $namespace) || ! $context->canAccess($user)) {
                    return false;
                }

                return $authorize();
            });
        } catch (AuthorizationException) {
            return false;
        } finally {
            $context->set($previous);
        }
    }
}

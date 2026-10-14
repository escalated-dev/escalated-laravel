<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Contracts\TicketAccountResolver;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tenancy\TenantReferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class TicketAccounts
{
    public static function filterRules(): array
    {
        return [
            'account_type' => ['nullable', 'required_with:account_id', 'string', 'max:255'],
            'account_id' => ['nullable', 'required_with:account_type', 'string', 'max:255'],
        ];
    }

    /** Normalize the complete morph identity used by rule conditions. */
    public static function identity(mixed $value): ?array
    {
        if (! is_array($value) || ! is_string($value['type'] ?? null) || $value['type'] === ''
            || (! is_string($value['id'] ?? null) && ! is_int($value['id'] ?? null)) || (string) $value['id'] === '') {
            return null;
        }

        return ['type' => $value['type'], 'id' => (string) $value['id']];
    }

    public static function constrain(Builder $query, mixed $value): Builder
    {
        $identity = self::identity($value);

        if (! $identity) {
            return $query->whereRaw('1 = 0');
        }

        self::whereIdentity($query, 'account_type', $identity['type']);

        return self::whereIdentity($query, 'account_id', $identity['id']);
    }

    /** Preserve opaque identities even on MySQL PAD SPACE collations. */
    public static function whereIdentity(Builder $query, string $field, mixed $value): Builder
    {
        if (! in_array($field, ['account_type', 'account_id'], true)) {
            throw new \InvalidArgumentException('Unknown account identity field.');
        }
        $column = $query->qualifyColumn($field);
        $query->where($column, $value);
        if ($value !== null && in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $wrapped = $query->getQuery()->getGrammar()->wrap($column);
            $query->whereRaw("CAST({$wrapped} AS BINARY) = CAST(? AS BINARY)", [(string) $value]);
        }

        return $query;
    }

    public static function resolve(?Model $source): ?Model
    {
        if (! config('escalated.accounts.enabled', false) || ! $source) {
            return null;
        }
        $resolver = config('escalated.accounts.resolver') ?? TicketAccountResolver::class;
        if (! app()->bound($resolver) && ! class_exists($resolver)) {
            throw new LogicException('Ticket accounts require a TicketAccountResolver binding or configured resolver class.');
        }
        $resolver = app($resolver);
        if (! $resolver instanceof TicketAccountResolver) {
            throw new LogicException('The account resolver must implement TicketAccountResolver.');
        }
        $account = $resolver->resolve($source);
        if ($account) {
            if (! $account->exists || $account->getKey() === null) {
                throw new LogicException('A ticket account must be a persisted model.');
            }
            if (app(TenantContext::class)->enabled()) {
                app(TenantReferences::class)->assertReference($account, $account->getKey());
            }
        }

        return $account;
    }
}

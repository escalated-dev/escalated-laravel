<?php

namespace Escalated\Laravel;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

class Escalated
{
    /**
     * The user model class.
     */
    public static string $userModel = 'App\\Models\\User';

    /**
     * The user display column.
     */
    public static string $userDisplayColumn = 'name';

    /**
     * Memoized per-process cache of `Schema::hasColumn` lookups so we
     * don't round-trip the information_schema on every search query.
     *
     * @var array<string,bool>
     */
    private static array $columnExistsCache = [];

    /**
     * Set the user model class.
     */
    public static function useUserModel(string $model): void
    {
        static::$userModel = $model;
    }

    /**
     * Get the user model class.
     */
    public static function userModel(): string
    {
        return config('escalated.user_model', static::$userModel);
    }

    /**
     * Create a new user model instance.
     */
    public static function newUserModel(): mixed
    {
        $model = static::userModel();

        return new $model;
    }

    /**
     * Set the user display column.
     */
    public static function useUserDisplayColumn(string $column): void
    {
        static::$userDisplayColumn = $column;
    }

    /**
     * Get the user display column.
     */
    public static function userDisplayColumn(): string
    {
        return config('escalated.user_display_column', static::$userDisplayColumn);
    }

    /**
     * Get users as id => display_column array for select options.
     *
     * Falls back to `email` when the configured display column doesn't
     * exist on the host's `users` table (e.g. hosts that store split
     * `first_name`/`last_name` columns instead of a single `name`).
     */
    public static function userOptions(): array
    {
        $model = static::userModel();
        $column = static::userSearchableDisplayColumn();

        return $model::pluck($column, 'id')->toArray();
    }

    /**
     * Resolve the effective display column to query against.
     *
     * Returns the configured `user_display_column` when that column
     * exists on the user model's table, otherwise `'email'`. This lets
     * hosts that don't have a `name` column (the default) still get
     * sensible search/pluck behavior without changing any code.
     */
    public static function userSearchableDisplayColumn(): string
    {
        $configured = static::userDisplayColumn();
        if (static::userHasColumn($configured)) {
            return $configured;
        }

        return 'email';
    }

    /**
     * Apply a requester search term to a user-model query.
     *
     * Searches the configured display column (typically `name`) and
     * `email`. When the display column doesn't exist on the host's
     * users table, silently degrades to an email-only search — this is
     * the fallback contract documented in issue #63.
     *
     * @param  Builder  $query  The inner query (an already-scoped user query,
     *                          usually from `whereHas('requester', ...)`)
     */
    public static function applyUserSearch(Builder $query, string $term): Builder
    {
        $displayColumn = static::userDisplayColumn();
        $hasDisplay = static::userHasColumn($displayColumn);

        if ($hasDisplay && $displayColumn !== 'email') {
            return $query->where($displayColumn, 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        }

        // Display column is missing (or is literally 'email'); search email only.
        return $query->where('email', 'like', "%{$term}%");
    }

    /**
     * Check whether the configured user model's table exposes the given
     * column. Results are memoized per process because Schema lookups
     * hit information_schema.
     */
    protected static function userHasColumn(string $column): bool
    {
        $model = static::userModel();
        $table = (new $model)->getTable();
        $key = "{$table}.{$column}";

        if (! array_key_exists($key, static::$columnExistsCache)) {
            static::$columnExistsCache[$key] = Schema::hasColumn($table, $column);
        }

        return static::$columnExistsCache[$key];
    }

    /**
     * Clear the schema cache. Intended for use in tests that recreate
     * the users table with different column sets.
     */
    public static function flushColumnCache(): void
    {
        static::$columnExistsCache = [];
    }

    /**
     * Get the table prefix.
     */
    public static function tablePrefix(): string
    {
        return config('escalated.table_prefix', 'escalated_');
    }

    /**
     * Get the prefixed table name.
     */
    public static function table(string $name): string
    {
        return static::tablePrefix().$name;
    }
}

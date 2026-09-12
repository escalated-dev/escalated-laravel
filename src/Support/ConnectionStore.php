<?php

namespace Escalated\Laravel\Support;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

/**
 * Where an admin-chosen database connection is persisted.
 *
 * Deliberately a file, not a settings row. The name of the connection cannot
 * live in the database it selects: reading it would require already knowing
 * it, and an admin who pointed Escalated at an empty database would lose the
 * very row that says how to get back. Every other Escalated setting belongs in
 * `escalated_settings`; this one cannot.
 *
 * The file is small, written atomically, and absent by default -- an
 * installation that never uses the admin screen has no file and resolves
 * `config('escalated.connection')` exactly as before.
 */
class ConnectionStore
{
    /**
     * Per-process memo. Resolving the connection happens on virtually every
     * query path, and a filesystem stat on each one would be a poor trade for
     * a value that changes at most once in a deployment's life.
     *
     * `false` means "not yet read"; null means "read, and there is no override".
     */
    protected static string|null|false $memo = false;

    public static function path(): string
    {
        return storage_path('app/escalated/connection.php');
    }

    /**
     * The stored connection name, or null when the admin has chosen none.
     */
    public static function get(): ?string
    {
        if (static::$memo !== false) {
            return static::$memo;
        }

        $path = static::path();

        if (! File::exists($path)) {
            return static::$memo = null;
        }

        // Deliberately narrow: a require() of a file inside storage/ is only
        // as safe as storage/, so the value is validated as a plain connection
        // name before it is ever handed to the connection resolver.
        $stored = rescue(fn () => require $path, null, report: false);

        if (! is_string($stored) || ! static::isValidName($stored)) {
            return static::$memo = null;
        }

        return static::$memo = $stored;
    }

    /**
     * Persist the admin's choice, or clear it with null so the package falls
     * back to `config('escalated.connection')`.
     *
     * @throws FileNotFoundException when storage is not writable
     */
    public static function put(?string $connection): void
    {
        $path = static::path();

        if ($connection === null || $connection === '') {
            File::delete($path);
            static::$memo = null;

            return;
        }

        if (! static::isValidName($connection)) {
            throw new \InvalidArgumentException("[{$connection}] is not a valid connection name.");
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php\n\nreturn ".var_export($connection, true).";\n", true);

        static::$memo = $connection;
    }

    /**
     * Drop the memo. Called after a write, and by tests, because the value is
     * cached for the life of the process.
     */
    public static function flush(): void
    {
        static::$memo = false;
    }

    /**
     * Connection names are used to index config and are interpolated nowhere,
     * but the value comes off disk, so it is constrained to the shape Laravel
     * itself allows for a `database.connections` key.
     */
    protected static function isValidName(string $name): bool
    {
        return $name !== '' && preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $name) === 1;
    }
}

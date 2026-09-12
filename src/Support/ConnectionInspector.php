<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Escalated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Describes the database connections Escalated could live on.
 *
 * This exists so the admin screen can refuse a bad switch instead of
 * performing one. Pointing Escalated at a database that has never been
 * migrated does not fail loudly -- it comes up as a panel with no tickets,
 * no departments and no settings, which reads exactly like data loss. So
 * every candidate is probed first: can we connect, are Escalated's tables
 * there, and how many tickets does it already hold.
 */
class ConnectionInspector
{
    /**
     * Every connection configured in `config/database.php`, described.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $current = Escalated::connection();
        $default = config('database.default');

        return collect(array_keys(config('database.connections', [])))
            ->sort()
            ->values()
            ->map(fn (string $name) => $this->describe($name, $current, $default))
            ->all();
    }

    /**
     * Describe one connection.
     *
     * Every probe is wrapped: a connection configured for a database that is
     * down, or unreachable from this host, must render as an unusable option
     * on the screen rather than a 500 that takes the whole settings page with
     * it.
     *
     * @return array<string, mixed>
     */
    public function describe(string $name, ?string $current = null, ?string $default = null): array
    {
        $current ??= Escalated::connection();
        $default ??= config('database.default');

        // `null` as the configured connection means "the host's default", so
        // the default entry is the active one when nothing is set.
        $isCurrent = $current === null ? $name === $default : $name === $current;

        $config = config("database.connections.{$name}", []);

        $description = [
            'name' => $name,
            'driver' => $config['driver'] ?? null,
            'database' => $this->databaseLabel($config),
            'is_current' => $isCurrent,
            'is_host_default' => $name === $default,
            'reachable' => false,
            'migrated' => false,
            'ticket_count' => null,
            'error' => null,
        ];

        try {
            $connection = DB::connection($name);
            $connection->getPdo();
            $description['reachable'] = true;

            $description['migrated'] = Schema::connection($name)
                ->hasTable(Escalated::table('tickets'));

            if ($description['migrated']) {
                $description['ticket_count'] = (int) $connection
                    ->table(Escalated::table('tickets'))
                    ->count();
            }
        } catch (Throwable $e) {
            // The message is shown to an admin who is choosing between
            // databases and needs to know why one is not an option. It is not
            // rendered anywhere public.
            $description['error'] = $e->getMessage();
        }

        return $description;
    }

    /**
     * Whether Escalated can safely be pointed at this connection.
     *
     * Reachable and migrated: without the tables, the panel would come up
     * empty and look like every ticket had been deleted.
     */
    public function isUsable(string $name): bool
    {
        $described = $this->describe($name);

        return $described['reachable'] && $described['migrated'];
    }

    /**
     * A human label for the database a connection points at, without leaking
     * credentials. SQLite names a path; the servers name a database on a host.
     */
    protected function databaseLabel(array $config): ?string
    {
        $database = $config['database'] ?? null;

        if (! is_string($database) || $database === '') {
            return null;
        }

        if (($config['driver'] ?? null) === 'sqlite') {
            return $database;
        }

        $host = $config['host'] ?? null;

        return is_string($host) && $host !== '' ? "{$database} @ {$host}" : $database;
    }
}

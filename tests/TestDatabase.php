<?php

namespace Escalated\Laravel\Tests;

use InvalidArgumentException;

/**
 * The database the suite runs against.
 *
 * SQLite unless `ESCALATED_TEST_DRIVER` says otherwise, so running the suite
 * locally needs nothing installed. CI runs it three times — sqlite, mysql and
 * pgsql — because the three disagree about enough to matter: LIKE is
 * case-insensitive on MySQL and not on PostgreSQL, aggregate results come back
 * as strings from one driver and ints from another, and SQLite quietly accepts
 * an unknown double-quoted column as a string literal rather than failing.
 *
 * Deliberately not a method on TestCase: Pest treats public methods on the base
 * test case as part of the test scope, and a static one there is reported as
 * "Method not found" from inside a test closure.
 */
final class TestDatabase
{
    private const DRIVERS = ['sqlite', 'mysql', 'mariadb', 'pgsql'];

    /**
     * @return array<string, mixed>
     */
    public static function config(string $suffix = ''): array
    {
        $driver = self::driver();

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ];
        }

        return [
            'driver' => $driver,
            'host' => env('ESCALATED_TEST_HOST', '127.0.0.1'),
            'port' => env('ESCALATED_TEST_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => env('ESCALATED_TEST_DATABASE', 'escalated_test').$suffix,
            'username' => env('ESCALATED_TEST_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => (string) env('ESCALATED_TEST_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
        ];
    }

    /**
     * Never falls back to a driver other than the one asked for. A leg of the
     * CI matrix that silently ran SQLite would report green having tested
     * nothing the matrix exists to test.
     */
    public static function driver(?string $requested = null): string
    {
        $driver = $requested ?? env('ESCALATED_TEST_DRIVER', 'sqlite');

        if (! in_array($driver, self::DRIVERS, true)) {
            throw new InvalidArgumentException(
                'ESCALATED_TEST_DRIVER must be one of '.implode(', ', self::DRIVERS).
                "; got \"{$driver}\"."
            );
        }

        return $driver;
    }
}

<?php

use Escalated\Laravel\Tests\TestDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The suite runs on the driver it was told to run on.
 *
 * A CI matrix leg that quietly fell back to SQLite would go green having tested
 * nothing the matrix exists to test -- and the failure mode is invisible,
 * because every assertion in the suite still passes. This is the one test that
 * notices.
 */
it('runs against the driver ESCALATED_TEST_DRIVER asks for', function () {
    $requested = env('ESCALATED_TEST_DRIVER', 'sqlite');
    $actual = DB::connection()->getDriverName();

    // Laravel reports MariaDB connections as "mariadb" from Laravel 11 on, and
    // the server is close enough that either answer means the leg is real.
    $acceptable = $requested === 'mariadb' ? ['mariadb', 'mysql'] : [$requested];

    expect(in_array($actual, $acceptable, true))->toBeTrue(
        "the suite asked for {$requested} and got {$actual}"
    );
});

it('reaches a database it can actually query', function () {
    // getDriverName() reads configuration, not a connection. Without this, a
    // leg pointed at a database that never came up would still pass the check
    // above.
    expect(DB::connection()->getPdo())->not->toBeNull();

    expect(DB::connection()->select('select 1 as one'))->not->toBeEmpty();
});

it('refuses an unrecognised driver rather than falling back', function () {
    // The fallback is the danger, not the typo. A misspelt driver that quietly
    // became SQLite is exactly the silent-green this file exists to prevent.
    // (Passed in rather than set with putenv: Laravel's env() reads a cached
    // repository, so putenv here would never reach it.)
    expect(fn () => TestDatabase::driver('postgres'))->toThrow(InvalidArgumentException::class);
    expect(TestDatabase::driver('pgsql'))->toBe('pgsql');
});

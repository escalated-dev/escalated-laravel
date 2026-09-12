<?php

namespace Escalated\Laravel\Database;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Migrations\Migration as BaseMigration;

/**
 * Base class for Escalated's migrations, so they build their tables on the
 * connection the package is configured to use.
 *
 * Laravel's migrator reads `getConnection()` and makes that connection the
 * default for the duration of the migration, so the plain `Schema::` calls in
 * each migration body land on the right database with no other change. That is
 * also why this is a `getConnection()` override rather than rewriting sixty
 * migrations to `Schema::connection(...)`: the framework already has the hook,
 * and the rewrite would have missed anything reached indirectly.
 *
 * Returning null — the default when `escalated.connection` is unset — is
 * exactly what a bare `Migration` does, so an unconfigured host migrates
 * precisely as it did before.
 */
abstract class Migration extends BaseMigration
{
    public function getConnection(): ?string
    {
        return Escalated::connection();
    }
}

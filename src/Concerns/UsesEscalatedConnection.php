<?php

namespace Escalated\Laravel\Concerns;

/**
 * Routes Escalated Eloquent models to a configurable database connection.
 *
 * Hosts that store Escalated tables on a non-default connection — most
 * commonly a multi-database multi-tenant setup where each tenant has
 * its own DB — set {@see escalated.database_connection} to that
 * connection name (or `ESCALATED_DB_CONNECTION` env var). All Escalated
 * models then route their queries through it.
 *
 * Per-model `$connection` overrides still win, so subclasses or
 * specific models can opt out by setting `protected $connection`.
 * Hosts that leave the config null see the default Laravel behavior
 * (no behavior change vs. earlier package versions).
 */
trait UsesEscalatedConnection
{
    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('escalated.database_connection');
    }
}

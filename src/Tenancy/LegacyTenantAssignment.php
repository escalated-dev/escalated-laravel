<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Escalated;
use RuntimeException;

/** Explicit whole-install upgrade; never a general tenant reassignment operation. */
class LegacyTenantAssignment
{
    public function assign(string $tenant, bool $apply = false): array
    {
        $enabled = config('escalated.tenancy.enabled', false);
        $connection = Escalated::db();
        $startingLevel = $connection->transactionLevel();
        config(['escalated.tenancy.enabled' => true]);
        try {
            return app(TenantContext::class)->run($tenant, function () use ($tenant, $apply, $connection) {
                $connection->beginTransaction();
                $counts = [];
                foreach (TenantTables::NAMES as $name) {
                    $table = Escalated::table($name);
                    if ($connection->table($table)->where('tenant_id', '!=', '')->exists()) {
                        throw new RuntimeException('Legacy assignment requires a wholly unassigned installation. Assigned rows were found in '.$table.'.');
                    }
                    $counts[$name] = $connection->table($table)->where('tenant_id', '')->update(['tenant_id' => $tenant]);
                }

                // Validate only after all local tables are assigned, still in a
                // reversible transaction. Host identities use the host's own DB.
                foreach (TenantTables::NAMES as $name) {
                    $table = Escalated::table($name);
                    foreach ($connection->table($table)->where('tenant_id', $tenant)->cursor() as $row) {
                        try {
                            app(TenantReferences::class)->validate((new TenantRow)->setTable($table), (array) $row, historical: true);
                        } catch (\Throwable $error) {
                            throw new RuntimeException('Invalid association in '.$table.' (row '.($row->id ?? 'pivot').'): '.$error->getMessage(), previous: $error);
                        }
                    }
                }
                $apply ? $connection->commit() : $connection->rollBack();

                return $counts;
            });
        } finally {
            while ($connection->transactionLevel() > $startingLevel) {
                $connection->rollBack();
            }
            config(['escalated.tenancy.enabled' => $enabled]);
        }
    }
}

<?php

namespace Escalated\Laravel\Tenancy;

use Escalated\Laravel\Escalated;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\DatabasePresenceVerifierInterface;
use Illuminate\Validation\PresenceVerifierInterface;

class TenantPresenceVerifier extends DatabasePresenceVerifier
{
    public function __construct(ConnectionResolverInterface $db, private PresenceVerifierInterface $inner)
    {
        parent::__construct($db);
    }

    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        return $this->shouldScope($collection, $column)
            ? parent::getCount($collection, $column, $value, $excludeId, $idColumn, $extra)
            : $this->inner->getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
    }

    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        return $this->shouldScope($collection, $column)
            ? parent::getMultiCount($collection, $column, $values, $extra)
            : $this->inner->getMultiCount($collection, $column, $values, $extra);
    }

    public function setConnection($connection)
    {
        parent::setConnection($connection);
        if ($this->inner instanceof DatabasePresenceVerifierInterface) {
            $this->inner->setConnection($connection);
        }
    }

    protected function table($table)
    {
        if (TenantTables::contains($table)) {
            return Escalated::query($table)->useWritePdo();
        }

        return app(TenantContext::class)->scopeHost(Escalated::newUserModel()->newQuery())->toBase()->useWritePdo();
    }

    private function shouldScope(string $table, string $column): bool
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            return false;
        }
        if (TenantTables::contains($table)) {
            return true;
        }
        // Preserve the host's unrelated validators outside Escalated requests.
        $user = Escalated::newUserModel();

        return $context->current() !== null && $table === $user->getTable() && $column === $user->getKeyName()
            && ($this->connection ?? $this->db->getDefaultConnection()) === ($user->getConnectionName() ?? $this->db->getDefaultConnection());
    }
}

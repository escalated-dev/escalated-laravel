<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;

/**
 * Single-use message IDs for SSO: the AuthnRequest IDs Escalated issued
 * (`saml_request`), and the SAML assertion IDs (`saml_assertion`) and JWT
 * `jti`s (`jwt`) it has accepted. Rows expire at `expires_at` (Unix seconds)
 * and are pruned on write.
 */
class SsoMessageIds
{
    /** Store an ID that a later message must name (an issued request). */
    public function remember(string $kind, string $id, int $expiresAt): void
    {
        $this->prune();

        $this->table()->insert(['kind' => $kind, 'message_id' => $id, 'expires_at' => $expiresAt]);
    }

    /** Remove an unexpired remembered ID: true only the first time. */
    public function consume(string $kind, string $id): bool
    {
        return $this->table()
            ->where('kind', $kind)
            ->where('message_id', $id)
            ->where('expires_at', '>', time())
            ->delete() === 1;
    }

    /** Record an ID as used: true the first time, false on a replay. */
    public function claim(string $kind, string $id, int $expiresAt): bool
    {
        $this->prune();

        try {
            $this->table()->insert(['kind' => $kind, 'message_id' => $id, 'expires_at' => $expiresAt]);
        } catch (QueryException) {
            return false;
        }

        return true;
    }

    protected function prune(): void
    {
        $this->table()->where('expires_at', '<=', time())->delete();
    }

    protected function table(): Builder
    {
        return Escalated::query(Escalated::table('sso_message_ids'));
    }
}

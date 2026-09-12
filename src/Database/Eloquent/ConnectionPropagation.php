<?php

namespace Escalated\Laravel\Database\Eloquent;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;

/**
 * Decides whether Escalated's connection may be pushed onto a related model.
 *
 * The rule is one sentence: Escalated's connection follows Escalated's models
 * and stops at the host's. A ticket may live in a separate database while its
 * requester, its assignee and its ticket subjects stay in the application's —
 * which is the whole point of letting the two be configured apart.
 */
final class ConnectionPropagation
{
    public static function belongsToEscalated(object $model): bool
    {
        return in_array(UsesEscalatedConnection::class, class_uses_recursive($model), true);
    }
}

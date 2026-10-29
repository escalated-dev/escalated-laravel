<?php

namespace Escalated\Laravel\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Remembers, per session, which user has passed the two-factor challenge.
 * Stored as the user's key, so a session that passed for one user does not
 * count for another, and cleared on every login and logout.
 */
final class TwoFactorSession
{
    public const KEY = 'escalated.two_factor.passed';

    public static function passed(?Session $session, mixed $user): bool
    {
        return $session !== null
            && $user !== null
            && $session->get(self::KEY) === (string) $user->getKey();
    }

    public static function markPassed(Session $session, mixed $user): void
    {
        $session->put(self::KEY, (string) $user->getKey());
    }

    public static function forget(?Session $session): void
    {
        $session?->forget(self::KEY);
    }
}

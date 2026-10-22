<?php

namespace Escalated\Laravel\Support;

/**
 * The placeholder an admin settings page shows for a stored secret.
 *
 * The page learns only that a secret is set. Posting the placeholder back
 * means "keep the stored value"; anything else replaces it.
 */
final class SecretMask
{
    public const MASK = '********';

    public static function mask(?string $value): string
    {
        return ($value === null || $value === '') ? '' : self::MASK;
    }

    /**
     * Also accepts the older "abc*****" form, so a settings tab opened before
     * an upgrade does not overwrite the secret with its placeholder.
     */
    public static function isMasked(mixed $value): bool
    {
        return is_string($value) && $value !== '' && preg_match('/^.{0,3}\*{3,}$/', $value) === 1;
    }
}

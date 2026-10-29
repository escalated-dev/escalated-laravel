<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\TwoFactor;
use Escalated\Laravel\Support\AccessFeatures;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238: SHA-1, 6 digits, 30 s steps) through pragmarx/google2fa,
 * plus single-use recovery codes.
 */
class TwoFactorService
{
    /** Steps of clock drift accepted either side of now. */
    public const WINDOW = 1;

    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
        $this->google2fa->setWindow(self::WINDOW);
    }

    /** A random 32-character base32 secret (160 bits). */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** The otpauth:// URI an authenticator app reads from a QR code. */
    public function generateQrUri(string $secret, string $email): string
    {
        $issuer = (string) (config('escalated.two_factor.issuer') ?: config('app.name', 'Escalated'));

        return $this->google2fa->getQRCodeUrl($issuer, $email, $secret);
    }

    /** Whether the code is valid for the secret right now (no replay check). */
    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $this->normalizeCode($code), self::WINDOW);
    }

    /**
     * Verify a code for an enrolment and record its time step, refusing a
     * step at or before the last one accepted (so a code works once).
     */
    public function verifyAndConsume(TwoFactor $twoFactor, string $code): bool
    {
        $code = $this->normalizeCode($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $step = $this->google2fa->verifyKeyNewer(
            $twoFactor->secret,
            $code,
            // 0, not null: with null the library returns true instead of the step.
            $twoFactor->last_used_step !== null ? (int) $twoFactor->last_used_step : 0,
            self::WINDOW,
        );

        if (! is_int($step)) {
            return false;
        }

        $twoFactor->forceFill(['last_used_step' => (int) $step])->save();

        return true;
    }

    /** Use a recovery code: true and removed when it matches one. */
    public function useRecoveryCode(TwoFactor $twoFactor, string $code): bool
    {
        $code = strtoupper(trim($code));
        $codes = $twoFactor->recovery_codes ?? [];

        foreach ($codes as $index => $candidate) {
            if ($code !== '' && hash_equals(strtoupper((string) $candidate), $code)) {
                unset($codes[$index]);
                $twoFactor->forceFill(['recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** Eight random single-use recovery codes. */
    public function generateRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))).'-'.strtoupper(bin2hex(random_bytes(4)));
        }

        return $codes;
    }

    /**
     * Whether staff must enrol: the `two_factor_required` setting when an
     * admin has saved it, else escalated.two_factor.required. Only while the
     * two-factor switch is on.
     */
    public static function required(): bool
    {
        if (! AccessFeatures::twoFactor()) {
            return false;
        }

        try {
            return EscalatedSettings::getBool('two_factor_required', (bool) config('escalated.two_factor.required', false));
        } catch (\Throwable) {
            return (bool) config('escalated.two_factor.required', false);
        }
    }

    public static function confirmedFor(mixed $user): ?TwoFactor
    {
        if (! $user || ! method_exists($user, 'getKey')) {
            return null;
        }

        return TwoFactor::where('user_id', $user->getKey())->whereNotNull('confirmed_at')->first();
    }

    protected function normalizeCode(string $code): string
    {
        return preg_replace('/\s+/', '', $code) ?? '';
    }
}

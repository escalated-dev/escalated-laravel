<?php

namespace Escalated\Laravel\Services;

use Closure;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Mail\GuestVerificationCode;
use Escalated\Laravel\Models\GuestVerification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestEmailVerification
{
    public static function email(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Email a one-time code. Delivery is budgeted per (mailbox, client IP)
     * so one client cannot exhaust the owner's budget, and per mailbox
     * across all clients so the mailbox cannot be flooded.
     */
    public function challenge(string $email, string $purpose, ?string $clientIp = null): string
    {
        $email = self::email($email);
        if (! in_array($purpose, ['ticket', 'chat', 'lookup'], true) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }
        $key = 'escalated:guest-email:'.$this->digest($email);
        $clientKey = $key.':client:'.$this->digest((string) ($clientIp ?? request()->ip() ?? ''));
        $perClient = max(1, (int) config('escalated.guest_access.challenges_per_client_per_hour', 3));
        $perMailbox = max($perClient, (int) config('escalated.guest_access.challenges_per_mailbox_per_hour', 10));
        try {
            Cache::lock($key.':lock', 10)->block(3, function () use ($key, $clientKey, $perClient, $perMailbox) {
                foreach ([$clientKey => $perClient, $key => $perMailbox] as $limitKey => $limit) {
                    abort_if(RateLimiter::tooManyAttempts($limitKey, $limit), 429, 'Please wait before requesting another code.', [
                        'Retry-After' => (string) RateLimiter::availableIn($limitKey),
                    ]);
                }
                RateLimiter::hit($clientKey, 3600);
                RateLimiter::hit($key, 3600);
            });
        } catch (LockTimeoutException) {
            abort(429, 'Please wait before requesting another code.', ['Retry-After' => '10']);
        }

        $id = (string) Str::uuid();
        $code = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $verification = GuestVerification::create([
            'id' => $id, 'email' => $email, 'purpose' => $purpose,
            'code_hash' => $this->digest($id.':'.$code), 'expires_at' => now()->addMinutes(10),
        ]);
        try {
            Mail::to($email)->send(new GuestVerificationCode($code));
        } catch (\Throwable $error) {
            $verification->delete();
            throw $error;
        }

        return $id;
    }

    /** The callback and one-time consumption commit together on the package DB. */
    public function consume(string $id, string $code, string $email, string $purpose, Closure $callback): mixed
    {
        $result = Escalated::db()->transaction(function () use ($id, $code, $email, $purpose, $callback) {
            $verification = GuestVerification::whereKey($id)->lockForUpdate()->first();
            if (! $verification || $verification->used_at || $verification->expires_at->lte(now()) || $verification->attempts >= 5) {
                return ['valid' => false];
            }
            $verification->increment('attempts');
            if (! hash_equals($verification->code_hash, $this->digest($id.':'.$code))
                || $verification->email !== self::email($email) || $verification->purpose !== $purpose) {
                return ['valid' => false];
            }
            $verification->update(['used_at' => now()]);

            return ['valid' => true, 'value' => $callback()];
        });
        // Outside the transaction: an incorrect attempt must not be rolled back.
        if (! $result['valid']) {
            throw ValidationException::withMessages(['verification_code' => 'This code is invalid, expired or already used. Request a new code.']);
        }

        return $result['value'];
    }

    private function digest(string $value): string
    {
        return hash_hmac('sha256', 'escalated-guest-email:'.$value, app('encrypter')->getKey());
    }
}

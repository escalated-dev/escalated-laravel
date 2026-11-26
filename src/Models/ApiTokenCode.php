<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One-time authorization code for the browser extension sign-in (PKCE S256).
 * Only the sha256 of the code is stored.
 */
class ApiTokenCode extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected $hidden = ['code'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('api_token_codes');
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Issue a code and return the plain value (shown once, never stored). */
    public static function issue(
        Model $user,
        string $codeChallenge,
        string $redirectUri,
        string $clientName,
        string $extensionId,
        int $ttlSeconds = 60,
    ): string {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        static::create([
            'code' => hash('sha256', $plain),
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'code_challenge' => $codeChallenge,
            'redirect_uri' => $redirectUri,
            'client_name' => $clientName,
            'extension_id' => $extensionId,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return $plain;
    }

    /**
     * Consume a code. Any presentation of a known, unexpired, unused code
     * marks it used; the row comes back only when the verifier and the exact
     * redirect_uri also match. A replayed (already used) code revokes the
     * token it issued.
     */
    public static function consume(string $code, string $verifier, string $redirectUri): ?self
    {
        return Escalated::db()->transaction(function () use ($code, $verifier, $redirectUri) {
            $row = static::where('code', hash('sha256', $code))->first();

            if (! $row || $row->expires_at->isPast()) {
                return null;
            }

            $claimed = static::where('id', $row->id)->whereNull('used_at')->update(['used_at' => now()]);

            if ($claimed !== 1) {
                if ($row->api_token_id) {
                    ApiToken::where('id', $row->api_token_id)->delete();
                }

                return null;
            }

            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            if (! hash_equals($row->code_challenge, $challenge) || ! hash_equals($row->redirect_uri, $redirectUri)) {
                return null;
            }

            return $row->refresh();
        });
    }
}

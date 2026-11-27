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
            'client_name' => mb_substr($clientName, 0, 64),
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
     *
     * $onSuccess, when given, runs inside the same transaction as the claim
     * with the matched row and returns the token it minted (or null to mint
     * nothing, which leaves the code burned and returns null). The token id is
     * written to the code before commit, so a replay can always revoke it.
     *
     * @param  (callable(self): ?ApiToken)|null  $onSuccess
     */
    public static function consume(string $code, string $verifier, string $redirectUri, ?callable $onSuccess = null): ?self
    {
        return Escalated::db()->transaction(function () use ($code, $verifier, $redirectUri, $onSuccess) {
            $row = static::where('code', hash('sha256', $code))->first();

            if (! $row || $row->expires_at->isPast()) {
                return null;
            }

            $claimed = static::where('id', $row->id)->whereNull('used_at')->update(['used_at' => now()]);

            if ($claimed !== 1) {
                // Re-read with a locking read: the first read may predate the
                // winning exchange's commit and miss the token it minted.
                $tokenId = static::where('id', $row->id)->lockForUpdate()->value('api_token_id');

                if ($tokenId) {
                    ApiToken::where('id', $tokenId)->delete();
                }

                return null;
            }

            $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            if (! hash_equals($row->code_challenge, $challenge) || ! hash_equals($row->redirect_uri, $redirectUri)) {
                return null;
            }

            $row->refresh();

            if ($onSuccess === null) {
                return $row;
            }

            $token = $onSuccess($row);

            if (! $token instanceof ApiToken) {
                return null;
            }

            $row->update(['api_token_id' => $token->getKey()]);

            return $row;
        });
    }
}

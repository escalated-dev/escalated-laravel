<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApiToken extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    /** The token hash is only for lookups; never serialized. */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('api_tokens');
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array('*', $abilities) || in_array($ability, $abilities);
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public static function createToken(Model $tokenable, string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null, ?string $kind = null, ?string $extensionId = null): array
    {
        $plainText = bin2hex(random_bytes(32));

        $attributes = [
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plainText),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ];

        // Only written when set, so an install that updated the package but
        // has not yet run the kind / extension_id migration can still mint
        // admin and mobile tokens.
        if ($kind !== null) {
            $attributes['kind'] = $kind;
        }

        if ($extensionId !== null) {
            $attributes['extension_id'] = $extensionId;
        }

        $token = static::create($attributes);

        return ['token' => $token, 'plainTextToken' => $plainText];
    }

    public static function findByPlainText(string $plainText): ?static
    {
        return static::where('token', hash('sha256', $plainText))->first();
    }
}

<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApiToken extends Model
{
    protected $guarded = ['id'];

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

    public static function createToken(Model $tokenable, string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): array
    {
        $plainText = bin2hex(random_bytes(32));

        $token = static::create([
            'tokenable_type' => $tokenable->getMorphClass(),
            'tokenable_id' => $tokenable->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plainText),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plainTextToken' => $plainText];
    }

    public static function findByPlainText(string $plainText): ?static
    {
        return static::where('token', hash('sha256', $plainText))->first();
    }
}

<?php

namespace Escalated\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * First-class identity for guest requesters. Deduped by email
 * (unique index). Links to a host-app user via `user_id` once the
 * guest accepts a signup invite.
 *
 * Coexists with the inline guest_* columns on Ticket for one
 * release; the backfill migration populates `contact_id` for
 * existing rows. New code should write via Contact.
 */
class Contact extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('escalated.table_prefix', 'escalated_').'contacts';
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'contact_id');
    }

    public static function findOrCreateByEmail(string $email, ?string $name = null): self
    {
        $normalized = strtolower(trim($email));

        $existing = static::where('email', $normalized)->first();
        if ($existing) {
            if (empty($existing->name) && ! empty($name)) {
                $existing->name = $name;
                $existing->save();
            }

            return $existing;
        }

        return static::create([
            'email' => $normalized,
            'name' => $name,
            'user_id' => null,
            'metadata' => [],
        ]);
    }

    public function linkToUser(int $userId): self
    {
        $this->user_id = $userId;
        $this->save();

        return $this;
    }

    /**
     * Link to a host-app user and back-stamp requester_id on all
     * prior tickets owned by this contact.
     */
    public function promoteToUser(int $userId, string $userType = 'App\\Models\\User'): self
    {
        $this->linkToUser($userId);
        Ticket::where('contact_id', $this->id)->update([
            'requester_id' => $userId,
            'requester_type' => $userType,
        ]);

        return $this;
    }
}

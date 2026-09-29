<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class GuestAccess
{
    /** Trusted service entry point: email proof must already have been consumed. */
    public function issue(Ticket $ticket, string $purpose = 'ticket'): string
    {
        app(TenantContext::class)->assertOwns($ticket);
        if (! $this->verified($ticket) || ! in_array($purpose, ['ticket', 'chat'], true)) {
            throw new \LogicException('Guest access requires a verified ticket email and a supported purpose.');
        }
        $nonce = Str::random(64);
        $minutes = max(5, min(10080, (int) config('escalated.guest_access.ttl_minutes', 1440)));
        $expires = now()->addMinutes($minutes)->startOfSecond();
        $ticket->updateQuietly([
            'guest_access_hash' => hash('sha256', $nonce), 'guest_access_expires_at' => $expires,
            'guest_token' => null,
        ]);
        $claims = [
            'version' => 1, 'ticket' => (string) $ticket->getKey(),
            'tenant' => (string) $ticket->tenant_id, 'purpose' => $purpose,
            'email' => hash('sha256', $ticket->guest_verified_email),
            'nonce' => $nonce, 'expires' => $expires->timestamp,
        ];

        // Laravel's authenticated encryption includes an integrity MAC/signature.
        // Base64url makes the sealed value safe in existing token route segments.
        return rtrim(strtr(base64_encode(Crypt::encryptString(json_encode($claims, JSON_THROW_ON_ERROR))), '+/', '-_'), '=');
    }

    public function resolve(string $token, string $purpose = 'ticket'): Ticket
    {
        abort_if(strlen($token) > 4096 || ! preg_match('/^[A-Za-z0-9_-]+$/D', $token), 404);
        try {
            $sealed = base64_decode(strtr($token, '-_', '+/'), true);
            $claims = json_decode(Crypt::decryptString($sealed ?: ''), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            abort(404);
        }
        abort_unless(is_array($claims) && ($claims['version'] ?? null) === 1
            && is_string($claims['ticket'] ?? null) && ctype_digit($claims['ticket'])
            && is_string($claims['nonce'] ?? null) && strlen($claims['nonce']) === 64
            && is_int($claims['expires'] ?? null) && $claims['expires'] > now()->timestamp
            && ($claims['purpose'] ?? null) === $purpose
            && ($claims['tenant'] ?? null) === app(TenantContext::class)->id(), 404);
        $ticket = Ticket::findOrFail($claims['ticket']);
        abort_unless($this->active($ticket)
            && $ticket->guest_access_expires_at->timestamp === $claims['expires']
            && hash_equals($ticket->guest_access_hash, hash('sha256', $claims['nonce']))
            && ($claims['email'] ?? null) === hash('sha256', $ticket->guest_verified_email), 404);

        return $ticket;
    }

    public function active(Ticket $ticket): bool
    {
        return app(TenantContext::class)->owns($ticket) && $this->verified($ticket)
            && is_string($ticket->guest_access_hash) && strlen($ticket->guest_access_hash) === 64
            && $ticket->guest_access_expires_at?->gt(now());
    }

    public function revoke(Ticket $ticket): void
    {
        $ticket->updateQuietly(['guest_access_hash' => null, 'guest_access_expires_at' => null, 'guest_token' => null]);
    }

    private function verified(Ticket $ticket): bool
    {
        return $ticket->guest_email_verified_at !== null && is_string($ticket->guest_email)
            && $ticket->guest_email !== '' && $ticket->guest_verified_email === GuestEmailVerification::email($ticket->guest_email);
    }
}

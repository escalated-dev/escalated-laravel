<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class AttachmentAccess
{
    /** Set only after a guest entry point has authenticated its ticket capability. */
    public function forGuest(Ticket $ticket): void
    {
        request()->attributes->set('escalated.attachment_guest_ticket', $ticket);
    }

    public function ticket(Attachment $attachment): ?Ticket
    {
        $parent = $attachment->attachable;

        return match (true) {
            $parent instanceof Ticket => $parent,
            $parent instanceof Reply => $parent->ticket,
            default => null,
        };
    }

    public function url(Attachment $attachment): string
    {
        $parameters = ['attachment' => $attachment->getKey()];
        $guest = request()->attributes->get('escalated.attachment_guest_ticket');
        if ($guest instanceof Ticket && app(GuestAccess::class)->active($guest) && $this->isPublic($attachment)
            && $this->ticket($attachment)?->is($guest)) {
            // The download gets a short-lived, ticket-bound capability, never the
            // ticket grant. Rotating or expiring that grant revokes issued links.
            $parameters['guest'] = $guest->guest_access_hash;
        }

        $minutes = max(1, min(60, (int) config('escalated.storage.download_ttl_minutes', 10)));

        $expires = now()->addMinutes($minutes);
        if (isset($parameters['guest']) && $guest->guest_access_expires_at->lt($expires)) {
            $expires = $guest->guest_access_expires_at;
        }

        return URL::temporarySignedRoute('escalated.attachments.download', $expires, $parameters);
    }

    public function authorize(Request $request, Attachment $attachment): void
    {
        $ticket = $this->ticket($attachment);
        abort_unless($ticket, 404);

        $guest = $request->query('guest');
        if (is_string($guest) && app(GuestAccess::class)->active($ticket) && $this->isPublic($attachment)
            && hash_equals($ticket->guest_access_hash, $guest)) {
            return; // Signature and expiry were checked by the route middleware.
        }

        $user = $request->user();
        abort_unless($user && Gate::forUser($user)->allows('view', $ticket), 403);
        $token = $request->attributes->get('api_token');
        if ($token instanceof ApiToken && ! $token->hasAbility('agent') && ! $token->hasAbility('admin')) {
            abort_unless($token->hasAbility('customer') && $this->isPublic($attachment)
                && (string) $ticket->requester_id === (string) $user->getKey()
                && $ticket->requester_type === $user->getMorphClass(), 403);
        }
        if (! $this->isPublic($attachment)) {
            abort_unless(StaffAccess::isStaff($user), 403);
        }
    }

    private function isPublic(Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        return $parent instanceof Ticket || ($parent instanceof Reply && ! $parent->is_internal_note);
    }
}

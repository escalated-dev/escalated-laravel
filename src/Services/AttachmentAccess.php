<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
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
        if ($guest instanceof Ticket && $guest->guest_token && $this->isPublic($attachment)
            && $this->ticket($attachment)?->is($guest)) {
            // The download gets a short-lived, ticket-bound capability, never the
            // permanent guest token. Rotating that token also revokes issued links.
            $parameters['guest'] = hash('sha256', $guest->guest_token);
        }

        $minutes = max(1, min(60, (int) config('escalated.storage.download_ttl_minutes', 10)));

        return URL::temporarySignedRoute('escalated.attachments.download', now()->addMinutes($minutes), $parameters);
    }

    public function authorize(Request $request, Attachment $attachment): void
    {
        $ticket = $this->ticket($attachment);
        abort_unless($ticket, 404);

        $guest = $request->query('guest');
        if (is_string($guest) && $ticket->guest_token && $this->isPublic($attachment)
            && hash_equals(hash('sha256', $ticket->guest_token), $guest)) {
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
            abort_unless(Gate::forUser($user)->allows(config('escalated.authorization.agent_gate', 'escalated-agent'))
                || Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin')), 403);
        }
    }

    private function isPublic(Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        return $parent instanceof Ticket || ($parent instanceof Reply && ! $parent->is_internal_note);
    }
}

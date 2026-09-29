<?php

namespace Escalated\Laravel\Notifications\Concerns;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Gate;

trait GuardsTenantNotification
{
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $context = app(TenantContext::class);
        if (! $context->enabled()) {
            return true;
        }
        $record = $this->ticket ?? $this->reply ?? null;
        if (! $record instanceof Model || ! $context->owns($record)) {
            return false;
        }
        $ticket = $record instanceof Ticket ? $record : $record->ticket;
        if (! $ticket || ! $context->owns($ticket) || ! Ticket::whereKey($ticket->getKey())->exists()) {
            return false;
        }
        $internal = (bool) ($this->reply?->is_internal_note ?? false);
        if ($notifiable instanceof Model) {
            return $context->canAccess($notifiable)
                && $context->resolver()->canReference($notifiable, $context->id())
                && $context->scopeHost($notifiable->newQuery())->whereKey($notifiable->getKey())->exists()
                && Gate::forUser($notifiable)->allows($internal ? 'addNote' : 'view', $ticket);
        }

        if (! $notifiable instanceof AnonymousNotifiable || $channel !== 'mail' || $internal
            || ! ($this instanceof Notifications\NewTicketNotification
                || $this instanceof Notifications\TicketReplyNotification
                || $this instanceof Notifications\TicketStatusChangedNotification
                || $this instanceof Notifications\TicketResolvedNotification)) {
            return false;
        }
        $recipient = $notifiable->routeNotificationFor('mail');

        return is_string($recipient) && is_string($ticket->guest_email)
            && $ticket->guest_email !== '' && strcasecmp($recipient, $ticket->guest_email) === 0;
    }
}

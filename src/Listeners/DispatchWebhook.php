<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events;
use Escalated\Laravel\Services\NotificationService;
use Escalated\Laravel\Services\WebhookDispatcher;
use Escalated\Laravel\Support\ImportContext;

class DispatchWebhook
{
    public function __construct(
        protected NotificationService $notificationService,
        protected WebhookDispatcher $webhookDispatcher,
    ) {}

    public function handle(object $event): void
    {
        if (ImportContext::isImporting()) {
            return;
        }

        $eventName = match (true) {
            $event instanceof Events\TicketCreated => 'ticket.created',
            $event instanceof Events\TicketUpdated => 'ticket.updated',
            $event instanceof Events\TicketStatusChanged => 'ticket.status_changed',
            $event instanceof Events\TicketResolved => 'ticket.resolved',
            $event instanceof Events\TicketClosed => 'ticket.closed',
            $event instanceof Events\TicketReopened => 'ticket.reopened',
            $event instanceof Events\TicketAssigned => 'ticket.assigned',
            $event instanceof Events\TicketUnassigned => 'ticket.unassigned',
            $event instanceof Events\TicketEscalated => 'ticket.escalated',
            $event instanceof Events\TicketPriorityChanged => 'ticket.priority_changed',
            $event instanceof Events\DepartmentChanged => 'ticket.department_changed',
            $event instanceof Events\ReplyCreated => 'reply.created',
            $event instanceof Events\InternalNoteAdded => 'note.created',
            $event instanceof Events\SlaBreached => 'sla.breached',
            $event instanceof Events\SlaWarning => 'sla.warning',
            $event instanceof Events\TagAddedToTicket => 'ticket.tag_added',
            $event instanceof Events\TagRemovedFromTicket => 'ticket.tag_removed',
            default => null,
        };

        if (! $eventName) {
            return;
        }

        $payload = $this->buildPayload($event);

        // The webhooks admins create under Admin > Webhooks.
        $this->webhookDispatcher->dispatch($eventName, $payload);

        // The single endpoint set by escalated.notifications.webhook_url.
        $this->notificationService->sendWebhook($eventName, $payload);
    }

    protected function buildPayload(object $event): array
    {
        $payload = [];

        if (property_exists($event, 'ticket')) {
            $payload['ticket'] = [
                'id' => $event->ticket->id,
                'reference' => $event->ticket->reference,
                'subject' => $event->ticket->subject,
                'status' => $event->ticket->status->value,
                'priority' => $event->ticket->priority->value,
            ];
        }

        if (property_exists($event, 'reply')) {
            $payload['ticket'] = [
                'id' => $event->reply->ticket->id,
                'reference' => $event->reply->ticket->reference,
            ];
            $payload['reply'] = [
                'id' => $event->reply->id,
                'is_internal_note' => $event->reply->is_internal_note,
            ];
        }

        if (property_exists($event, 'tag')) {
            $payload['tag'] = [
                'id' => $event->tag->id,
                'name' => $event->tag->name,
            ];
        }

        if (property_exists($event, 'agentId')) {
            $payload['agent_id'] = $event->agentId;
        }

        return $payload;
    }
}

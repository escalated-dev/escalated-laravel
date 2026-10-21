<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events;
use Escalated\Laravel\Models\ChatSession;
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
            $event instanceof Events\ChatStarted => 'chat.started',
            $event instanceof Events\ChatAssigned => 'chat.assigned',
            $event instanceof Events\ChatTransferred => 'chat.transferred',
            $event instanceof Events\ChatMessage => 'chat.message',
            $event instanceof Events\ChatEnded => 'chat.ended',
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
        if (property_exists($event, 'session') && $event->session instanceof ChatSession) {
            return ['chat' => $this->chatPayload($event)];
        }

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

    /**
     * The chat fields that leave the process. The visitor's session token (or
     * its hash) and the visitor's email never do, and neither do message
     * bodies: a subscriber reads those through the API.
     */
    protected function chatPayload(object $event): array
    {
        $session = $event->session;

        $chat = [
            'session_id' => $session->id,
            'ticket_id' => $session->ticket_id,
            'status' => $session->status?->value,
            'agent_id' => $session->agent_id,
            'department_id' => $session->ticket?->department_id,
        ];

        if ($event instanceof Events\ChatTransferred) {
            $chat['from_agent_id'] = $event->fromAgentId;
            $chat['to_agent_id'] = $event->toAgentId;
            $chat['to_department_id'] = $event->toDepartmentId;
        }

        if ($event instanceof Events\ChatMessage) {
            $chat['reply_id'] = $event->reply->id;
            $chat['author_type'] = $event->isAgent ? 'agent' : 'visitor';
        }

        if ($event instanceof Events\ChatEnded) {
            $chat['ended_by'] = $event->endedBy;
        }

        return $chat;
    }
}

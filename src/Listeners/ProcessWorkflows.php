<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Services\WorkflowEngine;
use Escalated\Laravel\Support\ImportContext;

class ProcessWorkflows
{
    public function __construct(protected WorkflowEngine $engine) {}

    public function handle(object $event): void
    {
        if (ImportContext::isImporting()) {
            return;
        }

        $mapping = $this->resolveEvent($event);

        if (! $mapping) {
            return;
        }

        [$eventName, $ticket] = $mapping;

        $this->engine->processEvent($eventName, $ticket);
    }

    protected function resolveEvent(object $event): ?array
    {
        return match (true) {
            $event instanceof Events\TicketCreated => ['ticket.created', $event->ticket],
            $event instanceof Events\TicketUpdated => ['ticket.updated', $event->ticket],
            $event instanceof Events\ReplyCreated => $this->isWorkflowReply($event->reply)
                ? null
                : ['ticket.replied', $event->reply->ticket],
            $event instanceof Events\TicketStatusChanged => ['ticket.status_changed', $event->ticket],
            $event instanceof Events\TicketAssigned => ['ticket.assigned', $event->ticket],
            $event instanceof Events\TicketEscalated => ['ticket.escalated', $event->ticket],
            $event instanceof Events\SlaBreached => ['sla.breached', $event->ticket],
            $event instanceof Events\SlaWarning => ['sla.warning', $event->ticket],
            $event instanceof Events\ChatStarted => $event->session->ticket
                ? ['chat.started', $event->session->ticket]
                : null,
            $event instanceof Events\ChatEnded => $event->session->ticket
                ? ['chat.ended', $event->session->ticket]
                : null,
            default => null,
        };
    }

    /**
     * A reply written by a workflow (insert_canned_reply) carries the workflow
     * id in its metadata. It does not trigger workflows: a ticket.replied
     * workflow would otherwise answer its own reply without end.
     */
    protected function isWorkflowReply(Reply $reply): bool
    {
        return isset($reply->metadata['workflow_id']);
    }
}

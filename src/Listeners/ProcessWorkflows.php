<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Services\WorkflowEngine;
use Escalated\Laravel\Support\ImportContext;

/**
 * Runs workflows under the canonical event names of ADR
 * 2026-10-21-event-vocabulary. WorkflowController::availableTriggerEvents()
 * offers exactly the names fired here.
 */
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
                : ['reply.created', $event->reply->ticket],
            $event instanceof Events\InternalNoteAdded => $this->isWorkflowReply($event->note)
                ? null
                : ['note.created', $event->note->ticket],
            $event instanceof Events\TicketStatusChanged => ['ticket.status_changed', $event->ticket],
            $event instanceof Events\TicketAssigned => ['ticket.assigned', $event->ticket],
            $event instanceof Events\TicketUnassigned => ['ticket.unassigned', $event->ticket],
            $event instanceof Events\TicketPriorityChanged => ['ticket.priority_changed', $event->ticket],
            $event instanceof Events\DepartmentChanged => ['ticket.department_changed', $event->ticket],
            $event instanceof Events\TagAddedToTicket => ['ticket.tag_added', $event->ticket],
            $event instanceof Events\TagRemovedFromTicket => ['ticket.tag_removed', $event->ticket],
            $event instanceof Events\TicketEscalated => ['ticket.escalated', $event->ticket],
            $event instanceof Events\SlaBreached => ['sla.breached', $event->ticket],
            $event instanceof Events\SlaWarning => ['sla.warning', $event->ticket],
            $event instanceof Events\ChatStarted => $this->chat('chat.started', $event->session),
            $event instanceof Events\ChatAssigned => $this->chat('chat.assigned', $event->session),
            $event instanceof Events\ChatTransferred => $this->chat('chat.transferred', $event->session),
            $event instanceof Events\ChatMessage => $this->chat('chat.message', $event->session),
            $event instanceof Events\ChatEnded => $this->chat('chat.ended', $event->session),
            default => null,
        };
    }

    protected function chat(string $name, ChatSession $session): ?array
    {
        return $session->ticket ? [$name, $session->ticket] : null;
    }

    /**
     * A reply or note written by a workflow (insert_canned_reply, add_note)
     * carries the workflow id in its metadata. It does not trigger workflows:
     * a reply.created or note.created workflow would otherwise answer its own
     * reply without end.
     */
    protected function isWorkflowReply(Reply $reply): bool
    {
        return isset($reply->metadata['workflow_id']);
    }
}

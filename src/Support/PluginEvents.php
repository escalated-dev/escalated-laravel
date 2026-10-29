<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Events;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;

/**
 * The canonical events (decisions/2026-10-21-event-vocabulary.md) and how
 * each looks to a plugin's actions (domain-model/plugin-contract.md): the
 * subject flat at the top and again under its own key, snake_case.
 */
final class PluginEvents
{
    /**
     * Lifecycle actions the host fires itself. A plugin may not emit them.
     */
    public const LIFECYCLE = ['plugin.activated', 'plugin.deactivated', 'plugin.uninstalling'];

    /**
     * The canonical name of a domain event object, or null.
     */
    public static function nameOf(object $event): ?string
    {
        return match (true) {
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
    }

    /**
     * Whether a plugin-emitted name would impersonate a host event: a
     * canonical event, a legacy alias of one, or a lifecycle action.
     */
    public static function isReserved(string $hook): bool
    {
        return in_array($hook, self::LIFECYCLE, true)
            || isset(EventVocabulary::LEGACY[$hook])
            || in_array($hook, self::canonicalNames(), true);
    }

    /**
     * @return list<string>
     */
    public static function canonicalNames(): array
    {
        return [
            'ticket.created', 'ticket.updated', 'ticket.status_changed', 'ticket.resolved', 'ticket.closed',
            'ticket.reopened', 'ticket.assigned', 'ticket.unassigned', 'ticket.escalated', 'ticket.priority_changed',
            'ticket.department_changed', 'ticket.tag_added', 'ticket.tag_removed', 'reply.created', 'note.created',
            'sla.warning', 'sla.breached', 'chat.started', 'chat.assigned', 'chat.transferred', 'chat.message', 'chat.ended',
        ];
    }

    public static function payload(object $event): array
    {
        if (property_exists($event, 'session') && $event->session instanceof ChatSession) {
            return ['session' => self::chat($event)];
        }

        if ($event instanceof Events\ReplyCreated || $event instanceof Events\InternalNoteAdded) {
            $reply = $event instanceof Events\ReplyCreated ? $event->reply : $event->note;
            $flat = self::reply($reply);

            return [...$flat, 'reply' => $flat, 'ticket' => $reply->ticket ? self::ticket($reply->ticket) : null];
        }

        $extra = match (true) {
            $event instanceof Events\TicketStatusChanged => [
                'old_status' => $event->oldStatus->value, 'new_status' => $event->newStatus->value,
            ],
            $event instanceof Events\TicketAssigned => ['agent_id' => $event->agentId],
            $event instanceof Events\TicketUnassigned => ['previous_agent_id' => $event->previousAgentId],
            $event instanceof Events\TicketPriorityChanged => [
                'old_priority' => $event->oldPriority->value, 'new_priority' => $event->newPriority->value,
            ],
            $event instanceof Events\DepartmentChanged => [
                'old_department_id' => $event->oldDepartmentId, 'new_department_id' => $event->newDepartmentId,
            ],
            $event instanceof Events\TagAddedToTicket, $event instanceof Events\TagRemovedFromTicket => [
                'tag' => self::tag($event->tag),
            ],
            $event instanceof Events\SlaWarning => ['type' => $event->type, 'minutes_remaining' => $event->minutesRemaining],
            $event instanceof Events\SlaBreached => ['type' => $event->type],
            $event instanceof Events\TicketEscalated => ['reason' => $event->reason],
            default => [],
        };

        $flat = self::ticket($event->ticket);

        return [...$flat, 'ticket' => $flat, ...$extra];
    }

    public static function ticket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'title' => $ticket->subject,
            'description' => $ticket->description,
            'status' => $ticket->status?->value,
            'priority' => $ticket->priority?->value,
            'channel' => $ticket->channel,
            'ticket_type' => $ticket->ticket_type,
            'department_id' => $ticket->department_id,
            'assigned_to' => $ticket->assigned_to,
            'requester_id' => $ticket->requester_id,
            'contact_id' => $ticket->contact_id,
            'metadata' => $ticket->metadata,
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    public static function reply(Reply $reply): array
    {
        return [
            'id' => $reply->id,
            'ticket_id' => $reply->ticket_id,
            'body' => $reply->body,
            'type' => $reply->type,
            'is_internal' => (bool) $reply->is_internal_note,
            'author_id' => $reply->author_id,
            'created_at' => $reply->created_at?->toIso8601String(),
            'updated_at' => $reply->updated_at?->toIso8601String(),
        ];
    }

    private static function tag(Tag $tag): array
    {
        return ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug ?? null];
    }

    /**
     * Never the visitor's token or email.
     */
    private static function chat(object $event): array
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

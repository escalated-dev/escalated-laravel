<?php

namespace Escalated\Laravel\Support;

/**
 * The event names from ADR decisions/2026-10-21-event-vocabulary.md.
 *
 * Escalated emits and offers only canonical names. Legacy names are still
 * accepted wherever stored data is read (a workflow's trigger_event, a
 * webhook's subscribed events) and are treated as their canonical name.
 */
final class EventVocabulary
{
    /**
     * Legacy name => canonical name.
     */
    public const LEGACY = [
        'ticket.replied' => 'reply.created',
        'internal_note.added' => 'note.created',
        'internal_note.created' => 'note.created',
        'tag.added' => 'ticket.tag_added',
        'ticket.tagged' => 'ticket.tag_added',
        'tag.removed' => 'ticket.tag_removed',
        'ticket.untagged' => 'ticket.tag_removed',
        'chat.session_started' => 'chat.started',
        'chat.session_ended' => 'chat.ended',
    ];

    public static function canonical(string $name): string
    {
        return self::LEGACY[$name] ?? $name;
    }

    /**
     * The canonical name plus every legacy name that means it.
     *
     * @return list<string>
     */
    public static function namesFor(string $canonical): array
    {
        return [$canonical, ...array_keys(self::LEGACY, $canonical, true)];
    }
}

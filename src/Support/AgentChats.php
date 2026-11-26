<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Models\ChatSession;
use Illuminate\Support\Str;

/**
 * The live chats an agent sees in the agent panel: their own active chats and
 * the chats waiting in the queue. Shared with every agent page as
 * `escalated.active_chats` / `escalated.chat_queue` while live chat is on, and
 * answered by the agent chat routes (escalated.agent.chat.active / .queue).
 */
final class AgentChats
{
    public const LIMIT = 25;

    /** @return list<array<string, mixed>> */
    public static function active(mixed $agent, int $limit = self::LIMIT): array
    {
        return ChatSession::active()
            ->forAgent($agent->getAuthIdentifier())
            ->whereHas('ticket')
            ->with(['ticket.latestReply', 'ticket.department', 'ticket.requester'])
            ->latest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChatSession $session) => self::present($session))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public static function waiting(int $limit = self::LIMIT): array
    {
        return ChatSession::waiting()
            ->whereHas('ticket')
            ->with(['ticket.latestReply', 'ticket.department', 'ticket.requester'])
            ->oldest('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (ChatSession $session) => self::present($session))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public static function present(ChatSession $session): array
    {
        $ticket = $session->ticket;
        $last = $ticket?->latestReply;
        $requester = $ticket?->requester_id !== null ? $ticket?->requester : null;

        return [
            'id' => $session->id,
            'status' => $session->status->value,
            'agent_id' => $session->agent_id,
            'ticket_id' => $ticket?->id,
            'ticket_reference' => $ticket?->reference,
            'subject' => $ticket?->subject,
            'customer_name' => $ticket?->guest_name ?? $requester?->name,
            'customer_email' => $ticket?->guest_email ?? $requester?->email,
            'department' => $ticket?->department ? ['id' => $ticket->department->id, 'name' => $ticket->department->name] : null,
            'first_message' => $ticket?->description !== null ? Str::limit((string) $ticket->description, 200) : null,
            'last_message' => $last ? Str::limit(strip_tags((string) $last->body), 200) : null,
            'last_message_at' => $last?->created_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'created_at' => ($session->started_at ?? $session->created_at)?->toIso8601String(),
        ];
    }
}

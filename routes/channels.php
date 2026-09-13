<?php

use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Escalated Broadcast Channels
|--------------------------------------------------------------------------
|
| These channels are registered when broadcasting is enabled
| (escalated.broadcasting.enabled = true). They authorize users to
| subscribe to private WebSocket channels for real-time ticket updates.
|
*/

// Whether $id is the user's id. Compared as strings: a host user may be keyed
// by UUID or ULID, and an (int) cast turns every ULID into 1.
$isUser = static fn ($user, mixed $id): bool => $id !== null
    && $id !== ''
    && (string) $user->getAuthIdentifier() === (string) $id;

// Whether the user raised the ticket. The requester is polymorphic, so the id
// identifies the user only together with the type.
$isRequester = static function ($user, Ticket $ticket) use ($isUser): bool {
    if (! $user instanceof Model || $ticket->requester_type === null) {
        return false;
    }

    $class = static fn (string $type): string => Relation::getMorphedModel($type) ?? $type;

    return $class($ticket->requester_type) === $class($user->getMorphClass())
        && $isUser($user, $ticket->requester_id);
};

// All tickets channel - agents and admins only
Broadcast::channel('escalated.tickets', function ($user) {
    return Gate::allows(config('escalated.authorization.agent_gate', 'escalated-agent'))
        || Gate::allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
});

// Individual ticket channel - agent/admin or the ticket requester
Broadcast::channel('escalated.tickets.{ticketId}', function ($user, $ticketId) use ($isRequester) {
    if (Gate::allows(config('escalated.authorization.agent_gate', 'escalated-agent'))
        || Gate::allows(config('escalated.authorization.admin_gate', 'escalated-admin'))) {
        return true;
    }

    $ticket = Ticket::find($ticketId);

    return $ticket !== null && $isRequester($user, $ticket);
});

// Agent-specific channel - only the agent themselves
Broadcast::channel('escalated.agents.{agentId}', function ($user, $agentId) use ($isUser) {
    return $isUser($user, $agentId);
});

// Chat session channel - assigned agent only (customer auth is handled via session token)
Broadcast::channel('escalated.chat.{sessionId}', function ($user, $sessionId) use ($isUser) {
    $session = ChatSession::find($sessionId);

    if (! $session) {
        return false;
    }

    // Agent assigned to the session
    if ($isUser($user, $session->agent_id)) {
        return true;
    }

    // Any agent/admin can view if not yet assigned
    return Gate::allows(config('escalated.authorization.agent_gate', 'escalated-agent'))
        || Gate::allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
});

// Chat queue channel - any agent/admin
Broadcast::channel('escalated.chat.queue', function ($user) {
    return Gate::allows(config('escalated.authorization.agent_gate', 'escalated-agent'))
        || Gate::allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
});

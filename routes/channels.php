<?php

use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
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

// All tickets channel - agents and admins only
Broadcast::channel('escalated.tickets', function ($user) {
    return Gate::allows(config('escalated.authorization.agent_gate', 'escalated-agent'))
        || Gate::allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
});

// Individual ticket channel - agent/admin or the ticket requester
Broadcast::channel('escalated.tickets.{ticketId}', function ($user, $ticketId) {
    if (Gate::allows(config('escalated.authorization.agent_gate', 'escalated-agent'))
        || Gate::allows(config('escalated.authorization.admin_gate', 'escalated-admin'))) {
        return true;
    }

    $ticket = Ticket::find($ticketId);

    return $ticket && (int) $ticket->requester_id === (int) $user->id;
});

// Agent-specific channel - only the agent themselves
Broadcast::channel('escalated.agents.{agentId}', function ($user, $agentId) {
    return (int) $user->id === (int) $agentId;
});

// Chat session channel - assigned agent only (customer auth is handled via session token)
Broadcast::channel('escalated.chat.{sessionId}', function ($user, $sessionId) {
    $session = ChatSession::find($sessionId);

    if (! $session) {
        return false;
    }

    // Agent assigned to the session
    if ($session->agent_id && (int) $session->agent_id === (int) $user->id) {
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

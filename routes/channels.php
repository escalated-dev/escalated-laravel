<?php

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

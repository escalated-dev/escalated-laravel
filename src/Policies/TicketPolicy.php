<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Gate;

class TicketPolicy
{
    public function viewAny($user): bool
    {
        return true;
    }

    public function view($user, Ticket $ticket): bool
    {
        if (Gate::forUser($user)->allows('escalated-agent') || Gate::forUser($user)->allows('escalated-admin')) {
            return true;
        }

        return $ticket->requester_id === $user->getKey()
            && $ticket->requester_type === $user->getMorphClass();
    }

    public function create($user): bool
    {
        return true;
    }

    public function update($user, Ticket $ticket): bool
    {
        return Gate::forUser($user)->allows('escalated-agent') || Gate::forUser($user)->allows('escalated-admin');
    }

    public function reply($user, Ticket $ticket): bool
    {
        if (Gate::forUser($user)->allows('escalated-agent') || Gate::forUser($user)->allows('escalated-admin')) {
            return true;
        }

        return $ticket->requester_id === $user->getKey()
            && $ticket->requester_type === $user->getMorphClass();
    }

    public function addNote($user, Ticket $ticket): bool
    {
        return Gate::forUser($user)->allows('escalated-agent') || Gate::forUser($user)->allows('escalated-admin');
    }

    public function assign($user, Ticket $ticket): bool
    {
        return Gate::forUser($user)->allows('escalated-agent') || Gate::forUser($user)->allows('escalated-admin');
    }

    public function close($user, Ticket $ticket): bool
    {
        if (Gate::forUser($user)->allows('escalated-agent') || Gate::forUser($user)->allows('escalated-admin')) {
            return true;
        }

        $isRequester = $ticket->requester_id === $user->getKey()
            && $ticket->requester_type === $user->getMorphClass();

        return $isRequester && config('escalated.tickets.allow_customer_close', false);
    }
}

<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class TicketPolicy
{
    public function viewAny($user): bool
    {
        return $this->canUse($user);
    }

    public function view($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }
        if (Gate::forUser($user)->allows(config('escalated.authorization.agent_gate', 'escalated-agent')) || Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin'))) {
            return true;
        }

        return $ticket->requester_id === $user->getKey()
            && $ticket->requester_type === $user->getMorphClass();
    }

    public function create($user): bool
    {
        return $this->canUse($user);
    }

    public function update($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }

        return Gate::forUser($user)->allows(config('escalated.authorization.agent_gate', 'escalated-agent')) || Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
    }

    public function reply($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }
        if (Gate::forUser($user)->allows(config('escalated.authorization.agent_gate', 'escalated-agent')) || Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin'))) {
            return true;
        }

        return $ticket->requester_id === $user->getKey()
            && $ticket->requester_type === $user->getMorphClass();
    }

    public function addNote($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }

        return Gate::forUser($user)->allows(config('escalated.authorization.agent_gate', 'escalated-agent')) || Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
    }

    public function assign($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }

        return Gate::forUser($user)->allows(config('escalated.authorization.agent_gate', 'escalated-agent')) || Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
    }

    public function close($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }
        if (Gate::forUser($user)->allows(config('escalated.authorization.agent_gate', 'escalated-agent')) || Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin'))) {
            return true;
        }

        $isRequester = $ticket->requester_id === $user->getKey()
            && $ticket->requester_type === $user->getMorphClass();

        return $isRequester && config('escalated.tickets.allow_customer_close', false);
    }

    private function canUse($user, ?Ticket $ticket = null): bool
    {
        $context = app(TenantContext::class);

        return ! $context->enabled() || ($user instanceof Model && $context->canAccess($user)
            && ($ticket === null || $context->owns($ticket)));
    }

    public function delete($user, Ticket $ticket): bool
    {
        return $this->canUse($user, $ticket)
            && Gate::forUser($user)->allows(config('escalated.authorization.admin_gate', 'escalated-admin'));
    }
}

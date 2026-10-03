<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

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
        if (StaffAccess::isStaff($user)) {
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

        return StaffAccess::isStaff($user);
    }

    public function reply($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }
        if (StaffAccess::isStaff($user)) {
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

        return StaffAccess::isStaff($user);
    }

    public function assign($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }

        return StaffAccess::isStaff($user);
    }

    public function close($user, Ticket $ticket): bool
    {
        if (! $this->canUse($user, $ticket)) {
            return false;
        }
        if (StaffAccess::isStaff($user)) {
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
            && StaffAccess::isAdmin($user);
    }
}

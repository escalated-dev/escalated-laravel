<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Support\StaffAccess;

class EscalationRulePolicy
{
    public function viewAny($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function view($user, EscalationRule $escalationRule): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function create($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function update($user, EscalationRule $escalationRule): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function delete($user, EscalationRule $escalationRule): bool
    {
        return StaffAccess::isAdmin($user);
    }
}

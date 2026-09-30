<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Support\StaffAccess;

class SlaPolicyPolicy
{
    public function viewAny($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function view($user, SlaPolicy $slaPolicy): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function create($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function update($user, SlaPolicy $slaPolicy): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function delete($user, SlaPolicy $slaPolicy): bool
    {
        return StaffAccess::isAdmin($user);
    }
}

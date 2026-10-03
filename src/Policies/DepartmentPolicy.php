<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Support\StaffAccess;

class DepartmentPolicy
{
    public function viewAny($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function view($user, Department $department): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function create($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function update($user, Department $department): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function delete($user, Department $department): bool
    {
        return StaffAccess::isAdmin($user);
    }
}

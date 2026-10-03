<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Support\StaffAccess;

class TagPolicy
{
    public function viewAny($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function view($user, Tag $tag): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function create($user): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function update($user, Tag $tag): bool
    {
        return StaffAccess::isAdmin($user);
    }

    public function delete($user, Tag $tag): bool
    {
        return StaffAccess::isAdmin($user);
    }
}

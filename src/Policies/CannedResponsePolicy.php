<?php

namespace Escalated\Laravel\Policies;

use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Support\StaffAccess;

class CannedResponsePolicy
{
    public function viewAny($user): bool
    {
        return StaffAccess::isAgent($user);
    }

    public function view($user, CannedResponse $cannedResponse): bool
    {
        return StaffAccess::isAgent($user);
    }

    public function create($user): bool
    {
        return StaffAccess::isAgent($user);
    }

    public function update($user, CannedResponse $cannedResponse): bool
    {
        if (! StaffAccess::isAgent($user)) {
            return false;
        }

        return $cannedResponse->is_shared || $cannedResponse->created_by === $user->getKey();
    }

    public function delete($user, CannedResponse $cannedResponse): bool
    {
        if (! StaffAccess::isAgent($user)) {
            return false;
        }

        return $cannedResponse->is_shared || $cannedResponse->created_by === $user->getKey();
    }
}

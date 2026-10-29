<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Exceptions\SsoException;
use Escalated\Laravel\Models\Role;
use Escalated\Laravel\Support\AccessFeatures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Maps an SSO identity to a host user: match by email (case-insensitive),
 * create one only when escalated.sso.create_users is on, and replace the
 * user's Escalated roles with the asserted role when roles & permissions and
 * escalated.sso.sync_roles are both on. SSO never touches the host's admin
 * or agent gates.
 */
class SsoUserResolver
{
    /**
     * @param  array{email: string, name: string, role: string}  $identity
     *
     * @throws SsoException
     */
    public function resolve(array $identity): Model
    {
        $email = $identity['email'];

        /** @var Model|null $user */
        $user = Escalated::userQuery()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();

        if (! $user) {
            if (! config('escalated.sso.create_users', false)) {
                throw new SsoException("SSO refused: no user has the email [{$email}] and escalated.sso.create_users is off.");
            }

            $class = Escalated::userModel();
            $user = new $class;
            $user->forceFill([
                'name' => $identity['name'] !== '' ? $identity['name'] : Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ])->save();
        }

        $this->syncRole($user, $identity['role']);

        return $user;
    }

    protected function syncRole(Model $user, string $roleSlug): void
    {
        if ($roleSlug === '' || ! AccessFeatures::rolesPermissions() || ! config('escalated.sso.sync_roles', false)) {
            return;
        }

        $role = Role::where('slug', $roleSlug)->first();
        if (! $role) {
            return;
        }

        Escalated::query(Escalated::table('role_user'))->where('user_id', $user->getKey())->delete();
        Escalated::query(Escalated::table('role_user'))->insert(['user_id' => $user->getKey(), 'role_id' => $role->id]);
    }
}

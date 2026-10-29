<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Role;
use Illuminate\Console\Command;

/**
 * Gives a user an Escalated role, so an install can bootstrap its first admin
 * before turning roles & permissions on (ESCALATED_ROLES_PERMISSIONS).
 */
class AssignRoleCommand extends Command
{
    protected $signature = 'escalated:roles:assign
        {user : The user\'s email address or ID}
        {role=admin : The role slug (admin, agent, light_agent or a custom role)}';

    protected $description = 'Give a user an Escalated role';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $user = Escalated::userQuery()->where('email', $identifier)->first()
            ?? (ctype_digit($identifier) || preg_match('/^[0-9a-f-]{36}$/i', $identifier)
                ? Escalated::userQuery()->find($identifier)
                : null);

        if (! $user) {
            $this->error("No user found for [{$identifier}].");

            return self::FAILURE;
        }

        $role = Role::where('slug', (string) $this->argument('role'))->first();

        if (! $role) {
            $this->error('No role ['.$this->argument('role').']. Seed them with: php artisan db:seed --class="Escalated\Laravel\Database\Seeders\PermissionSeeder"');

            return self::FAILURE;
        }

        $role->users()->syncWithoutDetaching([$user->getKey()]);

        $this->info("Gave {$identifier} the {$role->slug} role.");

        return self::SUCCESS;
    }
}

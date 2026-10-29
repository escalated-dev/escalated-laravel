<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\TwoFactor;
use Illuminate\Console\Command;

/** Clears a user's two-factor enrolment, for a lost device. */
class ResetTwoFactorCommand extends Command
{
    protected $signature = 'escalated:two-factor:reset {user : The user\'s email address or ID}';

    protected $description = 'Remove a user\'s two-factor enrolment so they can set it up again';

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

        $deleted = TwoFactor::where('user_id', $user->getKey())->delete();

        $this->info($deleted
            ? "Removed the two-factor enrolment for {$identifier}."
            : "{$identifier} had no two-factor enrolment.");

        return self::SUCCESS;
    }
}

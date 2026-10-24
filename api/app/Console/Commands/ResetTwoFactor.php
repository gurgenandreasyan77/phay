<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Security\TwoFactor;
use Illuminate\Console\Command;

/**
 * Last resort for someone who lost both their phone and their recovery codes
 * and has nobody above them to reset it (e.g. the only super admin): turns
 * their 2FA off and signs them out everywhere. Needs server access, and is
 * written to the security log.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'platform:two-factor:reset {email}';

    protected $description = "Turn off a login's 2FA (lost phone); they set it up again at the next sign-in";

    public function handle(TwoFactor $twoFactor): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No login with the email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        if (! $this->confirm("Turn off 2FA for {$user->email} and sign them out everywhere?", true)) {
            return self::FAILURE;
        }

        $twoFactor->disable($user, null, 'console');
        $this->info("2FA of {$user->email} is off; it will be asked to set up again at the next sign-in.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\SecurityEvent;
use App\Models\User;
use App\Security\AccountLinks;
use App\Security\SecurityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates the platform's first super admin, with no password anywhere: not
 * in .env, not in a seeder, not in shell history. It prints a one-time link
 * (valid security.bootstrap_ttl_minutes) where the person sets their own
 * password and then has to set up 2FA. Refuses once a super admin exists:
 * further staff are invited from the platform team page.
 *
 * Run again for the same email while the account isn't activated to get a
 * fresh link (e.g. the first one expired).
 */
class BootstrapSuperAdmin extends Command
{
    protected $signature = 'platform:bootstrap {email : Email of the first super admin} {--name=Admin : Display name}';

    protected $description = 'Create the first super admin and print a one-time activation link';

    public function handle(AccountLinks $links, SecurityLog $log): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("[{$email}] is not a valid email.");

            return self::FAILURE;
        }

        $existing = User::query()->where('is_super_admin', true)->get();
        $pending = $existing->first(fn (User $user) => $user->email === $email && ! $user->isActivated());

        if ($existing->isNotEmpty() && ! $pending) {
            $this->error('A super admin already exists. Invite more staff from the platform team page, or use platform:super-admin for an existing login.');

            return self::FAILURE;
        }

        if (! $pending && User::query()->where('email', $email)->exists()) {
            $this->error("A login with [{$email}] exists. Make it a super admin with: php artisan platform:super-admin {$email}");

            return self::FAILURE;
        }

        $user = $pending;

        if (! $user) {
            $user = User::query()->create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => null]);
            $user->forceFill(['is_super_admin' => true])->save();
            $log->record(SecurityEvent::SuperAdminBootstrapped, $user, ['via' => 'console']);
        }

        $link = $links->invite($user, null, (int) config('security.bootstrap_ttl_minutes'));

        $this->info("Super admin {$email} is ready to activate.");
        $this->line('Open this link (one time, until '.$link['expiresAt']->timezone('Asia/Yerevan')->format('d.m.Y H:i').' Yerevan), set a password and then 2FA:');
        $this->newLine();
        $this->line($link['url']);
        $this->newLine();
        $this->comment($link['emailed'] ? 'The link was also emailed.' : 'The email could not be sent; use the link above.');

        return self::SUCCESS;
    }
}

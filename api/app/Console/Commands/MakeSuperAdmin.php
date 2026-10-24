<?php

namespace App\Console\Commands;

use App\Enums\SecurityEvent;
use App\Models\User;
use App\Security\SecurityLog;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'platform:super-admin {email : Email of an existing login}';

    protected $description = 'Make an existing login a platform administrator (sees every business and the platform screens)';

    public function handle(SecurityLog $log): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No login with the email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $log->record(SecurityEvent::SuperAdminGranted, $user, ['via' => 'console']);
        $this->info("{$user->email} is now a platform administrator. They will have to set up 2FA at the next sign-in.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'platform:super-admin {email : Email of an existing login}';

    protected $description = 'Make an existing login a platform administrator (sees every business and the platform screens)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No login with the email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->info("{$user->email} is now a platform administrator.");

        return self::SUCCESS;
    }
}

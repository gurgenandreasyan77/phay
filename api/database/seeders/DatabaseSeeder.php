<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The platform needs no seeded content: plans come from migrations, the
 * super admin from `php artisan platform:bootstrap`; every business adds
 * its own content in the admin.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Servio's catalog, posts, market and calculator rates, written into the
 * default business. For a local copy of Servio only: a fresh platform
 * starts without any business content.
 *
 *     php artisan db:seed --class=ServioDemoSeeder
 */
class ServioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ServiceCategorySeeder::class,
            ServiceSeeder::class,
            AutoCareServiceSeeder::class,
            ServicePackageSeeder::class,
            PostSeeder::class,
            AutoCarePostSeeder::class,
            SiteSettingSeeder::class,
            AdditionalServiceSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            MarketTaskPageSeeder::class,
            CalculatorSettingSeeder::class,
        ]);
    }
}

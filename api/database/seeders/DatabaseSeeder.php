<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
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

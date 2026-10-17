<?php

namespace Database\Seeders;

use App\Models\ContactChannel;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::current()->update([
            'address' => [
                'hy' => 'Երևան, Հայաստան',
                'ru' => 'Ереван, Армения',
                'en' => 'Yerevan, Armenia',
            ],
        ]);

        ContactChannel::query()->updateOrCreate(
            ['type' => 'phone', 'value' => '+374 55 337633'],
            ['label' => null, 'is_primary' => true, 'sort_order' => 1, 'is_active' => true],
        );

        ContactChannel::query()->updateOrCreate(
            ['type' => 'email', 'value' => 'info@servio.am'],
            ['label' => null, 'is_primary' => false, 'sort_order' => 2, 'is_active' => true],
        );
    }
}

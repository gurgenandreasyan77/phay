<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        ServiceCategory::query()->updateOrCreate(
            ['slug' => 'cleaning'],
            [
                'icon' => 'spray-can',
                'sort_order' => 1,
                'name' => [
                    'hy' => 'Մաքրում',
                    'ru' => 'Уборка',
                    'en' => 'Cleaning',
                ],
                'description' => [
                    'hy' => 'Բնակարանների, տների և գրասենյակների մասնագիտական մաքրում՝ հարմարեցված ձեր կարիքներին։',
                    'ru' => 'Профессиональная уборка квартир, домов и офисов, подобранная под ваши потребности.',
                    'en' => 'Professional cleaning for apartments, houses and offices, tailored to your needs.',
                ],
            ]
        );

        ServiceCategory::query()->updateOrCreate(
            ['slug' => 'upholstery-care'],
            [
                'icon' => 'sofa',
                'sort_order' => 2,
                'name' => [
                    'hy' => 'Քիմմաքրում',
                    'ru' => 'Химчистка',
                    'en' => 'Upholstery Care',
                ],
                'description' => [
                    'hy' => 'Փափուկ կահույքի, ներքնակների և տեքստիլի մասնագիտական քիմմաքրում։',
                    'ru' => 'Профессиональная химчистка мягкой мебели, матрасов и текстиля.',
                    'en' => 'Professional dry cleaning for upholstered furniture, mattresses and textiles.',
                ],
            ]
        );

        ServiceCategory::query()->updateOrCreate(
            ['slug' => 'auto-care'],
            [
                'icon' => 'car',
                'sort_order' => 3,
                'name' => [
                    'hy' => 'Ավտոխնամք',
                    'ru' => 'Автоуход',
                    'en' => 'Auto Care',
                ],
                'description' => [
                    'hy' => 'Ավտոմեքենայի սրահի մասնագիտական քիմմաքրում և խնամք Երևանում։',
                    'ru' => 'Профессиональная химчистка и уход за автомобилем в Ереване.',
                    'en' => 'Professional car interior cleaning and auto care in Yerevan.',
                ],
            ]
        );
    }
}

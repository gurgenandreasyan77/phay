<?php

namespace Database\Seeders;

use App\Models\AdditionalService;
use Illuminate\Database\Seeder;

class AdditionalServiceSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'slug' => 'two-seat-sofa',
                'category' => 'upholstery',
                'name' => ['hy' => 'Երկտեղանի բազմոց', 'ru' => 'Двухместный диван', 'en' => 'Two-seat sofa'],
                'alt' => [
                    'hy' => 'Երկտեղանի բազմոցի քիմմաքրում',
                    'ru' => 'Химчистка двухместного дивана',
                    'en' => 'Two-seat sofa dry cleaning',
                ],
                'image' => '/images/services/additional/two-seat-sofa.webp',
                'price_amount' => 7000,
                'price_unit' => 'flat',
                'service_slug' => 'sofa-cleaning',
                'sort_order' => 1,
            ],
            [
                'slug' => 'three-seat-sofa',
                'category' => 'upholstery',
                'name' => ['hy' => 'Երեքտեղանի բազմոց', 'ru' => 'Трёхместный диван', 'en' => 'Three-seat sofa'],
                'alt' => [
                    'hy' => 'Երեքտեղանի բազմոցի քիմմաքրում',
                    'ru' => 'Химчистка трёхместного дивана',
                    'en' => 'Three-seat sofa dry cleaning',
                ],
                'image' => '/images/services/additional/three-seat-sofa.webp',
                'price_amount' => 9000,
                'price_unit' => 'flat',
                'service_slug' => 'sofa-cleaning',
                'sort_order' => 2,
            ],
            [
                'slug' => 'corner-sofa',
                'category' => 'upholstery',
                'name' => ['hy' => 'Անկյունային բազմոց', 'ru' => 'Угловой диван', 'en' => 'Corner sofa'],
                'alt' => [
                    'hy' => 'Անկյունային բազմոցի քիմմաքրում',
                    'ru' => 'Химчистка углового дивана',
                    'en' => 'Corner sofa dry cleaning',
                ],
                'image' => '/images/services/additional/corner-sofa.webp',
                'price_amount' => 14000,
                'price_unit' => 'flat',
                'service_slug' => 'sofa-cleaning',
                'sort_order' => 3,
            ],
            [
                'slug' => 'armchair',
                'category' => 'upholstery',
                'name' => ['hy' => 'Բազկաթոռ', 'ru' => 'Кресло', 'en' => 'Armchair'],
                'alt' => [
                    'hy' => 'Բազկաթոռի քիմմաքրում',
                    'ru' => 'Химчистка кресла',
                    'en' => 'Armchair dry cleaning',
                ],
                'image' => '/images/services/additional/armchair.webp',
                'price_amount' => 4000,
                'price_unit' => 'flat',
                'service_slug' => 'armchair-chair-cleaning',
                'sort_order' => 4,
            ],
            [
                'slug' => 'upholstered-chair',
                'category' => 'upholstery',
                'name' => ['hy' => 'Փափուկ աթոռ', 'ru' => 'Мягкий стул', 'en' => 'Upholstered chair'],
                'alt' => [
                    'hy' => 'Փափուկ աթոռի քիմմաքրում',
                    'ru' => 'Химчистка мягкого стула',
                    'en' => 'Upholstered chair dry cleaning',
                ],
                'image' => '/images/services/additional/upholstered-chair.webp',
                'price_amount' => 1200,
                'price_unit' => 'flat',
                'service_slug' => 'armchair-chair-cleaning',
                'sort_order' => 5,
            ],
            [
                'slug' => 'single-mattress',
                'category' => 'textile',
                'name' => ['hy' => 'Մեկտեղանի ներքնակ', 'ru' => 'Односпальный матрас', 'en' => 'Single mattress'],
                'note' => ['hy' => 'մեկ կողմը', 'ru' => 'одна сторона', 'en' => 'one side'],
                'alt' => [
                    'hy' => 'Մեկտեղանի ներքնակի քիմմաքրում',
                    'ru' => 'Химчистка односпального матраса',
                    'en' => 'Single mattress dry cleaning',
                ],
                'image' => '/images/services/additional/single-mattress.webp',
                'price_amount' => 5000,
                'price_unit' => 'flat',
                'service_slug' => 'mattress-cleaning',
                'sort_order' => 6,
            ],
            [
                'slug' => 'double-mattress',
                'category' => 'textile',
                'name' => ['hy' => 'Երկտեղանի ներքնակ', 'ru' => 'Двуспальный матрас', 'en' => 'Double mattress'],
                'note' => ['hy' => 'մեկ կողմը', 'ru' => 'одна сторона', 'en' => 'one side'],
                'alt' => [
                    'hy' => 'Երկտեղանի ներքնակի քիմմաքրում',
                    'ru' => 'Химчистка двуспального матраса',
                    'en' => 'Double mattress dry cleaning',
                ],
                'image' => '/images/services/additional/double-mattress.webp',
                'price_amount' => 8000,
                'price_unit' => 'flat',
                'service_slug' => 'mattress-cleaning',
                'sort_order' => 7,
            ],
            [
                'slug' => 'carpet-cleaning',
                'category' => 'textile',
                'name' => ['hy' => 'Գորգի քիմմաքրում', 'ru' => 'Химчистка ковра', 'en' => 'Carpet cleaning'],
                'alt' => [
                    'hy' => 'Գորգի քիմմաքրում',
                    'ru' => 'Химчистка ковра',
                    'en' => 'Carpet cleaning',
                ],
                'image' => '/images/services/additional/carpet-cleaning.webp',
                'price_amount' => 1000,
                'price_unit' => 'sqm',
                'service_slug' => 'carpet-textile-cleaning',
                'sort_order' => 8,
            ],
            [
                'slug' => 'standard-window',
                'category' => 'windows',
                'name' => ['hy' => 'Ստանդարտ պատուհան', 'ru' => 'Стандартное окно', 'en' => 'Standard window'],
                'alt' => [
                    'hy' => 'Ստանդարտ պատուհանի լվացում',
                    'ru' => 'Мойка стандартного окна',
                    'en' => 'Standard window cleaning',
                ],
                'image' => '/images/services/additional/standard-window.webp',
                'price_amount' => 3500,
                'price_unit' => 'flat',
                'sort_order' => 9,
            ],
            [
                'slug' => 'panoramic-window',
                'category' => 'windows',
                'name' => [
                    'hy' => 'Բարդ և պանորամային պատուհաններ',
                    'ru' => 'Сложные и панорамные окна',
                    'en' => 'Complex and panoramic windows',
                ],
                'alt' => [
                    'hy' => 'Պանորամային պատուհանների լվացում',
                    'ru' => 'Мойка панорамных окон',
                    'en' => 'Panoramic window cleaning',
                ],
                'image' => '/images/services/additional/panoramic-window.webp',
                'price_amount' => 1200,
                'price_unit' => 'sqm',
                'sort_order' => 10,
            ],
            [
                'slug' => 'glass-facade',
                'category' => 'windows',
                'name' => ['hy' => 'Վիտրաժների լվացում', 'ru' => 'Мойка витражей', 'en' => 'Glass facade cleaning'],
                'alt' => [
                    'hy' => 'Վիտրաժի լվացում',
                    'ru' => 'Мойка витража',
                    'en' => 'Glass facade cleaning',
                ],
                'image' => '/images/services/additional/glass-facade.webp',
                'price_amount' => 800,
                'price_unit' => 'sqm',
                'sort_order' => 11,
            ],
        ];

        foreach ($items as $item) {
            AdditionalService::query()->updateOrCreate(['slug' => $item['slug']], $item);
        }
    }
}

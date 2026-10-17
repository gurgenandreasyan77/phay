<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use Illuminate\Database\Seeder;

/**
 * All three packages are seeded Draft with price_type "on_inspection" —
 * no price is invented. The owner sets real pricing/duration from the
 * admin panel before publishing any of them.
 */
class ServicePackageSeeder extends Seeder
{
    public function run(): void
    {
        $autoCare = ServiceCategory::query()->where('slug', 'auto-care')->firstOrFail();

        $packages = [
            [
                'slug' => 'local-cleaning',
                'status' => 'draft',
                'sort_order' => 1,
                'name' => [
                    'hy' => 'Տեղային քիմմաքրում',
                    'ru' => 'Локальная химчистка',
                    'en' => 'Local Cleaning',
                ],
                'short_description' => [
                    'hy' => 'Համար այն դեպքերի, երբ անհրաժեշտ է մաքրել միայն կոնկրետ տարր՝ առանց ամբողջ սրահի մշակման։',
                    'ru' => 'Для случаев, когда нужно почистить только конкретный элемент, без обработки всего салона.',
                    'en' => 'For when only a specific element needs cleaning, without a full interior job.',
                ],
                'description' => [
                    'hy' => 'Տեղային փաթեթը ներառում է մեկ կամ մի քանի կոնկրետ տարրի մաքրում՝ ըստ ընտրության։ Հարմար է, երբ սրահի մնացած մասը լրացուցիչ մաքրման կարիք չունի։',
                    'ru' => 'Локальный пакет включает чистку одного или нескольких конкретных элементов по выбору клиента. Подходит, когда остальной салон не нуждается в дополнительной чистке.',
                    'en' => 'The local package covers cleaning one or a few chosen elements. Suited to cases where the rest of the interior doesn\'t need extra work.',
                ],
                'included_items' => [
                    'hy' => ['Մեկ կամ մի քանի նստատեղ', 'Տեղային բիծ', 'Գորգիկներ', 'Բեռնախցիկ', 'Մանկական նստատեղ', 'Հատակի առանձին հատված'],
                    'ru' => ['Одно или несколько сидений', 'Локальное пятно', 'Коврики', 'Багажник', 'Детское кресло', 'Отдельный участок пола'],
                    'en' => ['One or a few seats', 'A local stain', 'Floor mats', 'Trunk', 'Child seat', 'A specific section of the floor'],
                ],
                'price_type' => 'on_inspection',
                'category_id' => $autoCare->id,
            ],
            [
                'slug' => 'full-interior-cleaning',
                'status' => 'draft',
                'sort_order' => 2,
                'name' => [
                    'hy' => 'Ամբողջական քիմմաքրում',
                    'ru' => 'Полная химчистка салона',
                    'en' => 'Full Interior Cleaning',
                ],
                'short_description' => [
                    'hy' => 'Ամբողջ սրահի համալիր մշակում՝ նախնական մաքրումից մինչև ավարտական ստուգում։',
                    'ru' => 'Комплексная обработка всего салона — от предварительной уборки до финальной проверки.',
                    'en' => 'A comprehensive treatment of the whole interior, from initial cleaning to the final check.',
                ],
                'description' => [
                    'hy' => 'Ամբողջական փաթեթը ընդգրկում է սրահի բոլոր հիմնական տարրերը՝ նստատեղեր, հատակ, գորգիկներ, դռների պանելներ, պլաստիկ և բեռնախցիկ։',
                    'ru' => 'Полный пакет охватывает все основные элементы салона — сиденья, пол, коврики, дверные карты, пластик и багажник.',
                    'en' => 'The full package covers every major interior element — seats, floor, mats, door panels, plastic trim and trunk.',
                ],
                'included_items' => [
                    'hy' => ['Չոր մաքրում', 'Նստատեղեր', 'Հատակ և կարպետ', 'Գորգիկներ', 'Դռների պանելներ', 'Պլաստիկ', 'Բեռնախցիկ', 'Տեղային բծերի մշակում', 'Ավարտական ստուգում'],
                    'ru' => ['Сухая уборка', 'Сиденья', 'Пол и ковролин', 'Коврики', 'Дверные карты', 'Пластик', 'Багажник', 'Локальная обработка пятен', 'Финальная проверка'],
                    'en' => ['Dry cleaning pass', 'Seats', 'Floor and carpet', 'Mats', 'Door panels', 'Plastic trim', 'Trunk', 'Spot stain treatment', 'Final check'],
                ],
                'price_type' => 'on_inspection',
                'category_id' => $autoCare->id,
            ],
            [
                'slug' => 'premium-interior-detail',
                'status' => 'draft',
                'sort_order' => 3,
                'name' => [
                    'hy' => 'Premium Interior Detail',
                    'ru' => 'Premium Interior Detail',
                    'en' => 'Premium Interior Detail',
                ],
                'short_description' => [
                    'hy' => 'Ամենախորը փաթեթը՝ ամբողջական քիմմաքրումից մինչև կաշվի խնամք և հոտի նեյտրալացում։',
                    'ru' => 'Самый глубокий пакет — от полной химчистки до ухода за кожей и нейтрализации запаха.',
                    'en' => 'The most thorough package — from a full interior clean to leather care and odor neutralizing.',
                ],
                'description' => [
                    'hy' => 'Premium փաթեթը ներառում է ամբողջական քիմմաքրման բոլոր փուլերը՝ գումարած դժվարամատչելի հատվածների մանրակրկիտ մաքրում, կաշվի խնամք (առկայության դեպքում), հոտի նեյտրալացում համապատասխան մեթոդով և արդյունքի լուսանկարային ֆիքսում։',
                    'ru' => 'Premium-пакет включает все этапы полной химчистки, плюс детальную очистку труднодоступных мест, уход за кожей (если есть), нейтрализацию запаха подходящим методом и фотофиксацию результата.',
                    'en' => 'The Premium package includes every stage of a full interior clean, plus detailed cleaning of hard-to-reach areas, leather care (where present), odor neutralizing with a suitable method, and photo documentation of the result.',
                ],
                'included_items' => [
                    'hy' => ['Ամբողջական քիմմաքրում', 'Դժվարամատչելի հատվածների մանրակրկիտ մաքրում', 'Պլաստիկի խորացված մշակում', 'Կաշվի խնամք (առկայության դեպքում)', 'Հոտի նեյտրալացում', 'Լրացուցիչ ավարտական մշակում', 'Արդյունքի լուսանկարային ֆիքսում'],
                    'ru' => ['Полная химчистка салона', 'Детальная очистка труднодоступных мест', 'Более глубокая работа с пластиком', 'Уход за кожей (если есть)', 'Нейтрализация запахов подходящим методом', 'Дополнительная финальная обработка', 'Фотофиксация результата'],
                    'en' => ['Full interior clean', 'Detailed cleaning of hard-to-reach areas', 'Deeper plastic trim treatment', 'Leather care (where present)', 'Odor neutralizing with a suitable method', 'Extra finishing treatment', 'Photo documentation of the result'],
                ],
                'price_type' => 'on_inspection',
                'category_id' => $autoCare->id,
            ],
        ];

        foreach ($packages as $data) {
            ServicePackage::query()->updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}

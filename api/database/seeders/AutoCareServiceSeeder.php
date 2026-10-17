<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

/**
 * Servio currently only offers car interior cleaning and detailing — no
 * exterior/body services (polishing, coatings, headlight restoration, etc.)
 * are seeded here. All services below are Active with no price set, so the
 * public price list shows them with "price on request" until the owner
 * confirms real prices per service. Warnings/duration/drying-time text
 * intentionally avoids absolute claims ("removes any stain", "kills all
 * bacteria") per the no-fabricated-promises requirement.
 */
class AutoCareServiceSeeder extends Seeder
{
    public function run(): void
    {
        $category = ServiceCategory::query()->where('slug', 'auto-care')->firstOrFail();

        $warningsCommon = [
            'hy' => [
                'Արդյունքը կախված է նյութից, բծի ծագումից և տարիքից. շատ հին կամ խորը ներծծված բծերը միշտ չէ, որ ամբողջությամբ հեռացվում են',
                'Մշակումից առաջ կատարվում է թեստ՝ չտեսանելի հատվածում, նյութի արձագանքը ստուգելու համար',
                'Վերջնական մեթոդը որոշվում է տեղում զննումից հետո',
            ],
            'ru' => [
                'Результат зависит от материала, происхождения и возраста загрязнения — очень старые или глубоко въевшиеся пятна не всегда удаляются полностью',
                'Перед основной обработкой проводится тест на незаметном участке, чтобы проверить реакцию материала',
                'Итоговый метод определяется после осмотра на месте',
            ],
            'en' => [
                'The result depends on the material, origin and age of the stain — very old or deeply set stains are not always fully removable',
                'A test is run on a hidden area before the main treatment to check how the material reacts',
                'The final method is decided after an on-site inspection',
            ],
        ];

        $dryingTimeGeneric = [
            'hy' => 'Չորացման ժամանակը կախված է նյութից, եղանակից, օդափոխությունից և մաքրման խորությունից. ճշգրիտ ժամանակը նշվում է տեղում։',
            'ru' => 'Время высыхания зависит от материала, погоды, вентиляции и глубины очистки — точное время сообщается на месте.',
            'en' => 'Drying time depends on the material, weather, ventilation and how deep the cleaning was — the exact time is given on site.',
        ];

        $allCarTypes = ['compact', 'sedan', 'wagon', 'crossover', 'suv', 'minivan', 'minibus', 'commercial'];

        $services = [
            // ---- Ready (Draft) services -------------------------------------------------
            [
                'slug' => 'car-interior-cleaning',
                'icon' => 'car',
                'sort_order' => 1,
                'status' => 'active',
                'name' => [
                    'hy' => 'Ավտոմեքենայի սրահի ամբողջական քիմմաքրում',
                    'ru' => 'Полная химчистка салона автомобиля',
                    'en' => 'Full Car Interior Cleaning',
                ],
                'short_description' => [
                    'hy' => 'Սրահի համալիր մաքրում՝ նստատեղեր, հատակ, դռների պանելներ և պլաստիկ մակերեսներ։',
                    'ru' => 'Комплексная химчистка салона: сиденья, пол, дверные карты и пластиковые поверхности.',
                    'en' => 'Complete interior cleaning covering seats, floor, door panels and plastic surfaces.',
                ],
                'description' => [
                    'hy' => 'Ամբողջական քիմմաքրումը ներառում է սրահի բոլոր հիմնական տարրերի մշակում՝ նստատեղեր, հատակի ծածկույթ, դռների պանելներ, պլաստիկ և դժվարամատչելի հատվածներ։ Օգտագործվող միջոցն ընտրվում է նյութի տեսակին համապատասխան՝ ախտահանում, բարդ ախտորոշում կամ դժվար հասանելի հատվածների մշակում իրականացնելուց առաջ։',
                    'ru' => 'Полная химчистка охватывает все основные элементы салона — сиденья, напольное покрытие, дверные карты, пластик и труднодоступные зоны. Состав для обработки подбирается по типу материала после осмотра автомобиля.',
                    'en' => 'Full interior cleaning covers every major surface — seats, floor covering, door panels, plastic trim and hard-to-reach spots. The cleaning agent is matched to the material after inspecting the car.',
                ],
                'seo_title' => [
                    'hy' => 'Ավտոմեքենայի սրահի քիմմաքրում Երևանում | Servio',
                    'ru' => 'Химчистка салона автомобиля в Ереване | Servio',
                    'en' => 'Car Interior Cleaning in Yerevan | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի սրահի մասնագիտական քիմմաքրում Երևանում՝ նստատեղեր, հատակ, պլաստիկ։ Նախնական գնահատում լուսանկարներով։',
                    'ru' => 'Профессиональная химчистка салона автомобиля в Ереване: сиденья, пол, пластик. Предварительная оценка стоимости по фотографиям.',
                    'en' => 'Professional car interior cleaning in Yerevan — seats, floor, plastic trim. Preliminary estimate from photos.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է այն վարորդների համար, ում սրահը կուտակել է առօրյա կեղտ, փոշի կամ հոտեր և կարիք ունի համալիր թարմացման։',
                    'ru' => 'Подходит владельцам, чей салон накопил повседневную грязь, пыль или запахи и нуждается в комплексном обновлении.',
                    'en' => 'Suited to owners whose interior has built up everyday dirt, dust or odors and needs a full refresh.',
                ],
                'included_items' => [
                    'hy' => ['Նստատեղերի քիմմաքրում', 'Հատակի և ծածկույթի մշակում', 'Դռների պանելների մաքրում', 'Պլաստիկ մակերեսների մաքրում', 'Ավարտական զննում'],
                    'ru' => ['Химчистка сидений', 'Обработка пола и покрытия', 'Чистка дверных карт', 'Чистка пластиковых поверхностей', 'Финальная проверка'],
                    'en' => ['Seat cleaning', 'Floor and carpet treatment', 'Door panel cleaning', 'Plastic surface cleaning', 'Final inspection'],
                ],
                'excluded_items' => [
                    'hy' => ['Կուզովի արտաքին մաքրում', 'Կաշվի պաշտպանիչ ծածկույթ (պատվիրվում է առանձին)', 'Ապակիների անձրևադիմացկուն մշակում'],
                    'ru' => ['Наружная мойка кузова', 'Защитное покрытие кожи (заказывается отдельно)', 'Антидождь для стёкол'],
                    'en' => ['Exterior body wash', 'Leather protective coating (booked separately)', 'Rain repellent for glass'],
                ],
                'process_steps' => [
                    'hy' => ['Ավտոմեքենայի զննում և նյութերի որոշում', 'Նախնական չոր մաքրում', 'Բծերի տեղային մշակում', 'Նստատեղերի, հատակի և պանելների քիմմաքրում', 'Մնացորդների էքստրակցիա', 'Չորացում և ավարտական ստուգում'],
                    'ru' => ['Осмотр автомобиля и определение материалов', 'Предварительная сухая уборка', 'Локальная обработка пятен', 'Химчистка сидений, пола и панелей', 'Экстракция остатков состава', 'Сушка и финальная проверка'],
                    'en' => ['Inspecting the car and identifying materials', 'Initial dry cleaning pass', 'Spot-treating stains', 'Cleaning seats, floor and panels', 'Extracting residue', 'Drying and a final check'],
                ],
                'equipment' => [
                    'hy' => 'Էքստրակտոր, մասնագիտական փոշեկուլ և նյութին համապատասխան ընտրված մաքրող միջոցներ։',
                    'ru' => 'Экстрактор, профессиональный пылесос и чистящие средства, подобранные под материал.',
                    'en' => 'An extractor, a professional vacuum and cleaning agents matched to the material.',
                ],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Ավտոմեքենայի տեսակը', 'Նստատեղերի քանակը', 'Աղտոտվածության աստիճանը', 'Նյութի տեսակը'],
                    'ru' => ['Тип автомобиля', 'Количество сидений', 'Степень загрязнения', 'Тип материала'],
                    'en' => ['Car body type', 'Number of seats', 'Soiling level', 'Material type'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Որքա՞ն է տևում ամբողջական քիմմաքրումը', 'answer' => 'Տևողությունը կախված է սրահի չափից և աղտոտվածությունից, ճշգրիտ ժամանակը հաստատվում է զննումից հետո։']],
                    'ru' => [['question' => 'Сколько занимает полная химчистка', 'answer' => 'Длительность зависит от размера салона и степени загрязнения, точное время подтверждается после осмотра.']],
                    'en' => [['question' => 'How long does a full interior clean take', 'answer' => 'Duration depends on interior size and soiling level; the exact time is confirmed after inspection.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'leather', 'alcantara', 'mixed', 'plastic'],
                'contamination_types' => ['dust', 'food_drinks', 'coffee', 'grease_oil', 'pet_hair', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'interior-detailing',
                'icon' => 'sparkles',
                'sort_order' => 2,
                'status' => 'active',
                'name' => [
                    'hy' => 'Սրահի դետեյլինգ',
                    'ru' => 'Детейлинг салона',
                    'en' => 'Interior Detailing',
                ],
                'short_description' => [
                    'hy' => 'Ամբողջական քիմմաքրումից ավելի մանրակրկիտ մշակում՝ ներառյալ դժվարամատչելի հատվածները և կաշվի խնամքը։',
                    'ru' => 'Более тщательная обработка, чем полная химчистка, включая труднодоступные места и уход за кожей.',
                    'en' => 'A more meticulous pass than a full interior clean, covering hard-to-reach spots and leather care.',
                ],
                'description' => [
                    'hy' => 'Սրահի դետեյլինգը ներառում է ամբողջական քիմմաքրման բոլոր փուլերը՝ գումարած ավելի խորը աշխատանք դժվարամատչելի հատվածների, կարերի և կաշվե տարրերի հետ, եթե դրանք առկա են։ Հարմար է նրանց համար, ովքեր ցանկանում են առավել բարձր մակարդակի արդյունք։',
                    'ru' => 'Детейлинг салона включает все этапы полной химчистки плюс более глубокую работу с труднодоступными местами, швами и кожаными элементами, если они есть. Подходит тем, кто хочет более высокий уровень результата.',
                    'en' => 'Interior detailing includes every stage of a full interior clean plus deeper work on hard-to-reach areas, seams and leather elements where present — for a higher standard of result.',
                ],
                'seo_title' => [
                    'hy' => 'Ավտոդետեյլինգ Երևանում՝ սրահի խնամք | Servio',
                    'ru' => 'Детейлинг салона автомобиля в Ереване | Servio',
                    'en' => 'Car Interior Detailing in Yerevan | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Մասնագիտական ավտոդետեյլինգ Երևանում՝ սրահի մանրակրկիտ խնամք և կաշվի մշակում։',
                    'ru' => 'Профессиональный детейлинг салона в Ереване: тщательная проработка и уход за кожей.',
                    'en' => 'Professional interior detailing in Yerevan — meticulous care including leather.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է նրանց համար, ում սրահին անհրաժեշտ է ավելի բարձր մակարդակի խնամք, քան ստանդարտ քիմմաքրումը։',
                    'ru' => 'Подходит тем, чей салон нуждается в более высоком уровне ухода, чем стандартная химчистка.',
                    'en' => 'Suited to interiors that need a higher level of care than a standard clean.',
                ],
                'included_items' => [
                    'hy' => ['Ամբողջական քիմմաքրման բոլոր փուլերը', 'Դժվարամատչելի հատվածների մանրակրկիտ մշակում', 'Կարերի և հոդակապերի մաքրում', 'Կաշվե տարրերի խնամք (առկայության դեպքում)'],
                    'ru' => ['Все этапы полной химчистки', 'Тщательная проработка труднодоступных мест', 'Чистка швов и стыков', 'Уход за кожаными элементами (если есть)'],
                    'en' => ['Every stage of a full interior clean', 'Meticulous work on hard-to-reach areas', 'Seam and joint cleaning', 'Leather element care (where present)'],
                ],
                'excluded_items' => [
                    'hy' => ['Կաշվի պաշտպանիչ ծածկույթ (առանձին ծառայություն)', 'Կուզովի դետեյլինգ'],
                    'ru' => ['Защитное покрытие кожи (отдельная услуга)', 'Наружный детейлинг кузова'],
                    'en' => ['Leather protective coating (separate service)', 'Exterior body detailing'],
                ],
                'process_steps' => [
                    'hy' => ['Զննում և նյութերի որոշում', 'Ամբողջական քիմմաքրում', 'Դժվարամատչելի հատվածների առանձին մշակում', 'Կաշվե տարրերի խնամք', 'Ավարտական ստուգում'],
                    'ru' => ['Осмотр и определение материалов', 'Полная химчистка', 'Отдельная проработка труднодоступных зон', 'Уход за кожаными элементами', 'Финальная проверка'],
                    'en' => ['Inspection and material assessment', 'Full interior clean', 'Separate pass over hard-to-reach zones', 'Leather element care', 'Final check'],
                ],
                'equipment' => [
                    'hy' => 'Էքստրակտոր, մանրաթել խոզանակներ դժվարամատչելի հատվածների համար և կաշվի խնամքի միջոցներ։',
                    'ru' => 'Экстрактор, щётки для труднодоступных мест и средства по уходу за кожей.',
                    'en' => 'An extractor, detail brushes for tight spots and leather-care products.',
                ],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Ավտոմեքենայի տեսակը', 'Կաշվե տարրերի առկայությունը', 'Աղտոտվածության աստիճանը'],
                    'ru' => ['Тип автомобиля', 'Наличие кожаных элементов', 'Степень загрязнения'],
                    'en' => ['Car body type', 'Presence of leather elements', 'Soiling level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Ինչո՞վ է դետեյլինգը տարբերվում սովորական քիմմաքրումից', 'answer' => 'Դետեյլինգը ներառում է ավելի խորը աշխատանք դժվարամատչելի հատվածների ու կաշվե տարրերի հետ։']],
                    'ru' => [['question' => 'Чем детейлинг отличается от обычной химчистки', 'answer' => 'Детейлинг включает более глубокую проработку труднодоступных мест и кожаных элементов.']],
                    'en' => [['question' => 'How is detailing different from a standard clean', 'answer' => 'Detailing includes deeper work on hard-to-reach areas and leather elements.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'leather', 'alcantara', 'mixed', 'plastic'],
                'contamination_types' => ['dust', 'food_drinks', 'grease_oil', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'fabric-seat-cleaning',
                'icon' => 'sofa',
                'sort_order' => 3,
                'status' => 'active',
                'name' => ['hy' => 'Տեքստիլ նստատեղերի քիմմաքրում', 'ru' => 'Химчистка тканевых сидений', 'en' => 'Fabric Seat Cleaning'],
                'short_description' => [
                    'hy' => 'Տեքստիլ նստատեղերի խորը մաքրում՝ բծերից և կուտակված փոշուց ազատվելու համար։',
                    'ru' => 'Глубокая очистка тканевых сидений от пятен и накопившейся пыли.',
                    'en' => 'Deep cleaning of fabric seats to lift stains and built-up dust.',
                ],
                'description' => [
                    'hy' => 'Տեքստիլ նստատեղերը մշակվում են էքստրակցիայի եղանակով՝ օգտագործելով նյութին հարմար pH-չեզոք միջոցներ։ Հատուկ ուշադրություն է դարձվում կարերին և բարձերին։',
                    'ru' => 'Тканевые сиденья обрабатываются методом экстракции с pH-нейтральными средствами, подобранными под материал. Особое внимание уделяется швам и подголовникам.',
                    'en' => 'Fabric seats are cleaned by extraction using pH-neutral products matched to the material, with extra attention to seams and headrests.',
                ],
                'seo_title' => ['hy' => 'Նստատեղերի քիմմաքրում Երևանում | Servio', 'ru' => 'Химчистка сидений автомобиля в Ереване | Servio', 'en' => 'Car Seat Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի տեքստիլ նստատեղերի մասնագիտական քիմմաքրում Երևանում։',
                    'ru' => 'Профессиональная химчистка тканевых сидений автомобиля в Ереване.',
                    'en' => 'Professional fabric car seat cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է տեքստիլ նստատեղերով ավտոմեքենաների համար, որոնց վրա կան բծեր կամ կուտակված կեղտ։',
                    'ru' => 'Подходит автомобилям с тканевыми сиденьями, на которых есть пятна или накопленная грязь.',
                    'en' => 'Suited to cars with fabric seats carrying stains or built-up dirt.',
                ],
                'included_items' => [
                    'hy' => ['Նախնական փոշեկուլում', 'Բծերի տեղային մշակում', 'Էքստրակցիայի եղանակով մաքրում', 'Չորացում'],
                    'ru' => ['Предварительный пылесос', 'Локальная обработка пятен', 'Очистка методом экстракции', 'Сушка'],
                    'en' => ['Initial vacuuming', 'Spot stain treatment', 'Extraction cleaning', 'Drying'],
                ],
                'excluded_items' => [
                    'hy' => ['Կաշվե կամ ալկանտարա նստատեղերի մշակում', 'Հատակի կամ դռների պանելների մաքրում'],
                    'ru' => ['Обработка кожаных или алькантара сидений', 'Чистка пола или дверных карт'],
                    'en' => ['Leather or alcantara seat treatment', 'Floor or door panel cleaning'],
                ],
                'process_steps' => [
                    'hy' => ['Նյութի զննում', 'Չոր փոշեկուլում', 'Բծերի նախնական մշակում', 'Էքստրակցիա', 'Չորացում'],
                    'ru' => ['Осмотр материала', 'Сухой пылесос', 'Предобработка пятен', 'Экстракция', 'Сушка'],
                    'en' => ['Material inspection', 'Dry vacuuming', 'Pre-treating stains', 'Extraction', 'Drying'],
                ],
                'equipment' => ['hy' => 'Էքստրակտոր և տեքստիլի համար նախատեսված մաքրող միջոցներ։', 'ru' => 'Экстрактор и средства для текстиля.', 'en' => 'An extractor and fabric-specific cleaning products.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Նստատեղերի քանակը', 'Աղտոտվածության աստիճանը'],
                    'ru' => ['Количество сидений', 'Степень загрязнения'],
                    'en' => ['Number of seats', 'Soiling level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եմ պատվիրել միայն առջևի նստատեղերը', 'answer' => 'Այո, կարող եք ընտրել կոնկրետ նստատեղերի քանակը հայտ թողնելիս։']],
                    'ru' => [['question' => 'Можно ли заказать только передние сиденья', 'answer' => 'Да, можно указать нужное количество сидений при оформлении заявки.']],
                    'en' => [['question' => 'Can I book just the front seats', 'answer' => 'Yes, you can specify the number of seats when booking.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric'],
                'contamination_types' => ['dust', 'food_drinks', 'coffee', 'pet_hair', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'leather-seat-cleaning',
                'icon' => 'shield',
                'sort_order' => 4,
                'status' => 'active',
                'name' => ['hy' => 'Կաշվե նստատեղերի մաքրում և խնամք', 'ru' => 'Очистка и уход за кожаными сиденьями', 'en' => 'Leather Seat Cleaning & Care'],
                'short_description' => [
                    'hy' => 'Կաշվե նստատեղերի մաքրում և կերակրում՝ արտաքին տեսքը պահպանելու համար։',
                    'ru' => 'Очистка и питание кожаных сидений для сохранения внешнего вида.',
                    'en' => 'Cleaning and conditioning leather seats to help preserve their look.',
                ],
                'description' => [
                    'hy' => 'Կաշվե նստատեղերը պահանջում են այլ մոտեցում, քան տեքստիլը. մշակումը ներառում է մեղմ մաքրում և կաշվին հարմարեցված կերակրող միջոցի կիրառում։ Մեթոդը որոշվում է կաշվի տեսակից ելնելով։',
                    'ru' => 'Кожаные сиденья требуют иного подхода, чем ткань: обработка включает бережную очистку и нанесение подходящего кондиционера. Метод подбирается в зависимости от типа кожи.',
                    'en' => 'Leather seats need a different approach than fabric: cleaning is gentler and followed by a suitable conditioner. The method depends on the leather type.',
                ],
                'seo_title' => ['hy' => 'Կաշվե սրահի խնամք Երևանում | Servio', 'ru' => 'Чистка кожи автомобиля в Ереване | Servio', 'en' => 'Leather Car Seat Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի կաշվե նստատեղերի մասնագիտական մաքրում և խնամք Երևանում։',
                    'ru' => 'Профессиональная чистка и уход за кожаными сиденьями автомобиля в Ереване.',
                    'en' => 'Professional leather car seat cleaning and care in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է կաշվե սրահով ավտոմեքենաների համար, որոնց կաշին կորցրել է թարմ տեսքը կամ ունի մակերեսային կեղտ։',
                    'ru' => 'Подходит автомобилям с кожаным салоном, чья кожа потеряла свежий вид или имеет поверхностные загрязнения.',
                    'en' => 'Suited to leather interiors that have lost their fresh look or carry surface dirt.',
                ],
                'included_items' => [
                    'hy' => ['Կաշվի մակերեսի մաքրում', 'Կարերի և ծալքերի մշակում', 'Կերակրող միջոցի կիրառում'],
                    'ru' => ['Очистка поверхности кожи', 'Обработка швов и складок', 'Нанесение кондиционера'],
                    'en' => ['Leather surface cleaning', 'Seam and crease treatment', 'Conditioner application'],
                ],
                'excluded_items' => [
                    'hy' => ['Կաշվի ներկում կամ վերականգնում խորը վնասվածքների դեպքում', 'Պաշտպանիչ ծածկույթ (առանձին ծառայություն)'],
                    'ru' => ['Окрашивание или восстановление кожи при глубоких повреждениях', 'Защитное покрытие (отдельная услуга)'],
                    'en' => ['Re-dyeing or restoring leather with deep damage', 'Protective coating (separate service)'],
                ],
                'process_steps' => [
                    'hy' => ['Կաշվի տեսակի որոշում', 'Մեղմ մաքրում', 'Կարերի մշակում', 'Կերակրող միջոցի կիրառում'],
                    'ru' => ['Определение типа кожи', 'Бережная очистка', 'Обработка швов', 'Нанесение кондиционера'],
                    'en' => ['Identifying the leather type', 'Gentle cleaning', 'Seam treatment', 'Conditioner application'],
                ],
                'equipment' => ['hy' => 'Կաշվի համար նախատեսված pH-հավասարակշռված միջոցներ և փափուկ խոզանակներ։', 'ru' => 'pH-сбалансированные средства для кожи и мягкие щётки.', 'en' => 'pH-balanced leather products and soft brushes.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Նստատեղերի քանակը', 'Կաշվի վիճակը'],
                    'ru' => ['Количество сидений', 'Состояние кожи'],
                    'en' => ['Number of seats', 'Condition of the leather'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Անվտա՞նգ է քիմմաքրումը կաշվի համար', 'answer' => 'Օգտագործվում են կաշվին հարմարեցված միջոցներ, սակայն արդյունքը կախված է կաշվի տեսակից ու վիճակից, ուստի կատարվում է նախնական թեստ։']],
                    'ru' => [['question' => 'Безопасна ли химчистка для кожи', 'answer' => 'Используются средства, подобранные для кожи, но результат зависит от её типа и состояния, поэтому сначала проводится тест.']],
                    'en' => [['question' => 'Is the cleaning safe for leather', 'answer' => 'Leather-appropriate products are used, but the outcome depends on the leather\'s type and condition, so a test is run first.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['leather'],
                'contamination_types' => ['dust', 'grease_oil', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'floor-carpet-cleaning',
                'icon' => 'layers',
                'sort_order' => 5,
                'status' => 'active',
                'name' => ['hy' => 'Հատակի և ավտոմոբիլային կարպետի քիմմաքրում', 'ru' => 'Химчистка пола и автомобильного ковролина', 'en' => 'Floor & Carpet Cleaning'],
                'short_description' => [
                    'hy' => 'Հատակի ծածկույթի խորը մաքրում՝ փոշուց, ավազից և բծերից։',
                    'ru' => 'Глубокая очистка напольного покрытия от пыли, песка и пятен.',
                    'en' => 'Deep cleaning of the floor covering to lift dust, grit and stains.',
                ],
                'description' => [
                    'hy' => 'Ավտոմոբիլային կարպետը կուտակում է ավազ, փոշի և խոնավություն, որոնք ժամանակի ընթացքում հանգեցնում են հոտի ու բծերի։ Մաքրումն իրականացվում է էքստրակցիայի եղանակով՝ առանց ավելորդ խոնավացման։',
                    'ru' => 'Автомобильный ковролин накапливает песок, пыль и влагу, что со временем приводит к запаху и пятнам. Очистка проводится методом экстракции без избыточного увлажнения материала.',
                    'en' => 'Car carpet accumulates grit, dust and moisture that over time cause odor and staining. Cleaning is done by extraction, avoiding over-wetting the material.',
                ],
                'seo_title' => ['hy' => 'Հատակի և կարպետի քիմմաքրում Երևանում | Servio', 'ru' => 'Химчистка пола и ковролина авто в Ереване | Servio', 'en' => 'Car Floor & Carpet Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի հատակի և կարպետի մասնագիտական քիմմաքրում Երևանում։',
                    'ru' => 'Профессиональная химчистка пола и ковролина автомобиля в Ереване.',
                    'en' => 'Professional car floor and carpet cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է հատակի ծածկույթի վրա ավազ, խոնավություն կամ բծեր ունեցող ավտոմեքենաների համար։',
                    'ru' => 'Подходит автомобилям, у которых на напольном покрытии есть песок, влага или пятна.',
                    'en' => 'Suited to cars whose floor covering has grit, dampness or staining.',
                ],
                'included_items' => [
                    'hy' => ['Ինտենսիվ փոշեկուլում', 'Էքստրակցիայի եղանակով մաքրում', 'Խոնավության հեռացում'],
                    'ru' => ['Интенсивный пылесос', 'Очистка методом экстракции', 'Удаление влаги'],
                    'en' => ['Intensive vacuuming', 'Extraction cleaning', 'Moisture removal'],
                ],
                'excluded_items' => [
                    'hy' => ['Հանովի կոշիկների կամ ավտոկարպետի մաքրում (առանձին ծառայություն)', 'Նստատեղերի մշակում'],
                    'ru' => ['Чистка съёмных ковриков (отдельная услуга)', 'Обработка сидений'],
                    'en' => ['Removable floor mat cleaning (separate service)', 'Seat treatment'],
                ],
                'process_steps' => [
                    'hy' => ['Զննում և խոնավության ստուգում', 'Ինտենսիվ փոշեկուլում', 'Էքստրակցիա', 'Չորացում'],
                    'ru' => ['Осмотр и проверка на влажность', 'Интенсивный пылесос', 'Экстракция', 'Сушка'],
                    'en' => ['Inspection and moisture check', 'Intensive vacuuming', 'Extraction', 'Drying'],
                ],
                'equipment' => ['hy' => 'Հզոր էքստրակտոր և կարպետի համար նախատեսված միջոցներ։', 'ru' => 'Мощный экстрактор и средства для ковролина.', 'en' => 'A high-power extractor and carpet-specific products.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => [
                    'hy' => 'Կարպետը չորանում է ավելի երկար, քան նստատեղերը՝ նյութի խտության պատճառով. խորհուրդ է տրվում օդափոխել սրահը։',
                    'ru' => 'Ковролин сохнет дольше сидений из-за плотности материала — рекомендуется проветрить салон.',
                    'en' => 'Carpet takes longer to dry than seats due to its density — airing out the interior is recommended.',
                ],
                'price_factors' => [
                    'hy' => ['Ավտոմեքենայի տեսակը', 'Խոնավության աստիճանը'],
                    'ru' => ['Тип автомобиля', 'Степень влажности'],
                    'en' => ['Car body type', 'Moisture level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Որքա՞ն ժամանակ է պետք սպասել, մինչև նորից օգտագործել մեքենան', 'answer' => 'Խորհուրդ է տրվում սպասել լրիվ չորանալուն, ժամկետը կախված է եղանակից և օդափոխությունից։']],
                    'ru' => [['question' => 'Сколько ждать перед использованием машины', 'answer' => 'Рекомендуется дождаться полного высыхания, срок зависит от погоды и вентиляции.']],
                    'en' => [['question' => 'How long before I can use the car again', 'answer' => 'It\'s best to wait until fully dry — timing depends on weather and airflow.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'mixed'],
                'contamination_types' => ['dust', 'dampness', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'floor-mats-cleaning',
                'icon' => 'square',
                'sort_order' => 6,
                'status' => 'active',
                'name' => ['hy' => 'Ավտոմեքենայի գորգիկների քիմմաքրում', 'ru' => 'Химчистка автомобильных ковриков', 'en' => 'Floor Mat Cleaning'],
                'short_description' => [
                    'hy' => 'Հանովի գորգիկների առանձին քիմմաքրում։',
                    'ru' => 'Отдельная химчистка съёмных ковриков.',
                    'en' => 'Standalone cleaning for removable floor mats.',
                ],
                'description' => [
                    'hy' => 'Հանովի գորգիկները մաքրվում են առանձին՝ ինչը թույլ է տալիս ավելի արագ և մանրակրկիտ մշակում, առանց ավտոմեքենան երկար ժամանակ սպասարկումից դուրս թողնելու։',
                    'ru' => 'Съёмные коврики чистятся отдельно, что позволяет обработать их быстрее и тщательнее, не выводя автомобиль из эксплуатации надолго.',
                    'en' => 'Removable mats are cleaned separately, allowing a faster, more thorough treatment without taking the car out of use for long.',
                ],
                'seo_title' => ['hy' => 'Ավտոգորգիկների քիմմաքրում Երևանում | Servio', 'ru' => 'Химчистка автомобильных ковриков в Ереване | Servio', 'en' => 'Car Floor Mat Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի հանովի գորգիկների արագ և մասնագիտական քիմմաքրում Երևանում։',
                    'ru' => 'Быстрая и профессиональная химчистка съёмных автомобильных ковриков в Ереване.',
                    'en' => 'Fast, professional car floor mat cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է նրանց, ում պետք է միայն գորգիկների մաքրում՝ առանց ամբողջական սրահի մշակման։',
                    'ru' => 'Подходит тем, кому нужна только чистка ковриков без обработки всего салона.',
                    'en' => 'Suited to those who just need the mats cleaned without a full interior job.',
                ],
                'included_items' => [
                    'hy' => ['Գորգիկների փոշեկուլում', 'Էքստրակցիայի եղանակով մաքրում', 'Չորացում'],
                    'ru' => ['Пылесос ковриков', 'Очистка методом экстракции', 'Сушка'],
                    'en' => ['Vacuuming the mats', 'Extraction cleaning', 'Drying'],
                ],
                'excluded_items' => [
                    'hy' => ['Հատակի ֆիքսված ծածկույթի մաքրում', 'Նստատեղերի մաքրում'],
                    'ru' => ['Чистка несъёмного напольного покрытия', 'Чистка сидений'],
                    'en' => ['Fixed floor covering cleaning', 'Seat cleaning'],
                ],
                'process_steps' => [
                    'hy' => ['Գորգիկների զննում', 'Փոշեկուլում', 'Էքստրակցիա', 'Չորացում'],
                    'ru' => ['Осмотр ковриков', 'Пылесос', 'Экстракция', 'Сушка'],
                    'en' => ['Inspecting the mats', 'Vacuuming', 'Extraction', 'Drying'],
                ],
                'equipment' => ['hy' => 'Էքստրակտոր և գորգերի համար նախատեսված միջոցներ։', 'ru' => 'Экстрактор и средства для ковровых покрытий.', 'en' => 'An extractor and carpet-cleaning products.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Գորգիկների քանակը', 'Աղտոտվածության աստիճանը'],
                    'ru' => ['Количество ковриков', 'Степень загрязнения'],
                    'en' => ['Number of mats', 'Soiling level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եմ բերել միայն գորգիկները', 'answer' => 'Այո, այս ծառայությունը հասանելի է առանց ամբողջ ավտոմեքենան բերելու։']],
                    'ru' => [['question' => 'Можно ли привезти только коврики', 'answer' => 'Да, эта услуга доступна без необходимости привозить весь автомобиль.']],
                    'en' => [['question' => 'Can I just bring the mats', 'answer' => 'Yes, this service is available without bringing the whole car.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'mixed'],
                'contamination_types' => ['dust', 'dampness', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'ceiling-cleaning',
                'icon' => 'panel-top',
                'sort_order' => 7,
                'status' => 'active',
                'name' => ['hy' => 'Առաստաղի մեղմ մաքրում', 'ru' => 'Бережная очистка потолка', 'en' => 'Gentle Ceiling Cleaning'],
                'short_description' => [
                    'hy' => 'Սրահի առաստաղի մեղմ մշակում՝ առանց նյութը վնասելու ռիսկի։',
                    'ru' => 'Бережная обработка потолка салона без риска повредить материал.',
                    'en' => 'Gentle treatment of the interior ceiling that avoids damaging the material.',
                ],
                'description' => [
                    'hy' => 'Առաստաղի ծածկույթը հատուկ զգայուն է չափից ավելի խոնավացման նկատմամբ, ուստի մշակվում է նվազագույն խոնավությամբ մեթոդներով՝ ջոկելու ցեխարկում կամ շերտազատում կանխելու համար։',
                    'ru' => 'Потолочная обивка особенно чувствительна к избыточной влаге, поэтому обрабатывается методами с минимальным увлажнением, чтобы избежать расслоения или провисания.',
                    'en' => 'Headliner fabric is especially sensitive to over-wetting, so it\'s treated with low-moisture methods to avoid sagging or delamination.',
                ],
                'seo_title' => ['hy' => 'Ավտոմեքենայի առաստաղի մաքրում Երևանում | Servio', 'ru' => 'Чистка потолка автомобиля в Ереване | Servio', 'en' => 'Car Ceiling Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի առաստաղի մեղմ և անվտանգ մաքրում Երևանում։',
                    'ru' => 'Бережная и безопасная чистка потолка автомобиля в Ереване.',
                    'en' => 'Gentle, safe car ceiling cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է ծխի հոտ, բծեր կամ կեղտոտված առաստաղ ունեցող ավտոմեքենաների համար։',
                    'ru' => 'Подходит автомобилям с запахом дыма, пятнами или загрязнённым потолком.',
                    'en' => 'Suited to cars with smoke odor, staining or a soiled ceiling.',
                ],
                'included_items' => [
                    'hy' => ['Նախնական զննում', 'Մեղմ մակերեսային մաքրում', 'Տեղային բծերի մշակում'],
                    'ru' => ['Предварительный осмотр', 'Бережная поверхностная очистка', 'Локальная обработка пятен'],
                    'en' => ['Initial inspection', 'Gentle surface cleaning', 'Spot stain treatment'],
                ],
                'excluded_items' => [
                    'hy' => ['Առաստաղի փոխարինում կամ վերականգնում շերտազատման դեպքում', 'Ինտենսիվ խոնավացնող մեթոդներ'],
                    'ru' => ['Замена или восстановление потолка при расслоении обшивки', 'Интенсивные влажные методы'],
                    'en' => ['Replacing or re-gluing a delaminated headliner', 'Heavy-moisture methods'],
                ],
                'process_steps' => [
                    'hy' => ['Նյութի զննում', 'Մեղմ չոր մաքրում', 'Նվազագույն խոնավությամբ մշակում', 'Չորացում'],
                    'ru' => ['Осмотр материала', 'Бережная сухая чистка', 'Обработка с минимумом влаги', 'Сушка'],
                    'en' => ['Material inspection', 'Gentle dry cleaning', 'Low-moisture treatment', 'Drying'],
                ],
                'equipment' => ['hy' => 'Փափուկ խոզանակներ և նվազագույն խոնավացմամբ մաքրող միջոցներ։', 'ru' => 'Мягкие щётки и средства с минимальным увлажнением.', 'en' => 'Soft brushes and low-moisture cleaning products.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Աղտոտվածության աստիճանը', 'Ավտոմեքենայի տեսակը'],
                    'ru' => ['Степень загрязнения', 'Тип автомобиля'],
                    'en' => ['Soiling level', 'Car body type'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Անվտա՞նգ է առաստաղի մաքրումը', 'answer' => 'Օգտագործվում են մեղմ, նվազագույն խոնավությամբ մեթոդներ, սակայն հին կամ արդեն թուլացած ծածկույթի դեպքում առկա է վնասման որոշակի ռիսկ, որը քննարկվում է աշխատանքից առաջ։']],
                    'ru' => [['question' => 'Безопасна ли чистка потолка', 'answer' => 'Используются бережные методы с минимальным увлажнением, но при старой или уже ослабленной обивке есть риск повреждения — это обсуждается заранее.']],
                    'en' => [['question' => 'Is ceiling cleaning safe', 'answer' => 'Gentle, low-moisture methods are used, but an old or already-loose headliner carries some risk of damage, which is discussed beforehand.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'alcantara'],
                'contamination_types' => ['dust', 'tobacco_odor', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'door-panel-cleaning',
                'icon' => 'door-open',
                'sort_order' => 8,
                'status' => 'active',
                'name' => ['hy' => 'Դռների պանելների մաքրում', 'ru' => 'Очистка дверных карт', 'en' => 'Door Panel Cleaning'],
                'short_description' => [
                    'hy' => 'Դռների պանելների մաքրում՝ պլաստիկ և տեքստիլ տարրերի հաշվառմամբ։',
                    'ru' => 'Чистка дверных карт с учётом пластиковых и текстильных элементов.',
                    'en' => 'Door panel cleaning that accounts for both plastic and fabric elements.',
                ],
                'description' => [
                    'hy' => 'Դռների պանելները հաճախ համատեղում են մի քանի նյութ՝ պլաստիկ, տեքստիլ և երբեմն կաշի։ Մշակումը հարմարեցվում է յուրաքանչյուր հատվածին առանձին։',
                    'ru' => 'Дверные карты часто сочетают несколько материалов — пластик, ткань и иногда кожу. Обработка подбирается для каждой зоны отдельно.',
                    'en' => 'Door panels often combine several materials — plastic, fabric and sometimes leather. Each zone is treated according to its material.',
                ],
                'seo_title' => ['hy' => 'Դռների պանելների մաքրում Երևանում | Servio', 'ru' => 'Чистка дверных карт автомобиля в Ереване | Servio', 'en' => 'Car Door Panel Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի դռների պանելների մասնագիտական մաքրում Երևանում։',
                    'ru' => 'Профессиональная чистка дверных карт автомобиля в Ереване.',
                    'en' => 'Professional car door panel cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է կեղտոտված կամ բծերով դռների պանելներով ավտոմեքենաների համար։',
                    'ru' => 'Подходит автомобилям с загрязнёнными или испачканными дверными картами.',
                    'en' => 'Suited to cars with soiled or stained door panels.',
                ],
                'included_items' => [
                    'hy' => ['Պլաստիկի մաքրում', 'Տեքստիլ տարրերի մշակում', 'Բռնակների և կոճակների մաքրում'],
                    'ru' => ['Чистка пластика', 'Обработка текстильных вставок', 'Чистка ручек и кнопок'],
                    'en' => ['Plastic cleaning', 'Fabric insert treatment', 'Handle and switch cleaning'],
                ],
                'excluded_items' => [
                    'hy' => ['Դռան մեխանիզմի սպասարկում', 'Ձայնամեկուսացման աշխատանքներ'],
                    'ru' => ['Обслуживание дверного механизма', 'Работы по шумоизоляции'],
                    'en' => ['Door mechanism servicing', 'Sound-deadening work'],
                ],
                'process_steps' => [
                    'hy' => ['Նյութերի որոշում', 'Պլաստիկի և տեքստիլի առանձին մշակում', 'Ավարտական սրբում'],
                    'ru' => ['Определение материалов', 'Отдельная обработка пластика и ткани', 'Финальное протирание'],
                    'en' => ['Identifying materials', 'Treating plastic and fabric separately', 'Final wipe-down'],
                ],
                'equipment' => ['hy' => 'Պլաստիկի և տեքստիլի համար առանձին մաքրող միջոցներ։', 'ru' => 'Отдельные средства для пластика и ткани.', 'en' => 'Separate cleaning agents for plastic and fabric.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Դռների քանակը', 'Աղտոտվածության աստիճանը'],
                    'ru' => ['Количество дверей', 'Степень загрязнения'],
                    'en' => ['Number of doors', 'Soiling level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եմ պատվիրել միայն այս ծառայությունը', 'answer' => 'Այո, դռների պանելների մաքրումը հասանելի է որպես առանձին ծառայություն։']],
                    'ru' => [['question' => 'Можно ли заказать только эту услугу', 'answer' => 'Да, чистка дверных карт доступна как отдельная услуга.']],
                    'en' => [['question' => 'Can I book only this service', 'answer' => 'Yes, door panel cleaning is available on its own.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'leather', 'plastic', 'mixed'],
                'contamination_types' => ['dust', 'grease_oil', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'trunk-cleaning',
                'icon' => 'package',
                'sort_order' => 9,
                'status' => 'active',
                'name' => ['hy' => 'Բեռնախցիկի մաքրում', 'ru' => 'Очистка багажника', 'en' => 'Trunk Cleaning'],
                'short_description' => [
                    'hy' => 'Բեռնախցիկի մաքրում՝ բծերից, հոտից և փոշուց ազատվելու համար։',
                    'ru' => 'Очистка багажника от пятен, запаха и пыли.',
                    'en' => 'Trunk cleaning to clear stains, odor and dust.',
                ],
                'description' => [
                    'hy' => 'Բեռնախցիկը հաճախ կուտակում է կեղտ և հոտեր, որոնք չեն երևում առաջին հայացքից։ Մաքրումն ընդգրկում է ծածկույթը, կողային պանելները և պահեստային անվադողի հատվածը։',
                    'ru' => 'Багажник часто накапливает грязь и запахи, которые не видны с первого взгляда. Очистка охватывает обшивку, боковые панели и нишу запасного колеса.',
                    'en' => 'A trunk often accumulates dirt and odor that isn\'t obvious at first glance. Cleaning covers the liner, side panels and spare-wheel well.',
                ],
                'seo_title' => ['hy' => 'Բեռնախցիկի մաքրում Երևանում | Servio', 'ru' => 'Химчистка багажника автомобиля в Ереване | Servio', 'en' => 'Car Trunk Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի բեռնախցիկի մասնագիտական մաքրում Երևանում։',
                    'ru' => 'Профессиональная химчистка багажника автомобиля в Ереване.',
                    'en' => 'Professional car trunk cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է բեռնափոխադրումից հետո կեղտոտված կամ հոտ ունեցող բեռնախցիկի համար։',
                    'ru' => 'Подходит багажнику, загрязнённому или пахнущему после перевозки грузов.',
                    'en' => 'Suited to a trunk that\'s soiled or smells after hauling cargo.',
                ],
                'included_items' => [
                    'hy' => ['Ծածկույթի մաքրում', 'Կողային պանելների մշակում', 'Հոտի նեյտրալացում'],
                    'ru' => ['Чистка обшивки', 'Обработка боковых панелей', 'Нейтрализация запаха'],
                    'en' => ['Liner cleaning', 'Side panel treatment', 'Odor neutralizing'],
                ],
                'excluded_items' => [
                    'hy' => ['Պահեստային անվադողի փոխարինում կամ սպասարկում', 'Սրահի մաքրում'],
                    'ru' => ['Замена или обслуживание запасного колеса', 'Чистка салона'],
                    'en' => ['Spare wheel replacement or servicing', 'Interior cabin cleaning'],
                ],
                'process_steps' => [
                    'hy' => ['Բեռնախցիկի ազատում', 'Փոշեկուլում և տեղային մշակում', 'Հոտի նեյտրալացում'],
                    'ru' => ['Освобождение багажника', 'Пылесос и локальная обработка', 'Нейтрализация запаха'],
                    'en' => ['Clearing the trunk', 'Vacuuming and spot treatment', 'Odor neutralizing'],
                ],
                'equipment' => ['hy' => 'Էքստրակտոր և հոտի նեյտրալացնող միջոցներ։', 'ru' => 'Экстрактор и средства для нейтрализации запаха.', 'en' => 'An extractor and odor-neutralizing products.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Ավտոմեքենայի տեսակը', 'Աղտոտվածության աստիճանը'],
                    'ru' => ['Тип автомобиля', 'Степень загрязнения'],
                    'en' => ['Car body type', 'Soiling level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Կհեռացվի՞ հոտը ամբողջությամբ', 'answer' => 'Մեծ մասամբ հոտի աղբյուրը վերացվում է, սակայն արդյունքը կախված է աղբյուրից ու տարածքի նյութից, ուստի բացարձակ երաշխիք չենք տալիս։']],
                    'ru' => [['question' => 'Уйдёт ли запах полностью', 'answer' => 'В большинстве случаев источник запаха устраняется, но результат зависит от источника и материала, поэтому мы не даём абсолютных гарантий.']],
                    'en' => [['question' => 'Will the odor be fully removed', 'answer' => 'In most cases the source is cleared, but the outcome depends on the source and material, so we don\'t make absolute guarantees.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'plastic', 'mixed'],
                'contamination_types' => ['dust', 'grease_oil', 'dampness', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'plastic-cleaning',
                'icon' => 'square-dashed',
                'sort_order' => 10,
                'status' => 'active',
                'name' => ['hy' => 'Պլաստիկի և դժվարամատչելի հատվածների մաքրում', 'ru' => 'Очистка пластика и труднодоступных участков', 'en' => 'Plastic & Hard-to-Reach Area Cleaning'],
                'short_description' => [
                    'hy' => 'Ղեկափողի, կենտրոնական վահանակի և օդանցքերի մաքրում փոշուց։',
                    'ru' => 'Чистка торпедо, центральной консоли и вентиляционных решёток от пыли.',
                    'en' => 'Cleaning the dashboard, center console and vents of accumulated dust.',
                ],
                'description' => [
                    'hy' => 'Փոշին և կեղտը կուտակվում են ղեկափողի, կենտրոնական վահանակի կոճակների և օդանցքերի միջև, որտեղ դժվար է հասնել առօրյա մաքրման ժամանակ։ Այս ծառայությունը կենտրոնացած է հենց այդ հատվածների վրա։',
                    'ru' => 'Пыль и грязь скапливаются на торпедо, между кнопками центральной консоли и в вентиляционных решётках — местах, куда сложно добраться при обычной уборке. Эта услуга сфокусирована именно на таких зонах.',
                    'en' => 'Dust and grime build up on the dashboard, between center-console buttons and inside vents — spots that everyday cleaning rarely reaches. This service focuses on exactly those areas.',
                ],
                'seo_title' => ['hy' => 'Ավտոպլաստիկի մաքրում Երևանում | Servio', 'ru' => 'Чистка пластика салона авто в Ереване | Servio', 'en' => 'Car Plastic Trim Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի պլաստիկ մակերեսների և դժվարամատչելի հատվածների մաքրում Երևանում։',
                    'ru' => 'Чистка пластиковых поверхностей и труднодоступных участков салона в Ереване.',
                    'en' => 'Car plastic surface and hard-to-reach area cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է նրանց, ում սրահում կուտակվել է փոշի կոճակների, օդանցքերի և հոդակապերի շուրջ։',
                    'ru' => 'Подходит тем, у кого в салоне скопилась пыль вокруг кнопок, решёток и стыков.',
                    'en' => 'Suited to interiors with dust built up around buttons, vents and trim joints.',
                ],
                'included_items' => [
                    'hy' => ['Ղեկափողի մաքրում', 'Կենտրոնական վահանակի և կոճակների մշակում', 'Օդանցքերի մաքրում'],
                    'ru' => ['Чистка торпедо', 'Обработка центральной консоли и кнопок', 'Чистка вентиляционных решёток'],
                    'en' => ['Dashboard cleaning', 'Center console and button treatment', 'Vent cleaning'],
                ],
                'excluded_items' => [
                    'hy' => ['Էլեկտրոնային բաղադրիչների վերանորոգում', 'Նստատեղերի կամ հատակի մաքրում'],
                    'ru' => ['Ремонт электронных компонентов', 'Чистка сидений или пола'],
                    'en' => ['Electronic component repair', 'Seat or floor cleaning'],
                ],
                'process_steps' => [
                    'hy' => ['Զննում', 'Մանր խոզանակներով մաքրում', 'Պլաստիկի սրբում'],
                    'ru' => ['Осмотр', 'Чистка тонкими щётками', 'Протирание пластика'],
                    'en' => ['Inspection', 'Detail-brush cleaning', 'Wiping down the plastic'],
                ],
                'equipment' => ['hy' => 'Մանր խոզանակներ, միկրոմանրաթել և պլաստիկի համար նախատեսված միջոցներ։', 'ru' => 'Тонкие щётки, микрофибра и средства для пластика.', 'en' => 'Detail brushes, microfiber and plastic-safe products.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Ավտոմեքենայի տեսակը', 'Աղտոտվածության աստիճանը'],
                    'ru' => ['Тип автомобиля', 'Степень загрязнения'],
                    'en' => ['Car body type', 'Soiling level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Կփայլեցվի՞ պլաստիկը', 'answer' => 'Կիրառվում են պլաստիկին հարմարեցված միջոցներ, որոնք մաքրում են առանց արհեստական փայլեցնող էֆեկտի, եթե այլ բան նախապես չի համաձայնեցվել։']],
                    'ru' => [['question' => 'Пластик будет глянцевым после чистки', 'answer' => 'Используются средства для пластика, которые очищают без искусственного глянцевого эффекта, если иное не согласовано заранее.']],
                    'en' => [['question' => 'Will the plastic look glossy afterward', 'answer' => 'Plastic-safe products are used that clean without an artificial glossy finish, unless otherwise agreed in advance.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['plastic'],
                'contamination_types' => ['dust', 'grease_oil', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'stain-removal',
                'icon' => 'droplet',
                'sort_order' => 11,
                'status' => 'active',
                'name' => ['hy' => 'Տեղային բծերի հեռացում', 'ru' => 'Удаление локальных пятен', 'en' => 'Spot Stain Removal'],
                'short_description' => [
                    'hy' => 'Կոնկրետ բծերի նպատակային մշակում՝ առանց ամբողջ սրահը մաքրելու։',
                    'ru' => 'Точечная обработка конкретных пятен без чистки всего салона.',
                    'en' => 'Targeted treatment of specific stains without cleaning the whole interior.',
                ],
                'description' => [
                    'hy' => 'Եթե սրահում կա միայն մեկ կամ մի քանի կոնկրետ բիծ, հնարավոր է պատվիրել դրանց տեղային մշակումը՝ առանց ամբողջական քիմմաքրման։ Մեթոդն ընտրվում է բծի ծագումից ելնելով։',
                    'ru' => 'Если в салоне есть всего одно или несколько конкретных пятен, можно заказать их точечную обработку без полной химчистки. Метод подбирается по происхождению пятна.',
                    'en' => 'If the interior has just one or a few specific stains, spot treatment can be booked without a full clean. The method depends on what caused the stain.',
                ],
                'seo_title' => ['hy' => 'Բծերի հեռացում ավտոմեքենայից Երևանում | Servio', 'ru' => 'Удаление пятен с сидений в Ереване | Servio', 'en' => 'Car Stain Removal in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի սրահում կոնկրետ բծերի մասնագիտական հեռացում Երևանում։',
                    'ru' => 'Профессиональное удаление отдельных пятен в салоне автомобиля в Ереване.',
                    'en' => 'Professional spot stain removal for car interiors in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է, երբ սրահում առկա է միայն մեկ կամ երկու հստակ բիծ։',
                    'ru' => 'Подходит, если в салоне есть всего одно-два чётких пятна.',
                    'en' => 'Suited to cases with just one or two clear stains.',
                ],
                'included_items' => [
                    'hy' => ['Բծի ախտորոշում', 'Թեստային մշակում', 'Տեղային հեռացում'],
                    'ru' => ['Диагностика пятна', 'Тестовая обработка', 'Точечное удаление'],
                    'en' => ['Stain diagnosis', 'Test treatment', 'Spot removal'],
                ],
                'excluded_items' => [
                    'hy' => ['Ամբողջ սրահի մաքրում', 'Երաշխավորված արդյունք հին կամ անհայտ ծագման բծերի դեպքում'],
                    'ru' => ['Чистка всего салона', 'Гарантированный результат для старых или пятен неизвестного происхождения'],
                    'en' => ['Cleaning the whole interior', 'A guaranteed result for old or unknown-origin stains'],
                ],
                'process_steps' => [
                    'hy' => ['Բծի տեսակի որոշում', 'Չտեսանելի հատվածում թեստ', 'Նպատակային մշակում', 'Չորացում'],
                    'ru' => ['Определение типа пятна', 'Тест на незаметном участке', 'Целевая обработка', 'Сушка'],
                    'en' => ['Identifying the stain type', 'Testing on a hidden area', 'Targeted treatment', 'Drying'],
                ],
                'equipment' => ['hy' => 'Բծի տեսակին հարմարեցված մասնագիտացված մաքրող միջոցներ։', 'ru' => 'Специализированные средства, подобранные под тип пятна.', 'en' => 'Specialized products matched to the stain type.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Բծերի քանակը', 'Բծի տեսակը և տարիքը'],
                    'ru' => ['Количество пятен', 'Тип и возраст пятна'],
                    'en' => ['Number of stains', 'Stain type and age'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եք երաշխավորել բծի ամբողջական հեռացումը', 'answer' => 'Ոչ միշտ. արդյունքը կախված է բծի ծագումից, տարիքից և նյութից, ինչը պարզվում է թեստից հետո։']],
                    'ru' => [['question' => 'Можете ли вы гарантировать полное удаление пятна', 'answer' => 'Не всегда — результат зависит от происхождения, возраста пятна и материала, что становится ясно после теста.']],
                    'en' => [['question' => 'Can you guarantee full stain removal', 'answer' => 'Not always — it depends on the stain\'s origin, age and the material, which becomes clear after the test.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'leather', 'alcantara'],
                'contamination_types' => ['food_drinks', 'coffee', 'grease_oil', 'blood', 'ink', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'odor-removal',
                'icon' => 'wind',
                'sort_order' => 12,
                'status' => 'active',
                'name' => ['hy' => 'Տհաճ հոտերի վերացում', 'ru' => 'Устранение неприятных запахов', 'en' => 'Odor Removal'],
                'short_description' => [
                    'hy' => 'Հոտի աղբյուրի հայտնաբերում և մշակում՝ ըստ ծագման։',
                    'ru' => 'Поиск источника запаха и обработка в зависимости от его происхождения.',
                    'en' => 'Locating the odor source and treating it based on what\'s causing it.',
                ],
                'description' => [
                    'hy' => 'Հոտի վերացումը սկսվում է աղբյուրի հայտնաբերումից՝ նստատեղեր, կարպետ, օդափոխման համակարգ կամ այլ հատված։ Մեթոդն ընտրվում է հոտի տեսակից ելնելով, և արդյունքը կարող է տարբերվել ըստ դեպքի։',
                    'ru' => 'Устранение запаха начинается с поиска источника — сиденья, ковролин, система вентиляции или другая зона. Метод подбирается в зависимости от типа запаха, и результат может отличаться от случая к случаю.',
                    'en' => 'Odor removal starts with locating the source — seats, carpet, the ventilation system or elsewhere. The method depends on the odor type, and results can vary case by case.',
                ],
                'seo_title' => ['hy' => 'Ավտոմեքենայից հոտի հեռացում Երևանում | Servio', 'ru' => 'Удаление запаха из автомобиля в Ереване | Servio', 'en' => 'Car Odor Removal in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի սրահից տհաճ հոտերի մասնագիտական վերացում Երևանում։',
                    'ru' => 'Профессиональное устранение неприятного запаха из салона автомобиля в Ереване.',
                    'en' => 'Professional odor removal for car interiors in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է ծխի, խոնավության, կենդանիների կամ այլ աղբյուրից եկող հոտ ունեցող սրահների համար։',
                    'ru' => 'Подходит салонам с запахом от табака, сырости, животных или иного источника.',
                    'en' => 'Suited to interiors with odor from smoke, dampness, pets or another source.',
                ],
                'included_items' => [
                    'hy' => ['Հոտի աղբյուրի հայտնաբերում', 'Աղբյուրի մշակում', 'Նեյտրալացում'],
                    'ru' => ['Поиск источника запаха', 'Обработка источника', 'Нейтрализация'],
                    'en' => ['Locating the odor source', 'Treating the source', 'Neutralizing'],
                ],
                'excluded_items' => [
                    'hy' => ['Օդափոխման համակարգի տեխնիկական վերանորոգում', 'Երաշխավորված ամբողջական վերացում խորը ներծծված հոտերի դեպքում'],
                    'ru' => ['Техническая починка системы вентиляции', 'Гарантированное полное устранение при глубоко въевшихся запахах'],
                    'en' => ['Technical repair of the ventilation system', 'A guaranteed complete removal for deeply embedded odors'],
                ],
                'process_steps' => [
                    'hy' => ['Հոտի աղբյուրի ախտորոշում', 'Աղբյուր պարունակող նյութի մշակում', 'Նեյտրալացնող մեթոդի կիրառում', 'Վերստուգում'],
                    'ru' => ['Диагностика источника запаха', 'Обработка материала-источника', 'Применение нейтрализующего метода', 'Повторная проверка'],
                    'en' => ['Diagnosing the odor source', 'Treating the source material', 'Applying a neutralizing method', 'Re-checking'],
                ],
                'equipment' => ['hy' => 'Աղբյուրին հարմարեցված նեյտրալացնող միջոցներ։ Կոնկրետ սարքավորումը որոշվում է ախտորոշումից հետո։', 'ru' => 'Нейтрализующие средства, подобранные под источник. Конкретное оборудование определяется после диагностики.', 'en' => 'Neutralizing products matched to the source. The specific equipment used is decided after diagnosis.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Հոտի աղբյուրը և ինտենսիվությունը', 'Ավտոմեքենայի տեսակը'],
                    'ru' => ['Источник и интенсивность запаха', 'Тип автомобиля'],
                    'en' => ['Odor source and intensity', 'Car body type'],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եք ամբողջությամբ հեռացնել հոտը', 'answer' => 'Արդյունքը կախված է հոտի աղբյուրից, խորությունից և նյութից. որոշ դեպքերում ամբողջական վերացումը հնարավոր չէ երաշխավորել։'],
                        ['question' => 'Հեռացվու՞մ է մեզի կամ ծխախոտի հոտը', 'answer' => 'Մշակվում է աղբյուրին համապատասխան մեթոդով, սակայն երաշխիք չենք տալիս խորը ներծծված դեպքերի համար։'],
                    ],
                    'ru' => [
                        ['question' => 'Можно ли полностью удалить запах', 'answer' => 'Результат зависит от источника, глубины и материала — в некоторых случаях полное устранение нельзя гарантировать.'],
                        ['question' => 'Удаляется ли запах мочи или табака', 'answer' => 'Обрабатывается методом, соответствующим источнику, но для глубоко въевшихся случаев гарантию мы не даём.'],
                    ],
                    'en' => [
                        ['question' => 'Can you fully remove the odor', 'answer' => 'The result depends on the source, depth and material — full removal can\'t always be guaranteed.'],
                        ['question' => 'Does it remove urine or tobacco odor', 'answer' => 'It\'s treated with a method matched to the source, but we don\'t guarantee deeply embedded cases.'],
                    ],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'leather', 'alcantara', 'plastic', 'mixed'],
                'contamination_types' => ['tobacco_odor', 'urine', 'vomit', 'dampness', 'pet_hair', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'pet-hair-removal',
                'icon' => 'paw-print',
                'sort_order' => 13,
                'status' => 'active',
                'name' => ['hy' => 'Կենդանիների մազի հեռացում', 'ru' => 'Удаление шерсти животных', 'en' => 'Pet Hair Removal'],
                'short_description' => [
                    'hy' => 'Նստատեղերից և հատակից կենդանիների մազի մասնագիտական հեռացում։',
                    'ru' => 'Профессиональное удаление шерсти животных с сидений и пола.',
                    'en' => 'Professional removal of pet hair from seats and floor.',
                ],
                'description' => [
                    'hy' => 'Կենդանիների մազը հաճախ խորապես ներծծվում է տեքստիլի մեջ և դժվար է հեռացվում սովորական փոշեկուլով։ Օգտագործվում են մասնագիտացված գործիքներ և տեխնիկա՝ մազը մակերեսից անջատելու համար։',
                    'ru' => 'Шерсть животных часто глубоко въедается в ткань и плохо удаляется обычным пылесосом. Используются специализированные инструменты и техника, чтобы отделить шерсть от поверхности.',
                    'en' => 'Pet hair often works deep into fabric and resists a regular vacuum. Specialized tools and technique are used to lift it from the surface.',
                ],
                'seo_title' => ['hy' => 'Կենդանու մազի հեռացում մեքենայից Երևանում | Servio', 'ru' => 'Удаление шерсти животных из автомобиля в Ереване | Servio', 'en' => 'Pet Hair Removal from Cars in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի սրահից կենդանիների մազի մասնագիտական հեռացում Երևանում։',
                    'ru' => 'Профессиональное удаление шерсти животных из салона автомобиля в Ереване.',
                    'en' => 'Professional pet hair removal from car interiors in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է կենդանիներ փոխադրող ավտոմեքենաների սեփականատերերի համար։',
                    'ru' => 'Подходит владельцам, которые перевозят в машине домашних животных.',
                    'en' => 'Suited to owners who transport pets in the car.',
                ],
                'included_items' => [
                    'hy' => ['Մազի հայտնաբերում և գնահատում', 'Մասնագիտացված գործիքներով մշակում', 'Ավարտական փոշեկուլում'],
                    'ru' => ['Оценка степени загрязнения шерстью', 'Обработка специализированными инструментами', 'Финальный пылесос'],
                    'en' => ['Assessing how much hair is present', 'Treatment with specialized tools', 'Final vacuuming'],
                ],
                'excluded_items' => [
                    'hy' => ['Հոտի ամբողջական վերացում (պատվիրվում է առանձին)', 'Ալերգենների լրիվ չեզոքացում'],
                    'ru' => ['Полное устранение запаха (заказывается отдельно)', 'Полная нейтрализация аллергенов'],
                    'en' => ['Complete odor removal (booked separately)', 'Full allergen neutralization'],
                ],
                'process_steps' => [
                    'hy' => ['Զննում', 'Մազի անջատում մասնագիտացված գործիքով', 'Փոշեկուլում', 'Ավարտական ստուգում'],
                    'ru' => ['Осмотр', 'Отделение шерсти специальным инструментом', 'Пылесос', 'Финальная проверка'],
                    'en' => ['Inspection', 'Lifting hair with a specialized tool', 'Vacuuming', 'Final check'],
                ],
                'equipment' => ['hy' => 'Ռետինե խոզանակներ, մազահեռացնող գործիքներ և հզոր փոշեկուլ։', 'ru' => 'Резиновые щётки, инструменты для удаления шерсти и мощный пылесос.', 'en' => 'Rubber brushes, pet-hair removal tools and a high-power vacuum.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Մազի քանակը', 'Ավտոմեքենայի տեսակը'],
                    'ru' => ['Количество шерсти', 'Тип автомобиля'],
                    'en' => ['Amount of hair', 'Car body type'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Կհեռացվի՞ ամբողջ մազը', 'answer' => 'Մեծ մասամբ մազը հեռացվում է, սակայն շատ խորը ներծծված դեպքերում կարող են մնալ առանձին մազիկներ։']],
                    'ru' => [['question' => 'Уйдёт ли вся шерсть', 'answer' => 'Большая часть шерсти удаляется, но при очень глубоком въедании отдельные волоски могут остаться.']],
                    'en' => [['question' => 'Will all the hair be removed', 'answer' => 'Most of it comes out, but a few strands may remain in cases of very deep embedding.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'alcantara'],
                'contamination_types' => ['pet_hair'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'child-seat-cleaning',
                'icon' => 'baby',
                'sort_order' => 14,
                'status' => 'active',
                'name' => ['hy' => 'Մանկական ավտոմեքենայի նստատեղի քիմմաքրում', 'ru' => 'Химчистка детского автомобильного кресла', 'en' => 'Child Car Seat Cleaning'],
                'short_description' => [
                    'hy' => 'Մանկական նստատեղի մեղմ մշակում՝ հարմար մաքրող միջոցներով։',
                    'ru' => 'Бережная обработка детского кресла с подходящими средствами.',
                    'en' => 'Gentle treatment of a child seat using appropriate products.',
                ],
                'description' => [
                    'hy' => 'Մանկական նստատեղը մաքրվում է առանձին՝ ուշադրություն դարձնելով գործվածքին, ամրագոտիների նյութին և կարերին։ Օգտագործվող միջոցներն ընտրվում են՝ հաշվի առնելով, որ արտադրանքը շփվում է երեխայի հետ։',
                    'ru' => 'Детское кресло чистится отдельно, с вниманием к ткани, ремням безопасности и швам. Средства подбираются с учётом того, что изделие контактирует с ребёнком.',
                    'en' => 'A child seat is cleaned separately, with attention to the fabric, harness straps and seams. Products are chosen with the fact that a child will be in contact with it in mind.',
                ],
                'seo_title' => ['hy' => 'Մանկական ավտոկրեսլոյի քիմմաքրում Երևանում | Servio', 'ru' => 'Химчистка детского автокресла в Ереване | Servio', 'en' => 'Child Car Seat Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Մանկական ավտոմեքենայի նստատեղի մասնագիտական և մեղմ քիմմաքրում Երևանում։',
                    'ru' => 'Профессиональная и бережная химчистка детского автокресла в Ереване.',
                    'en' => 'Professional, gentle child car seat cleaning in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է ծնողների համար, ում մանկական նստատեղն ունի բծեր կամ կարիք ունի կանոնավոր թարմացման։',
                    'ru' => 'Подходит родителям, чьё детское кресло имеет пятна или нуждается в регулярном обновлении.',
                    'en' => 'Suited to parents whose child seat has stains or needs periodic refreshing.',
                ],
                'included_items' => [
                    'hy' => ['Հանովի գործվածքի մաքրում', 'Ամրագոտիների մշակում', 'Կարկասի սրբում'],
                    'ru' => ['Чистка съёмного чехла', 'Обработка ремней безопасности', 'Протирание каркаса'],
                    'en' => ['Removable cover cleaning', 'Harness strap treatment', 'Frame wipe-down'],
                ],
                'excluded_items' => [
                    'hy' => ['Կրեսլոյի կառուցվածքային վերանորոգում', 'Անվտանգության մեխանիզմի ստուգում'],
                    'ru' => ['Конструкционный ремонт кресла', 'Проверка механизма безопасности'],
                    'en' => ['Structural seat repair', 'Safety mechanism inspection'],
                ],
                'process_steps' => [
                    'hy' => ['Նյութի և պիտակի զննում', 'Մեղմ մաքրում', 'Ամրագոտիների մշակում', 'Ամբողջական չորացում'],
                    'ru' => ['Осмотр материала и ярлыка', 'Бережная очистка', 'Обработка ремней', 'Полная сушка'],
                    'en' => ['Checking the material and care label', 'Gentle cleaning', 'Harness treatment', 'Full drying'],
                ],
                'equipment' => ['hy' => 'Մանկական իրերի համար հարմարեցված, մեղմ մաքրող միջոցներ։', 'ru' => 'Средства, подходящие для детских изделий.', 'en' => 'Products suited for children\'s items.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => [
                    'hy' => 'Կրեսլոն պետք է ամբողջությամբ չորանա, նախքան նորից օգտագործելը՝ նյութի անվտանգության համար։',
                    'ru' => 'Кресло должно полностью высохнуть перед повторным использованием — в целях безопасности материала.',
                    'en' => 'The seat needs to fully dry before reuse, for the material\'s safety.',
                ],
                'price_factors' => [
                    'hy' => ['Կրեսլոների քանակը', 'Աղտոտվածության աստիճանը'],
                    'ru' => ['Количество кресел', 'Степень загрязнения'],
                    'en' => ['Number of seats', 'Soiling level'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Անվտա՞նգ են մաքրող միջոցները երեխայի համար', 'answer' => 'Ընտրվում են մանկական իրերի համար հարմարեցված միջոցներ, սակայն կոնկրետ արտադրանքի մասին տեղեկատվությունը տրամադրվում է հարցման դեպքում։']],
                    'ru' => [['question' => 'Безопасны ли средства для ребёнка', 'answer' => 'Подбираются средства, подходящие для детских изделий; подробности о конкретном составе предоставляются по запросу.']],
                    'en' => [['question' => 'Are the products safe for a child', 'answer' => 'Products suited for children\'s items are chosen; details on the specific product are available on request.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric'],
                'contamination_types' => ['food_drinks', 'dust', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'single-seat-cleaning',
                'icon' => 'armchair',
                'sort_order' => 15,
                'status' => 'active',
                'name' => ['hy' => 'Առանձին նստատեղերի քիմմաքրում', 'ru' => 'Химчистка отдельных сидений', 'en' => 'Single Seat Cleaning'],
                'short_description' => [
                    'hy' => 'Մեկ կամ մի քանի կոնկրետ նստատեղի մաքրում՝ առանց ամբողջ սրահի մշակման։',
                    'ru' => 'Чистка одного или нескольких конкретных сидений без обработки всего салона.',
                    'en' => 'Cleaning one or a few specific seats without a full interior job.',
                ],
                'description' => [
                    'hy' => 'Եթե ամբողջ սրահը չի պահանջում մաքրում, կարող եք պատվիրել միայն կոնկրետ նստատեղերի մշակում՝ ինչպես վարորդի, այնպես էլ ուղևորների տեղերի համար։',
                    'ru' => 'Если весь салон не требует чистки, можно заказать обработку только конкретных сидений — как водительского, так и пассажирских.',
                    'en' => 'If the whole interior doesn\'t need cleaning, you can book just specific seats — driver\'s or passenger\'s.',
                ],
                'seo_title' => ['hy' => 'Առանձին նստատեղի մաքրում Երևանում | Servio', 'ru' => 'Химчистка отдельного сиденья в Ереване | Servio', 'en' => 'Single Car Seat Cleaning in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի մեկ կամ մի քանի նստատեղի մասնագիտական մաքրում Երևանում։',
                    'ru' => 'Профессиональная химчистка одного или нескольких сидений автомобиля в Ереване.',
                    'en' => 'Professional cleaning for one or more individual car seats in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է, երբ միայն մեկ կամ երկու նստատեղ ունի ուշադրության կարիք։',
                    'ru' => 'Подходит, когда внимания требует только одно-два сиденья.',
                    'en' => 'Suited to cases where only one or two seats need attention.',
                ],
                'included_items' => [
                    'hy' => ['Ընտրված նստատեղերի փոշեկուլում', 'Տեղային բծերի մշակում', 'Էքստրակցիա'],
                    'ru' => ['Пылесос выбранных сидений', 'Локальная обработка пятен', 'Экстракция'],
                    'en' => ['Vacuuming the chosen seats', 'Spot stain treatment', 'Extraction'],
                ],
                'excluded_items' => [
                    'hy' => ['Հատակի կամ պանելների մաքրում', 'Ամբողջ սրահի մշակում'],
                    'ru' => ['Чистка пола или карт', 'Обработка всего салона'],
                    'en' => ['Floor or panel cleaning', 'Whole-interior treatment'],
                ],
                'process_steps' => [
                    'hy' => ['Նստատեղերի ընտրություն', 'Նյութի զննում', 'Էքստրակցիայի եղանակով մաքրում', 'Չորացում'],
                    'ru' => ['Выбор сидений', 'Осмотр материала', 'Очистка методом экстракции', 'Сушка'],
                    'en' => ['Choosing the seats', 'Material inspection', 'Extraction cleaning', 'Drying'],
                ],
                'equipment' => ['hy' => 'Էքստրակտոր և նյութին համապատասխան միջոցներ։', 'ru' => 'Экстрактор и средства, подобранные под материал.', 'en' => 'An extractor and material-matched products.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Նստատեղերի քանակը', 'Նյութի տեսակը'],
                    'ru' => ['Количество сидений', 'Тип материала'],
                    'en' => ['Number of seats', 'Material type'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Ինչպե՞ս է հաշվարկվում գինը մեկ նստատեղի համար', 'answer' => 'Գինը կախված է նստատեղերի քանակից, նյութից և աղտոտվածության աստիճանից, հաստատվում է լուսանկարներից կամ զննումից հետո։']],
                    'ru' => [['question' => 'Как рассчитывается цена за одно сиденье', 'answer' => 'Цена зависит от количества сидений, материала и степени загрязнения, подтверждается по фото или после осмотра.']],
                    'en' => [['question' => 'How is the price for one seat calculated', 'answer' => 'The price depends on the number of seats, material and soiling level, confirmed from photos or after inspection.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'leather', 'alcantara'],
                'contamination_types' => ['dust', 'food_drinks', 'coffee', 'pet_hair', 'unknown'],
                'warnings' => $warningsCommon,
            ],
            [
                'slug' => 'pre-sale-detailing',
                'icon' => 'tag',
                'sort_order' => 16,
                'status' => 'active',
                'name' => ['hy' => 'Ավտոմեքենայի սրահի նախապատրաստում վաճառքի համար', 'ru' => 'Подготовка салона автомобиля к продаже', 'en' => 'Pre-Sale Interior Prep'],
                'short_description' => [
                    'hy' => 'Սրահի համալիր մաքրում՝ ավտոմեքենան վաճառքի ներկայացնելուց առաջ։',
                    'ru' => 'Комплексная чистка салона перед выставлением автомобиля на продажу.',
                    'en' => 'A full interior clean before listing the car for sale.',
                ],
                'description' => [
                    'hy' => 'Մաքուր և թարմ սրահը դրական տպավորություն է թողնում պոտենցիալ գնորդների վրա։ Ծառայությունը ներառում է ամբողջական քիմմաքրում և հոտի ստուգում՝ ավտոմեքենան ներկայանալի տեսքի բերելու համար։',
                    'ru' => 'Чистый и свежий салон производит хорошее впечатление на потенциальных покупателей. Услуга включает полную химчистку и проверку на запах, чтобы придать автомобилю презентабельный вид.',
                    'en' => 'A clean, fresh interior makes a strong impression on prospective buyers. The service includes a full interior clean and an odor check to make the car presentable.',
                ],
                'seo_title' => ['hy' => 'Ավտոմեքենայի նախապատրաստում վաճառքի Երևանում | Servio', 'ru' => 'Подготовка авто к продаже в Ереване | Servio', 'en' => 'Pre-Sale Car Interior Prep in Yerevan | Servio'],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի սրահի մասնագիտական նախապատրաստում վաճառքից առաջ Երևանում։',
                    'ru' => 'Профессиональная подготовка салона автомобиля к продаже в Ереване.',
                    'en' => 'Professional pre-sale interior preparation for cars in Yerevan.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է ավտոմեքենան վաճառելուց առաջ ներկայանալի տեսք ապահովել ցանկացողների համար։',
                    'ru' => 'Подходит тем, кто хочет придать автомобилю презентабельный вид перед продажей.',
                    'en' => 'Suited to sellers who want the car looking its best before a sale.',
                ],
                'included_items' => [
                    'hy' => ['Ամբողջական քիմմաքրում', 'Հոտի ստուգում և անհրաժեշտության դեպքում մշակում', 'Պլաստիկի և ապակիների սրբում'],
                    'ru' => ['Полная химчистка', 'Проверка на запах и обработка при необходимости', 'Протирание пластика и стёкол'],
                    'en' => ['Full interior clean', 'Odor check and treatment if needed', 'Wiping plastic and glass'],
                ],
                'excluded_items' => [
                    'hy' => ['Կուզովի արտաքին պատրաստում', 'Տեխնիկական ախտորոշում'],
                    'ru' => ['Подготовка кузова снаружи', 'Техническая диагностика'],
                    'en' => ['Exterior body prep', 'Technical inspection'],
                ],
                'process_steps' => [
                    'hy' => ['Ամբողջական զննում', 'Ամբողջական քիմմաքրում', 'Հոտի ստուգում', 'Ավարտական ներկայացուցչական ստուգում'],
                    'ru' => ['Полный осмотр', 'Полная химчистка', 'Проверка на запах', 'Финальная презентационная проверка'],
                    'en' => ['A full inspection', 'A full interior clean', 'Odor check', 'A final presentation check'],
                ],
                'equipment' => ['hy' => 'Նույն սարքավորումն, ինչ ամբողջական քիմմաքրման համար։', 'ru' => 'То же оборудование, что и для полной химчистки.', 'en' => 'The same equipment used for a full interior clean.'],
                'duration_note' => $dryingTimeGeneric,
                'drying_time_note' => $dryingTimeGeneric,
                'price_factors' => [
                    'hy' => ['Ավտոմեքենայի տեսակը', 'Ընթացիկ վիճակը'],
                    'ru' => ['Тип автомобиля', 'Текущее состояние'],
                    'en' => ['Car body type', 'Current condition'],
                ],
                'faq' => [
                    'hy' => [['question' => 'Ավելացնու՞մ է սա ավտոմեքենայի վաճառքային գինը', 'answer' => 'Մենք չենք կարող երաշխավորել գնի ավելացում, սակայն մաքուր և թարմ սրահը դրական է ազդում գնորդի առաջին տպավորության վրա։']],
                    'ru' => [['question' => 'Повышает ли это продажную цену автомобиля', 'answer' => 'Мы не можем гарантировать рост цены, но чистый и свежий салон положительно влияет на первое впечатление покупателя.']],
                    'en' => [['question' => 'Does this raise the car\'s resale value', 'answer' => 'We can\'t guarantee a higher price, but a clean, fresh interior helps a buyer\'s first impression.']],
                ],
                'car_types' => $allCarTypes,
                'materials' => ['fabric', 'leather', 'alcantara', 'plastic', 'mixed'],
                'contamination_types' => ['dust', 'food_drinks', 'grease_oil', 'unknown'],
                'warnings' => $warningsCommon,
            ],
        ];

        foreach ($services as $data) {
            $data['category_id'] = $category->id;
            Service::query()->updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}

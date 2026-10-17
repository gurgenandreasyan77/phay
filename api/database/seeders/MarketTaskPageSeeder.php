<?php

namespace Database\Seeders;

use App\Models\MarketTaskPage;
use App\Models\Service;
use Illuminate\Database\Seeder;

class MarketTaskPageSeeder extends Seeder
{
    public function run(): void
    {
        $serviceId = fn (string $slug) => Service::query()->where('slug', $slug)->value('id');

        $pages = [
            [
                'slug_en' => 'urine-and-organic-odor-removal',
                'slug_ru' => 'udaleniye-zapaha-mochi',
                'slug_hy' => 'mizi-ev-organakan-hoteri-heracum',
                'sort_order' => 1,
                'related_service_id' => $serviceId('steam-cleaning-disinfection'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Մեզի և օրգանական հոտերի հեռացում',
                    'ru' => 'Удаление запаха мочи и органических запахов',
                    'en' => 'Removing Urine and Organic Odors',
                ],
                'intro' => [
                    'hy' => 'Մեզի հոտը բազմոցից, ներքնակից կամ գորգից դժվար է հեռացնել սովորական մաքրող միջոցներով, քանի որ հոտի աղբյուրը՝ միզաթթուն, մնում է թել-թելիկի խորքում և վերսկսում հոտը խոնավության դեպքում։ Սովորական օճառներն ու օծանելիքային սփրեյերը միայն ժամանակավորապես քողարկում են խնդիրը։',
                    'ru' => 'Запах мочи с дивана, матраса или ковра трудно устранить обычными чистящими средствами, потому что источник запаха — мочевая кислота — остаётся глубоко в волокнах и «оживает» при повышенной влажности. Обычное мыло и парфюмированные спреи лишь временно маскируют проблему.',
                    'en' => 'Urine odour on a sofa, mattress or carpet is hard to remove with ordinary cleaners, because the source — uric acid crystals — stays deep in the fibres and reactivates with humidity. Regular soap and scented sprays only mask the problem temporarily.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Ընտրեք էնզիմային կամ բակտերիալ բանաձևով միջոց, որը քայքայում է միզաթթուն, այլ ոչ թե պարզապես քողարկում հոտը',
                        'Ստուգեք միջոցի համատեղելիությունը տեքստիլի տեսակի հետ՝ չզգայուն գույների կամ նուրբ մանրաթելերի դեպքում',
                        'Մեծ կամ հին բծերի համար նախատեսեք կրկնակի մշակում',
                    ],
                    'ru' => [
                        'Выбирайте средство с энзимной или бактериальной формулой, разрушающей мочевую кислоту, а не просто маскирующей запах',
                        'Проверьте совместимость средства с типом текстиля — особенно для чувствительных к цвету или деликатных тканей',
                        'Для больших или застарелых пятен планируйте повторную обработку',
                    ],
                    'en' => [
                        'Choose an enzymatic or bacterial formula that breaks down uric acid rather than just masking the smell',
                        'Check compatibility with the fabric type, especially for colour-sensitive or delicate textiles',
                        'Plan for a second treatment on large or old stains',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Նախապես ստուգեք միջոցը աննկատ հատվածում',
                        'Հետևեք արտադրողի հրահանգներին և չխառնեք տարբեր քիմիական միջոցներ',
                        'Օգտագործեք ձեռնոցներ և ապահովեք սենյակի օդափոխությունը',
                    ],
                    'ru' => [
                        'Предварительно проверьте средство на незаметном участке',
                        'Следуйте инструкции производителя и не смешивайте разные химические средства',
                        'Используйте перчатки и обеспечьте проветривание помещения',
                    ],
                    'en' => [
                        'Patch-test the product on an inconspicuous area first',
                        'Follow the manufacturer\'s instructions and never mix different chemical products',
                        'Wear gloves and ventilate the room',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Մեզի հոտի հեռացում բազմոցից և ներքնակից | Servio',
                    'ru' => 'Средство от запаха мочи на диване и матрасе | Servio',
                    'en' => 'Removing Urine Odor from Sofas and Mattresses | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես ընտրել միջոց մեզի հոտի դեմ բազմոցի, ներքնակի կամ գորգի համար. խորհուրդներ, նախազգուշություններ և Servio-ի մասնագետի առաջարկը։',
                    'ru' => 'Как выбрать средство от запаха мочи для дивана, матраса или ковра: советы, меры предосторожности и рекомендация специалиста Servio.',
                    'en' => 'How to choose a urine-odor solution for a sofa, mattress or carpet: guidance, precautions and a recommendation from a Servio specialist.',
                ],
            ],
            [
                'slug_en' => 'blood-and-protein-stain-removal',
                'slug_ru' => 'udaleniye-krovi-i-belkovyh-pyaten',
                'slug_hy' => 'aryan-ev-spitakuc-bceri-heracum',
                'sort_order' => 2,
                'related_service_id' => $serviceId('sofa-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Արյան և սպիտակուցային բծերի հեռացում',
                    'ru' => 'Удаление крови и белковых пятен',
                    'en' => 'Removing Blood and Protein Stains',
                ],
                'intro' => [
                    'hy' => 'Արյունը, ձուն և կաթնամթերքը սպիտակուցային բծեր են, որոնք տաք ջրով մշակելիս «եփվում» են և ամրանում տեքստիլի մեջ։ Ճիշտ մոտեցումը սառը ջրով նախնական մշակումն է և հատուկ սպիտակուցազերծիչի կիրառումը։',
                    'ru' => 'Кровь, яйца и молочные продукты — это белковые пятна, которые при обработке горячей водой «сворачиваются» и закрепляются в текстиле. Правильный подход — предварительная обработка холодной водой и применение специального средства против белковых пятен.',
                    'en' => 'Blood, egg and dairy are protein-based stains that "cook" and set into fabric when treated with hot water. The right approach is a cold-water pre-treatment followed by a dedicated protein-stain remover.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Նախընտրեք էնզիմային կամ սառը ջրում լուծվող միջոց',
                        'Խուսափեք տաք ջրից բծի ողջ մշակման ընթացքում',
                        'Հին, չորացած բծերի համար նախատեսեք ավելի երկար ազդեցության ժամանակ',
                    ],
                    'ru' => [
                        'Отдавайте предпочтение энзимным средствам или тем, что растворяются в холодной воде',
                        'Избегайте горячей воды на всех этапах обработки пятна',
                        'Для старых, засохших пятен закладывайте более долгое время воздействия',
                    ],
                    'en' => [
                        'Prefer an enzymatic remover or one designed to work with cold water',
                        'Avoid hot water at every stage of treating the stain',
                        'Allow a longer dwell time for old, dried-in stains',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք միջոցը աննկատ հատվածում',
                        'Մի՛ շփեք ուժեղ՝ բծը կարող է տարածվել ավելի մեծ մակերեսի վրա',
                        'Հետևեք արտադրողի հրահանգին',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Не трите пятно слишком интенсивно — оно может растечься на большую площадь',
                        'Следуйте инструкции производителя',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Avoid aggressive scrubbing — it can spread the stain further',
                        'Follow the manufacturer\'s instructions',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Արյան և սպիտակուցային բծերի հեռացում | Servio',
                    'ru' => 'Как удалить кровь и белковые пятна с ткани | Servio',
                    'en' => 'How to Remove Blood and Protein Stains | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես ընտրել միջոց արյան և սպիտակուցային բծերի դեմ, ինչ խուսափել և ինչ խորհուրդ է տալիս Servio-ի մասնագետը։',
                    'ru' => 'Как выбрать средство против пятен крови и других белковых загрязнений, чего избегать и что рекомендует специалист Servio.',
                    'en' => 'How to choose a remover for blood and other protein stains, what to avoid, and what a Servio specialist recommends.',
                ],
            ],
            [
                'slug_en' => 'ink-and-marker-removal',
                'slug_ru' => 'udaleniye-chernil-i-markera',
                'slug_hy' => 'melani-ev-markeri-heracum',
                'sort_order' => 3,
                'related_service_id' => $serviceId('sofa-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Մելանի և մարկերի հետքերի հեռացում',
                    'ru' => 'Удаление чернил и следов маркера',
                    'en' => 'Removing Ink and Marker Stains',
                ],
                'intro' => [
                    'hy' => 'Գրիչի կամ մարկերի հետքերը պարունակում են ներկանյութեր և լուծիչներ, որոնք արագ ներծծվում են տեքստիլի մեջ։ Այս տեսակի բծերը պահանջում են լուծիչահիմք միջոց՝ ընտրված կոնկրետ մակերեսի հանդուրժողականությանը համապատասխան։',
                    'ru' => 'Следы от ручки или маркера содержат красители и растворители, которые быстро впитываются в текстиль. Такие пятна требуют средства на основе растворителя, подобранного с учётом стойкости конкретной поверхности.',
                    'en' => 'Pen or marker marks contain dyes and solvents that soak into fabric quickly. These stains call for a solvent-based remover chosen to suit how tolerant the specific surface is.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Ստուգեք՝ միջոցը հարմար է տվյալ տեքստիլի կամ կաշվե մակերեսի համար',
                        'Աշխատեք բծի եզրերից դեպի կենտրոն՝ տարածումից խուսափելու համար',
                        'Թարմ բծերը հեռացվում են շատ ավելի հեշտությամբ, քան հները',
                    ],
                    'ru' => [
                        'Проверьте, подходит ли средство для конкретного текстиля или кожаной поверхности',
                        'Обрабатывайте пятно от края к центру, чтобы избежать его распространения',
                        'Свежие пятна удаляются значительно легче застарелых',
                    ],
                    'en' => [
                        'Confirm the product suits the specific fabric or leather surface',
                        'Work from the edge of the stain toward the centre to avoid spreading it',
                        'Fresh stains come out far more easily than old ones',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում, հատկապես գունավոր և կաշվե մակերեսների վրա',
                        'Ապահովեք սենյակի օդափոխությունը՝ լուծիչահիմք միջոցներ օգտագործելիս',
                        'Հեռու պահեք բաց կրակից',
                    ],
                    'ru' => [
                        'Проверяйте на незаметном участке, особенно на цветных и кожаных поверхностях',
                        'Обеспечьте проветривание при использовании средств на основе растворителей',
                        'Держите вдали от открытого огня',
                    ],
                    'en' => [
                        'Patch-test first, especially on coloured or leather surfaces',
                        'Ventilate the room when using solvent-based products',
                        'Keep away from open flame',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Մարկերի և մելանի հետքերի հեռացում | Servio',
                    'ru' => 'Как удалить чернила и маркер с мебели | Servio',
                    'en' => 'How to Remove Ink and Marker Stains | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես ընտրել միջոց մարկերի և մելանի հետքերի դեմ կահույքի և տեքստիլի վրա։',
                    'ru' => 'Как выбрать средство против чернил и маркера на мебели и текстиле, и какие меры предосторожности соблюдать.',
                    'en' => 'How to choose a remover for ink and marker stains on furniture and textiles, and what precautions to take.',
                ],
            ],
            [
                'slug_en' => 'grease-and-oil-removal',
                'slug_ru' => 'udaleniye-zhira-i-masla',
                'slug_hy' => 'carpi-ev-yuxi-heracum',
                'sort_order' => 4,
                'related_service_id' => $serviceId('general-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Ճարպի և յուղի հեռացում',
                    'ru' => 'Удаление жира и масла',
                    'en' => 'Removing Grease and Oil Stains',
                ],
                'intro' => [
                    'hy' => 'Ճարպային բծերը հեռացվում են միայն ալկալային կամ մասնագիտացված ճարպահանիչ միջոցներով, քանի որ ջուրը ինքնին չի քայքայում ճարպի մոլեկուլները։ Խոհանոցում, ավտոտնակում կամ տեքստիլի վրա ճարպի հեռացումը պահանջում է տարբեր մոտեցում՝ կախված մակերեսից։',
                    'ru' => 'Жирные пятна удаляются только щелочными или специализированными обезжиривающими средствами, поскольку вода сама по себе не расщепляет молекулы жира. На кухне, в гараже или на текстиле подход к удалению жира отличается в зависимости от поверхности.',
                    'en' => 'Grease stains only lift with alkaline or dedicated degreasing products, since water alone doesn\'t break down fat molecules. Removing grease from a kitchen, garage or textile calls for a different approach depending on the surface.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Կոշտ մակերեսների համար ընտրեք ալկալային ճարպահանիչ, տեքստիլի համար՝ մեղմ, հատուկ բանաձև',
                        'Ստուգեք համատեղելիությունը լաքապատ կամ նուրբ մակերեսների հետ',
                        'Հին, սևացած ճարպաբծերի համար անհրաժեշտ է ավելի երկար ազդեցության ժամանակ',
                    ],
                    'ru' => [
                        'Для твёрдых поверхностей выбирайте щелочной обезжириватель, для текстиля — более мягкую специализированную формулу',
                        'Проверяйте совместимость с лакированными или деликатными поверхностями',
                        'Для застарелых потемневших жировых пятен нужно больше времени воздействия',
                    ],
                    'en' => [
                        'Use an alkaline degreaser for hard surfaces and a gentler, dedicated formula for textiles',
                        'Check compatibility with lacquered or delicate surfaces',
                        'Old, darkened grease stains need a longer dwell time',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Աշխատեք ձեռնոցներով և լավ օդափոխվող տարածքում',
                        'Մի՛ խառնեք ալկալային և թթվային միջոցներ',
                        'Փորձարկեք աննկատ հատվածում',
                    ],
                    'ru' => [
                        'Работайте в перчатках и в хорошо проветриваемом помещении',
                        'Не смешивайте щелочные и кислотные средства',
                        'Проверьте средство на незаметном участке',
                    ],
                    'en' => [
                        'Wear gloves and work in a well-ventilated space',
                        'Never mix alkaline and acidic products',
                        'Patch-test on an inconspicuous area',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Ինչպես հեռացնել ճարպն ու յուղը | Servio',
                    'ru' => 'Как удалить жир и масляные пятна | Servio',
                    'en' => 'How to Remove Grease and Oil Stains | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոց ընտրել ճարպի ու յուղի բծերի դեմ խոհանոցում, մեքենայում կամ տեքստիլի վրա։',
                    'ru' => 'Какое средство выбрать против жирных и масляных пятен на кухне, в автомобиле или на текстиле.',
                    'en' => 'Which product to choose against grease and oil stains in the kitchen, a car, or on textiles.',
                ],
            ],
            [
                'slug_en' => 'coffee-wine-and-juice-stain-removal',
                'slug_ru' => 'udaleniye-kofe-vina-i-sokov',
                'slug_hy' => 'surci-gini-ev-hyuti-bceri-heracum',
                'sort_order' => 5,
                'related_service_id' => $serviceId('sofa-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Սուրճի, գինու և հյութի բծերի հեռացում',
                    'ru' => 'Удаление пятен от кофе, вина и сока',
                    'en' => 'Removing Coffee, Wine and Juice Stains',
                ],
                'intro' => [
                    'hy' => 'Սուրճը, կարմիր գինին և վառ գունավոր հյութերը պարունակում են բնական ներկանյութեր (տանիններ), որոնք արագ ամրանում են տեքստիլի մեջ, հատկապես չորանալուց հետո։ Որքան արագ սկսվի մշակումը, այնքան ավելի հեշտ է հեռացումը։',
                    'ru' => 'Кофе, красное вино и ярко окрашенные соки содержат природные красители (танины), которые быстро закрепляются в текстиле, особенно после высыхания. Чем раньше начата обработка, тем легче удалить пятно.',
                    'en' => 'Coffee, red wine and brightly coloured juices contain natural tannin dyes that set into fabric quickly, especially once dry. The sooner treatment starts, the easier the stain lifts.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Ընտրեք տանինային բծերի համար նախատեսված միջոց',
                        'Խուսափեք տաք ջրից՝ այն կարող է ամրացնել գույնը',
                        'Բծի եզրերը մշակեք առաջինը՝ տարածումից խուսափելու համար',
                    ],
                    'ru' => [
                        'Выбирайте средство, предназначенное для танинных пятен',
                        'Избегайте горячей воды — она может закрепить цвет',
                        'Сначала обрабатывайте края пятна, чтобы избежать его растекания',
                    ],
                    'en' => [
                        'Choose a product designed for tannin-based stains',
                        'Avoid hot water — it can set the colour further',
                        'Treat the edge of the stain first to avoid spreading it',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Հետևեք արտադրողի հրահանգներին',
                        'Մի՛ շփեք ուժեղ',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Следуйте инструкции производителя',
                        'Не трите пятно интенсивно',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Follow the manufacturer\'s instructions',
                        'Avoid aggressive scrubbing',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Սուրճի և գինու բծերի հեռացում | Servio',
                    'ru' => 'Как удалить пятна от кофе и вина | Servio',
                    'en' => 'How to Remove Coffee and Wine Stains | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես ընտրել միջոց սուրճի, գինու և հյութի բծերի դեմ բազմոցի կամ գորգի վրա։',
                    'ru' => 'Как выбрать средство от пятен кофе, вина и сока на диване или ковре.',
                    'en' => 'How to choose a remover for coffee, wine and juice stains on a sofa or carpet.',
                ],
            ],
            [
                'slug_en' => 'rust-stain-removal',
                'slug_ru' => 'udaleniye-rzhavchiny',
                'slug_hy' => 'jangi-heracum',
                'sort_order' => 6,
                'related_service_id' => $serviceId('carpet-textile-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Ժանգի հեռացում',
                    'ru' => 'Удаление ржавчины',
                    'en' => 'Removing Rust Stains',
                ],
                'intro' => [
                    'hy' => 'Ժանգային բծերը մետաղական օքսիդներ են, որոնք սովորական մաքրող միջոցները չեն լուծում. անհրաժեշտ է հատուկ, թթվային բանաձևով միջոց՝ հատուկ նախատեսված ժանգի հեռացման համար։',
                    'ru' => 'Пятна ржавчины — это оксиды металлов, которые не растворяются обычными чистящими средствами. Требуется специальный, кислотный состав, разработанный именно для удаления ржавчины.',
                    'en' => 'Rust stains are metal oxides that ordinary cleaners can\'t dissolve — they need a dedicated acidic formula made specifically for rust removal.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Օգտագործեք հատուկ ժանգահան միջոց, ոչ թե ունիվերսալ բծաթուր',
                        'Ստուգեք համատեղելիությունը մակերեսի հետ, հատկապես գույնավոր տեքստիլի կամ քարի դեպքում',
                        'Խուսափեք մետաղական խոզանակներից, որոնք կարող են նոր ժանգի աղբյուր դառնալ',
                    ],
                    'ru' => [
                        'Используйте специализированное средство против ржавчины, а не универсальный пятновыводитель',
                        'Проверьте совместимость с поверхностью, особенно с цветным текстилем или камнем',
                        'Избегайте металлических щёток — они могут стать источником новой ржавчины',
                    ],
                    'en' => [
                        'Use a dedicated rust remover rather than a general-purpose stain remover',
                        'Check compatibility with the surface, especially coloured textile or stone',
                        'Avoid metal brushes, which can introduce fresh rust of their own',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Աշխատեք ձեռնոցներով, քանի որ միջոցը սովորաբար թթվային է',
                        'Խուսափեք մետաղական մակերեսների հետ երկարատև շփումից',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Работайте в перчатках — такие средства обычно кислотные',
                        'Избегайте длительного контакта с металлическими поверхностями',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Wear gloves — these products are typically acidic',
                        'Avoid prolonged contact with metal surfaces',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Ինչպես հեռացնել ժանգը | Servio',
                    'ru' => 'Как удалить ржавчину с текстиля | Servio',
                    'en' => 'How to Remove Rust Stains | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոց ընտրել ժանգային բծերի դեմ և ինչ նախազգուշություններ պահպանել։',
                    'ru' => 'Какое средство выбрать против ржавчины и какие меры предосторожности соблюдать.',
                    'en' => 'Which product to choose against rust stains and what precautions to take.',
                ],
            ],
            [
                'slug_en' => 'glue-and-gum-removal',
                'slug_ru' => 'udaleniye-kleya-i-zhevatelnoy-rezinki',
                'slug_hy' => 'sosinji-ev-jvaci-heracum',
                'sort_order' => 7,
                'related_service_id' => $serviceId('carpet-textile-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Սոսինձի և ծամոնի հեռացում',
                    'ru' => 'Удаление клея и жевательной резинки',
                    'en' => 'Removing Glue and Chewing Gum',
                ],
                'intro' => [
                    'hy' => 'Սոսինձն ու ծամոնը կպչուն նյութեր են, որոնք սովորաբար հեռացվում են ջերմաստիճանային մեթոդով (սառեցում կամ մեղմ տաքացում)՝ զուգակցված հատուկ լուծիչի հետ, այլ ոչ թե ուժեղ շփումով։',
                    'ru' => 'Клей и жевательная резинка — липкие вещества, которые обычно удаляют температурным способом (заморозка или лёгкий нагрев) в сочетании со специальным растворителем, а не интенсивным трением.',
                    'en' => 'Glue and chewing gum are sticky substances usually removed with a temperature trick (freezing or gentle warming) combined with a dedicated solvent, rather than aggressive rubbing.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Ընտրեք լուծիչահիմք միջոց, որը հարմար է տվյալ տեքստիլի կամ մակերեսի համար',
                        'Համադրեք սառեցման մեթոդի հետ՝ ծամոնի դեպքում',
                        'Ստուգեք՝ միջոցը չի վնասում գույնը կամ մակերեսի ծածկույթը',
                    ],
                    'ru' => [
                        'Выбирайте средство на основе растворителя, подходящее для конкретного текстиля или поверхности',
                        'Для жевательной резинки комбинируйте с методом заморозки',
                        'Проверьте, что средство не повреждает цвет или покрытие поверхности',
                    ],
                    'en' => [
                        'Choose a solvent-based product suited to the specific fabric or surface',
                        'For chewing gum, combine it with the freezing method',
                        'Check that the product won\'t damage the colour or surface finish',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Ապահովեք օդափոխություն՝ լուծիչահիմք միջոցներ օգտագործելիս',
                        'Հեռու պահեք բաց կրակից',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Обеспечьте проветривание при использовании растворителей',
                        'Держите вдали от открытого огня',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Ventilate the room when using solvent-based products',
                        'Keep away from open flame',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Ինչպես հեռացնել սոսինձն ու ծամոնը | Servio',
                    'ru' => 'Как удалить клей и жвачку с ткани | Servio',
                    'en' => 'How to Remove Glue and Chewing Gum | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոց և մեթոդ ընտրել սոսինձի ու ծամոնի հեռացման համար տեքստիլից և գորգից։',
                    'ru' => 'Какое средство и метод выбрать для удаления клея и жвачки с ткани и ковра.',
                    'en' => 'Which product and method to choose for removing glue and chewing gum from fabric and carpet.',
                ],
            ],
            [
                'slug_en' => 'pet-odor-elimination',
                'slug_ru' => 'ustraneniye-zapaha-zhivotnyh',
                'slug_hy' => 'kendaniner-hot-heracum',
                'sort_order' => 8,
                'related_service_id' => $serviceId('steam-cleaning-disinfection'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Կենդանիների հոտի վերացում',
                    'ru' => 'Устранение запаха животных',
                    'en' => 'Eliminating Pet Odors',
                ],
                'intro' => [
                    'hy' => 'Կենդանիների հոտը կուտակվում է գորգերում, բազմոցներում և տեքստիլում՝ սպիտակուցային մասնիկների և միզաթթվի հետևանքով։ Օդի թարմացուցիչները ժամանակավոր լուծում են. արդյունավետ մոտեցումը էնզիմային մշակումն է աղբյուրի մակարդակում։',
                    'ru' => 'Запах животных накапливается в коврах, диванах и текстиле из-за белковых частиц и мочевой кислоты. Освежители воздуха — временное решение; эффективный подход — энзимная обработка на уровне источника запаха.',
                    'en' => 'Pet odour builds up in carpets, sofas and fabric from protein particles and uric acid. Air fresheners are a temporary fix — the effective approach is enzymatic treatment at the source.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Ընտրեք էնզիմային կամ բակտերիալ նեյտրալիզատոր, ոչ թե միայն բուրավետացնող սփրեյ',
                        'Մշակեք ոչ միայն տեսանելի հատվածները, այլև հարակից գոտիները',
                        'Կրկնվող դեպքերում մշակումը կրկնեք մի քանի անգամ',
                    ],
                    'ru' => [
                        'Выбирайте энзимный или бактериальный нейтрализатор, а не просто ароматизирующий спрей',
                        'Обрабатывайте не только видимые пятна, но и прилегающие зоны',
                        'При повторяющихся случаях повторяйте обработку несколько раз',
                    ],
                    'en' => [
                        'Choose an enzymatic or bacterial neutraliser, not just a fragranced spray',
                        'Treat the surrounding area, not just the visible spot',
                        'For recurring cases, repeat the treatment several times',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Հեռու պահեք կենդանիներից՝ մինչև մակերեսը լիովին չորանա',
                        'Հետևեք արտադրողի հրահանգներին',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Не подпускайте животных к поверхности до полного высыхания',
                        'Следуйте инструкции производителя',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Keep pets away from the surface until it\'s fully dry',
                        'Follow the manufacturer\'s instructions',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Ինչպես վերացնել կենդանիների հոտը | Servio',
                    'ru' => 'Как устранить запах животных дома | Servio',
                    'en' => 'How to Eliminate Pet Odors at Home | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոց ընտրել կենդանիների հոտի դեմ գորգից, բազմոցից և տեքստիլից։',
                    'ru' => 'Какое средство выбрать против запаха животных на ковре, диване и текстиле.',
                    'en' => 'Which product to choose against pet odor on carpets, sofas and fabric.',
                ],
            ],
            [
                'slug_en' => 'sofa-dry-cleaning-chemicals',
                'slug_ru' => 'himiya-dlya-himchistki-divanov',
                'slug_hy' => 'bazmoci-qimmaqrman-qimia',
                'sort_order' => 9,
                'related_service_id' => $serviceId('sofa-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Բազմոցի քիմմաքրման միջոցներ',
                    'ru' => 'Химия для химчистки диванов',
                    'en' => 'Sofa Dry-Cleaning Chemicals',
                ],
                'intro' => [
                    'hy' => 'Բազմոցի քիմմաքրումը սովորաբար ներառում է մի քանի փուլ՝ նախնական փոշեհեռացում, պրեսփրեյի կիրառում, խորքային մշակում շամպունով կամ էքստրակտորով և ողողում կամ չեզոքացում։ Ընտրությունը կախված է երեսպատման տեսակից՝ տեքստիլ, կաշի կամ էկոկաշի։',
                    'ru' => 'Химчистка дивана обычно включает несколько этапов: удаление пыли, нанесение преспрея, глубокую обработку шампунем или экстрактором и ополаскивание либо нейтрализацию. Выбор средств зависит от типа обивки — текстиль, кожа или экокожа.',
                    'en' => 'Sofa dry-cleaning usually involves several steps: dust removal, applying a prespray, a deep clean with shampoo or an extractor, and a rinse or neutralising step. Product choice depends on the upholstery type — fabric, leather or faux leather.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Տեքստիլե երեսպատման համար՝ pH-չեզոք շամպուն, կաշվե՝ հատուկ կաշվե խնամքի միջոց',
                        'Ստուգեք արտադրողի պիտակի խորհուրդները (W, S, WS, X կոդեր)',
                        'Բարդ բծերի համար նախապես կիրառեք նպատակային բծաթուր',
                    ],
                    'ru' => [
                        'Для текстильной обивки — pH-нейтральный шампунь, для кожи — специальное средство по уходу за кожей',
                        'Проверьте рекомендации на этикетке производителя (коды W, S, WS, X)',
                        'Для сложных пятен предварительно используйте целевой пятновыводитель',
                    ],
                    'en' => [
                        'Use a pH-neutral shampoo for fabric upholstery and a dedicated leather-care product for leather',
                        'Check the manufacturer\'s care-label code (W, S, WS, X)',
                        'Pre-treat stubborn stains with a targeted stain remover first',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Մի՛ թրջեք չափազանց ինտենսիվ՝ փայտե շրջանակի կամ սպունգի վնասումից խուսափելու համար',
                        'Ապահովեք լավ չորացում՝ բորբոսի կանխարգելման համար',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Не переувлажняйте обивку, чтобы не повредить деревянный каркас или наполнитель',
                        'Обеспечьте хорошую просушку во избежание плесени',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Avoid over-wetting, which can damage the wooden frame or padding',
                        'Ensure thorough drying to prevent mould',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Բազմոցի քիմմաքրման միջոցներ | Servio',
                    'ru' => 'Химия для химчистки диванов в Армении | Servio',
                    'en' => 'Sofa Dry-Cleaning Chemicals in Armenia | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոցներ են անհրաժեշտ բազմոցի ինքնուրույն քիմմաքրման համար և երբ դիմել Servio մասնագետին։',
                    'ru' => 'Какие средства нужны для самостоятельной химчистки дивана и когда стоит обратиться к специалисту Servio.',
                    'en' => 'What products are needed for DIY sofa dry-cleaning, and when to call in a Servio specialist instead.',
                ],
            ],
            [
                'slug_en' => 'mattress-dry-cleaning-chemicals',
                'slug_ru' => 'himiya-dlya-himchistki-matrasov',
                'slug_hy' => 'nerqnaki-qimmaqrman-qimia',
                'sort_order' => 10,
                'related_service_id' => $serviceId('mattress-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Ներքնակի քիմմաքրման միջոցներ',
                    'ru' => 'Химия для химчистки матрасов',
                    'en' => 'Mattress Dry-Cleaning Chemicals',
                ],
                'intro' => [
                    'hy' => 'Ներքնակը շփվում է մաշկի հետ ամեն գիշեր, ուստի նրա մաքրման միջոցները պետք է լինեն մեղմ, առանց ագրեսիվ բաղադրիչների, բայց միևնույն ժամանակ արդյունավետ փոշեկուլների, քրտինքի և բծերի դեմ։',
                    'ru' => 'Матрас каждую ночь контактирует с кожей, поэтому средства для его чистки должны быть мягкими, без агрессивных компонентов, но при этом эффективными против пылевых клещей, пота и пятен.',
                    'en' => 'A mattress is in nightly contact with skin, so the products used on it need to be gentle and free of harsh ingredients, while still being effective against dust mites, sweat and stains.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Նախընտրեք փրփուրահիմք կամ չոր փոշեհիմք միջոցներ՝ նվազագույն թրջումով',
                        'Ստուգեք հակաալերգիկ և հիպոալերգեն բանաձևերի հասանելիությունը',
                        'Բծերի համար օգտագործեք նպատակային, ոչ ագրեսիվ բծաթուր',
                    ],
                    'ru' => [
                        'Отдавайте предпочтение пенным или сухим порошковым средствам с минимальным увлажнением',
                        'Проверяйте наличие гипоаллергенных формул',
                        'Для пятен используйте целевой, неагрессивный пятновыводитель',
                    ],
                    'en' => [
                        'Prefer foam-based or dry-powder products with minimal moisture',
                        'Look for hypoallergenic formulas where available',
                        'Use a targeted, non-aggressive stain remover for spots',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Ապահովեք երկարատև չորացում մինչև կրկին օգտագործումը',
                        'Խուսափեք ավելցուկ թրջումից՝ ներքին լցանյութի բորբոսից խուսափելու համար',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Обеспечьте достаточную просушку перед повторным использованием',
                        'Избегайте избыточного увлажнения, чтобы наполнитель не заплесневел',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Allow enough drying time before the mattress is used again',
                        'Avoid over-wetting so the internal filling doesn\'t develop mould',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Ներքնակի քիմմաքրման միջոցներ | Servio',
                    'ru' => 'Химия для химчистки матрасов | Servio',
                    'en' => 'Mattress Dry-Cleaning Chemicals | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոցներ ընտրել ներքնակի ինքնուրույն մաքրման համար և ինչու պատվիրել Servio-ի մասնագիտական ծառայությունը։',
                    'ru' => 'Какие средства выбрать для самостоятельной чистки матраса и почему стоит заказать профессиональную услугу Servio.',
                    'en' => 'Which products to choose for DIY mattress cleaning, and why a professional Servio service may be worth booking.',
                ],
            ],
            [
                'slug_en' => 'carpet-dry-cleaning-chemicals',
                'slug_ru' => 'himiya-dlya-himchistki-kovrov',
                'slug_hy' => 'gorgeri-qimmaqrman-qimia',
                'sort_order' => 11,
                'related_service_id' => $serviceId('carpet-textile-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Գորգերի քիմմաքրման միջոցներ',
                    'ru' => 'Химия для химчистки ковров',
                    'en' => 'Carpet Dry-Cleaning Chemicals',
                ],
                'intro' => [
                    'hy' => 'Գորգի քիմմաքրումը սովորաբար հետևում է հաջորդականությանը՝ պրեսփրեյ, խորքային մշակում շամպունով կամ էքստրակտորով, և թթվային ողողում՝ հիմքի մնացորդները չեզոքացնելու և գույնը վերականգնելու համար։',
                    'ru' => 'Химчистка ковра обычно следует последовательности: преспрей, глубокая обработка шампунем или экстрактором, кислотное ополаскивание для нейтрализации щелочных остатков и восстановления цвета.',
                    'en' => 'Carpet cleaning typically follows a sequence: prespray, a deep clean with shampoo or an extractor, and an acid rinse to neutralise alkaline residue and help restore colour.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Բնական մանրաթելերի (բուրդ) համար ընտրեք մեղմ, ցածր ալկալայնությամբ միջոցներ',
                        'Սինթետիկ գորգերի համար կարելի է կիրառել ավելի ուժեղ պրեսփրեյ',
                        'Միշտ ավարտեք թթվային ողողումով, եթե օգտագործվել է ալկալային միջոց',
                    ],
                    'ru' => [
                        'Для натуральных волокон (шерсть) выбирайте мягкие средства с низкой щёлочностью',
                        'Для синтетических ковров можно использовать более сильный преспрей',
                        'Всегда завершайте кислотным ополаскиванием, если применялось щелочное средство',
                    ],
                    'en' => [
                        'Choose gentle, low-alkalinity products for natural fibres like wool',
                        'A stronger prespray can be used on synthetic carpets',
                        'Always finish with an acid rinse if an alkaline product was used',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Ապահովեք լավ օդափոխություն և չորացում',
                        'Մի՛ քայլեք գորգի վրա մինչև այն լիովին չորանա',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Обеспечьте хорошее проветривание и просушку',
                        'Не наступайте на ковёр до полного высыхания',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Ensure good ventilation and drying',
                        'Avoid walking on the carpet until it\'s fully dry',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Գորգերի քիմմաքրման միջոցներ Հայաստանում | Servio',
                    'ru' => 'Химия для химчистки ковров в Армении | Servio',
                    'en' => 'Carpet Cleaning Chemicals in Armenia | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոցներ են անհրաժեշտ գորգի ինքնուրույն քիմմաքրման համար և ինչու ընտրել Servio-ի ծառայությունը։',
                    'ru' => 'Какие средства нужны для самостоятельной химчистки ковра и почему стоит выбрать услугу Servio.',
                    'en' => 'What products are needed for DIY carpet cleaning, and why a Servio service may be the safer choice.',
                ],
            ],
            [
                'slug_en' => 'car-interior-dry-cleaning-chemicals',
                'slug_ru' => 'himiya-dlya-himchistki-avtosalona',
                'slug_hy' => 'avtosaloni-qimmaqrman-qimia',
                'sort_order' => 12,
                'related_service_id' => null,
                'status' => 'published',
                'title' => [
                    'hy' => 'Ավտոսալոնի քիմմաքրման միջոցներ',
                    'ru' => 'Химия для химчистки автомобильного салона',
                    'en' => 'Car Interior Dry-Cleaning Chemicals',
                ],
                'intro' => [
                    'hy' => 'Ավտոսալոնի մաքրումը յուրահատուկ է փակ, սահմանափակ տարածքի և տարբեր նյութերի՝ տեքստիլ, էկոկաշի, պլաստիկ և արագածեր համակցության պատճառով։ Յուրաքանչյուր մակերես պահանջում է իր հատուկ միջոցը։',
                    'ru' => 'Чистка салона автомобиля имеет свои особенности из-за замкнутого пространства и сочетания разных материалов — текстиля, экокожи, пластика и потолочной обивки. Каждая поверхность требует своего средства.',
                    'en' => 'Cleaning a car interior has its own quirks due to the enclosed space and mix of materials — fabric, faux leather, plastic trim and headliner. Each surface calls for its own product.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Տեքստիլե նստատեղերի համար՝ պրեսփրեյ և շամպուն, պլաստիկի համար՝ մեղմ մաքրիչ',
                        'Էկոկաշվե մակերեսների համար օգտագործեք հատուկ, ոչ լուծիչահիմք միջոց',
                        'Ապահովեք լավ օդափոխություն՝ ուժեղ հոտի կուտակումից խուսափելու համար',
                    ],
                    'ru' => [
                        'Для текстильных сидений — преспрей и шампунь, для пластика — мягкий очиститель',
                        'Для экокожи используйте специальное средство без агрессивных растворителей',
                        'Обеспечьте хорошее проветривание, чтобы избежать скопления сильного запаха',
                    ],
                    'en' => [
                        'Use a prespray and shampoo for fabric seats, and a mild cleaner for plastic trim',
                        'Use a dedicated, solvent-free product for faux-leather surfaces',
                        'Ventilate well to avoid a build-up of strong fumes in a closed cabin',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Փորձարկեք աննկատ հատվածում',
                        'Խուսափեք ավելորդ թրջումից՝ էլեկտրոնիկայի վնասումից խուսափելու համար',
                        'Չորացրեք մեքենան բաց դռներով',
                    ],
                    'ru' => [
                        'Проверьте средство на незаметном участке',
                        'Избегайте избыточного увлажнения, чтобы не повредить электронику',
                        'Просушивайте салон с открытыми дверями',
                    ],
                    'en' => [
                        'Patch-test on an inconspicuous area',
                        'Avoid over-wetting to prevent damage to onboard electronics',
                        'Dry the cabin with the doors open',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Ավտոսալոնի քիմմաքրման միջոցներ | Servio',
                    'ru' => 'Химия для химчистки салона автомобиля | Servio',
                    'en' => 'Car Interior Cleaning Chemicals | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ միջոցներ են անհրաժեշտ ավտոսալոնի տարբեր մակերեսների մաքրման համար։',
                    'ru' => 'Какие средства нужны для чистки разных поверхностей автомобильного салона.',
                    'en' => 'Which products are needed to clean the different surfaces inside a car interior.',
                ],
            ],
            [
                'slug_en' => 'extractor-and-vacuum-chemicals',
                'slug_ru' => 'himiya-dlya-moyushchego-pylesosa',
                'slug_hy' => 'ekstraktori-qimia',
                'sort_order' => 13,
                'related_service_id' => $serviceId('carpet-textile-cleaning'),
                'status' => 'published',
                'title' => [
                    'hy' => 'Քիմիա մոծիկ էքստրակտորի համար',
                    'ru' => 'Химия для моющего пылесоса и экстрактора',
                    'en' => 'Chemicals for Extractors and Wet Vacuums',
                ],
                'intro' => [
                    'hy' => 'Էքստրակտորի կամ մոծիկ փոշեկուլի համար նախատեսված միջոցները պետք է ցածր փրփրող լինեն, հակառակ դեպքում փրփուրը կարող է վնասել շարժիչը կամ նվազեցնել ներծծման ուժը։ Սովորական ձեռքի շամպունները հարմար չեն այս սարքավորման համար։',
                    'ru' => 'Средства для экстрактора или моющего пылесоса должны быть низкопенными — иначе пена может повредить двигатель или снизить силу всасывания. Обычные ручные шамп��ни для этого оборудования не подходят.',
                    'en' => 'Products used in an extractor or wet vacuum need to be low-foam — otherwise foam can damage the motor or reduce suction power. Regular hand-application shampoos aren\'t suitable for this equipment.',
                ],
                'selection_rules' => [
                    'hy' => [
                        'Ընտրեք հատուկ «low-foam» շամպուն էքստրակտորի համար',
                        'Ունեցեք պենոգասիչ՝ պատահական չափից ավելի փրփրման դեպքերի համար',
                        'Հետևեք արտադրողի նշված նոսրացման հարաբերակցությանը՝ սարքավորումը չվնասելու համար',
                    ],
                    'ru' => [
                        'Выбирайте специальный низкопенный шампунь для экстрактора',
                        'Держите под рукой пеногаситель на случай чрезмерного пенообразования',
                        'Следуйте указанному производителем соотношению разведения, чтобы не повредить оборудование',
                    ],
                    'en' => [
                        'Choose a dedicated low-foam shampoo made for extractors',
                        'Keep a defoamer on hand in case of excess foaming',
                        'Follow the manufacturer\'s stated dilution ratio to avoid damaging the equipment',
                    ],
                ],
                'precautions' => [
                    'hy' => [
                        'Հետևեք սարքի արտադրողի հրահանգներին քիմիայի տեսակի վերաբերյալ',
                        'Մի՛ խառնեք տարբեր արտադրողների միջոցներ',
                        'Պահեք երեխաներից հեռու',
                    ],
                    'ru' => [
                        'Следуйте инструкциям производителя оборудования по типу химии',
                        'Не смешивайте средства разных производителей',
                        'Храните вдали от детей',
                    ],
                    'en' => [
                        'Follow the equipment manufacturer\'s guidance on the type of chemical to use',
                        'Don\'t mix products from different manufacturers',
                        'Keep out of reach of children',
                    ],
                ],
                'seo_title' => [
                    'hy' => 'Քիմիա մոծիկ էքստրակտորի համար | Servio',
                    'ru' => 'Химия для моющего пылесоса и экстрактора | Servio',
                    'en' => 'Chemicals for Extractors and Wet Vacuums | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ քիմիա է հարմար էքստրակտորի և մոծիկ փոշեկուլի համար՝ առանց սարքավորումը վնասելու։',
                    'ru' => 'Какая химия подходит для экстрактора и моющего пылесоса, не повреждая оборудование.',
                    'en' => 'Which chemicals suit an extractor or wet vacuum without damaging the equipment.',
                ],
            ],
        ];

        foreach ($pages as $page) {
            MarketTaskPage::query()->updateOrCreate(['slug_en' => $page['slug_en']], $page);
        }
    }
}

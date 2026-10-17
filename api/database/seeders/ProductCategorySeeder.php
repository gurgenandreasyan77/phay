<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug_hy' => 'bazmoci-qimmaqrman-mijocner',
                'slug_ru' => 'sredstva-dlya-himchistki-mebeli',
                'slug_en' => 'upholstery-cleaners',
                'icon' => 'sofa',
                'sort_order' => 1,
                'name' => [
                    'hy' => 'Փափուկ կահույքի քիմմաքրման միջոցներ',
                    'ru' => 'Средства для химчистки мягкой мебели',
                    'en' => 'Upholstery Cleaning Chemicals',
                ],
                'short_description' => [
                    'hy' => 'Մասնագիտական միջոցներ բազմոցների, բազկաթոռների և աթոռների տեքստիլի ու կաշվե երեսպատման խորքային մաքրման համար։ Հարմար են ինչպես կանոնավոր խնամքի, այնպես էլ բարդ բծերի դեմ պայքարի համար։',
                    'ru' => 'Профессиональные средства для глубокой химчистки текстильной и кожаной обивки диванов, кресел и стульев. Подходят как для регулярного ухода, так и для удаления стойких загрязнений.',
                    'en' => 'Professional-grade chemicals for deep-cleaning fabric and leather upholstery on sofas, armchairs and chairs — suited to routine maintenance as well as stubborn stains.',
                ],
            ],
            [
                'slug_hy' => 'gorgeri-qimmaqrman-mijocner',
                'slug_ru' => 'sredstva-dlya-kovrov',
                'slug_en' => 'carpet-cleaners',
                'icon' => 'layers',
                'sort_order' => 2,
                'name' => [
                    'hy' => 'Գորգերի քիմմաքրման միջոցներ',
                    'ru' => 'Средства для ковров',
                    'en' => 'Carpet Cleaning Chemicals',
                ],
                'short_description' => [
                    'hy' => 'Շամպուններ, պրեսփրեյեր և հատուկ բանաձևեր գորգերի ու հատակի ծածկույթների մաքրման համար՝ ինչպես ձեռքով, այնպես էլ մեքենայական (էքստրակտորով) եղանակով։',
                    'ru' => 'Шампуни, преспреи и специализированные составы для чистки ковров и напольных покрытий — как вручную, так и с помощью экстрактора.',
                    'en' => 'Shampoos, presprays and specialised formulas for cleaning carpets and rugs, suitable for both hand application and machine (extractor) cleaning.',
                ],
            ],
            [
                'slug_hy' => 'nerqnakneri-qimmaqrman-mijocner',
                'slug_ru' => 'sredstva-dlya-matrasov',
                'slug_en' => 'mattress-cleaners',
                'icon' => 'bed',
                'sort_order' => 3,
                'name' => [
                    'hy' => 'Ներքնակների քիմմաքրման միջոցներ',
                    'ru' => 'Средства для матрасов',
                    'en' => 'Mattress Cleaning Chemicals',
                ],
                'short_description' => [
                    'hy' => 'Մեղմ, բայց արդյունավետ միջոցներ ներքնակների մակերեսային մաքրման, բծերի հեռացման և հոտազերծման համար՝ հաշվի առնելով, որ ներքնակը շփվում է մաշկի հետ ամեն գիշեր։',
                    'ru' => 'Мягкие, но эффективные составы для поверхностной чистки матрасов, удаления пятен и устранения запахов — с учётом того, что матрас каждую ночь контактирует с кожей.',
                    'en' => 'Gentle yet effective formulas for surface-cleaning mattresses, removing stains and neutralising odours — mindful that a mattress is in nightly contact with skin.',
                ],
            ],
            [
                'slug_hy' => 'avtosaloni-qimmaqrman-mijocner',
                'slug_ru' => 'sredstva-dlya-avtosalona',
                'slug_en' => 'car-interior-cleaners',
                'icon' => 'car',
                'sort_order' => 4,
                'name' => [
                    'hy' => 'Ավտոսալոնի քիմմաքրման միջոցներ',
                    'ru' => 'Средства для химчистки автомобильного салона',
                    'en' => 'Car Interior Cleaning Chemicals',
                ],
                'short_description' => [
                    'hy' => 'Միջոցներ ավտոմեքենայի նստատեղերի, տակդիրների, առաստաղի և պլաստիկ մասերի մաքրման համար՝ հարմարեցված փակ տարածքի յուրահատուկ պայմաններին։',
                    'ru' => 'Составы для чистки сидений, ковриков, потолка и пластиковых элементов салона автомобиля, адаптированные под условия замкнутого пространства.',
                    'en' => 'Formulas for cleaning car seats, floor mats, headliners and plastic trim, adapted to the conditions of an enclosed cabin.',
                ],
            ],
            [
                'slug_hy' => 'bcaturner',
                'slug_ru' => 'pyatnovyvoditeli',
                'slug_en' => 'stain-removers',
                'icon' => 'droplet',
                'sort_order' => 5,
                'name' => [
                    'hy' => 'Բծաթուրեր',
                    'ru' => 'Пятновыводители',
                    'en' => 'Stain Removers',
                ],
                'short_description' => [
                    'hy' => 'Նպատակային միջոցներ կոնկրետ տեսակի բծերի համար՝ արյուն, սուրճ, գինի, յուղ, ժանգ, սոսինձ և այլն։ Ընտրվում են՝ ելնելով բծի տեսակից և մակերեսից։',
                    'ru' => 'Целевые средства против конкретных типов пятен: кровь, кофе, вино, жир, ржавчина, клей и другие. Подбираются в зависимости от типа пятна и поверхности.',
                    'en' => 'Targeted solutions for specific stain types — blood, coffee, wine, grease, rust, adhesive and more — chosen according to the stain and the surface involved.',
                ],
            ],
            [
                'slug_hy' => 'hoti-dem-mijocner',
                'slug_ru' => 'sredstva-protiv-zapahov',
                'slug_en' => 'odor-eliminators',
                'icon' => 'wind',
                'sort_order' => 6,
                'name' => [
                    'hy' => 'Հոտի դեմ միջոցներ',
                    'ru' => 'Средства против запахов',
                    'en' => 'Odor Eliminators',
                ],
                'short_description' => [
                    'hy' => 'Էնզիմային և չեզոքացնող բանաձևեր, որոնք քայքայում են հոտի աղբյուրը՝ մեզի, կենդանիների, ծխի կամ խոնավության հետևանքով առաջացած հոտերի դեպքում, այլ ոչ թե պարզապես քողարկում այն։',
                    'ru' => 'Энзимные и нейтрализующие составы, которые разрушают источник запаха — от мочи, животных, табака или сырости — а не просто маскируют его.',
                    'en' => 'Enzymatic and neutralising formulas that break down the source of an odour — from urine, pets, smoke or damp — rather than simply masking it.',
                ],
            ],
            [
                'slug_hy' => 'tturayin-lucuytner',
                'slug_ru' => 'kislotnye-opolaskivateli',
                'slug_en' => 'acid-rinses',
                'icon' => 'flask-conical',
                'sort_order' => 7,
                'name' => [
                    'hy' => 'Թթվային ողողանյութեր',
                    'ru' => 'Кислотные ополаскиватели',
                    'en' => 'Acid Rinses',
                ],
                'short_description' => [
                    'hy' => 'Օգտագործվում են գորգերի և տեքստիլի քիմմաքրման վերջին փուլում՝ հիմնային մնացորդները չեզոքացնելու, թել-թելիկը փափկեցնելու և գույնը վերականգնելու համար։',
                    'ru' => 'Применяются на финальном этапе химчистки ковров и текстиля — нейтрализуют щелочные остатки, смягчают ворс и восстанавливают цвет.',
                    'en' => 'Used at the final stage of carpet and textile cleaning to neutralise alkaline residue, soften the pile and help restore colour.',
                ],
            ],
            [
                'slug_hy' => 'himnakan-lvacox-mijocner',
                'slug_ru' => 'presprei-i-osnovnye-moyushchie-sredstva',
                'slug_en' => 'presprays-detergents',
                'icon' => 'spray-can',
                'sort_order' => 8,
                'name' => [
                    'hy' => 'Պրեսփրեյեր և հիմնական լվացող միջոցներ',
                    'ru' => 'Преспреи и основные моющие средства',
                    'en' => 'Presprays & Core Detergents',
                ],
                'short_description' => [
                    'hy' => 'Առաջին փուլում կիրառվող միջոցներ, որոնք թուլացնում են կեղտն ու ճարպը մինչև հիմնական մաքրումը՝ մեծացնելով հետագա փուլերի արդյունավետությունը։',
                    'ru' => 'Составы для первого этапа обработки, которые размягчают грязь и жир перед основной чисткой, повышая эффективность последующих шагов.',
                    'en' => 'First-step formulas that loosen dirt and grease ahead of the main cleaning pass, making the following steps more effective.',
                ],
            ],
            [
                'slug_hy' => 'popi-dem-mijocner',
                'slug_ru' => 'penogasiteli',
                'slug_en' => 'defoamers',
                'icon' => 'circle-off',
                'sort_order' => 9,
                'name' => [
                    'hy' => 'Պենոգասիչներ (փրփուրի դեմ միջոցներ)',
                    'ru' => 'Пеногасители',
                    'en' => 'Defoamers',
                ],
                'short_description' => [
                    'hy' => 'Ավելացվում են էքստրակտորի կեղտաջրերի բաքին՝ ավելորդ փրփուրը վերացնելու համար, ինչը թույլ է տալիս սարքին աշխատել կայուն և արդյունավետ։',
                    'ru' => 'Добавляются в бак экстрактора для сбора грязной воды, устраняя излишнюю пену и позволяя оборудованию работать стабильно и эффективно.',
                    'en' => 'Added to an extractor\'s recovery tank to knock down excess foam, keeping the machine running smoothly and at full suction power.',
                ],
            ],
            [
                'slug_hy' => 'xohanoci-mijocner',
                'slug_ru' => 'sredstva-dlya-kuhni',
                'slug_en' => 'kitchen-cleaners',
                'icon' => 'utensils',
                'sort_order' => 10,
                'name' => [
                    'hy' => 'Խոհանոցի մաքրման միջոցներ',
                    'ru' => 'Средства для кухни',
                    'en' => 'Kitchen Cleaning Chemicals',
                ],
                'short_description' => [
                    'hy' => 'Ճարպահանիչներ և մակերեսների մաքրիչներ՝ սեղանների, սալօջախների, պահարանների և վառարանների ամենօրյա ու խորքային մաքրման համար։',
                    'ru' => 'Обезжириватели и очистители поверхностей для ежедневной и глубокой чистки столешниц, плит, шкафов и духовых шкафов.',
                    'en' => 'Degreasers and surface cleaners for everyday and deep cleaning of countertops, stovetops, cabinets and ovens.',
                ],
            ],
            [
                'slug_hy' => 'sanhanguyci-mijocner',
                'slug_ru' => 'sredstva-dlya-vannoy-i-santehniki',
                'slug_en' => 'bathroom-plumbing-cleaners',
                'icon' => 'shower-head',
                'sort_order' => 11,
                'name' => [
                    'hy' => 'Լոգարանի և սանտեխնիկայի մաքրման միջոցներ',
                    'ru' => 'Средства для ванной и сантехники',
                    'en' => 'Bathroom & Plumbing Cleaners',
                ],
                'short_description' => [
                    'hy' => 'Կրային նստվածքի, օճառաքարի և բորբոսի դեմ միջոցներ զուգարանակոնքի, խառնուրդների, կաբինաների և սալիկների կարերի համար։',
                    'ru' => 'Средства против известкового налёта, мыльного камня и плесени для унитазов, смесителей, кабин и швов плитки.',
                    'en' => 'Solutions against limescale, soap scum and mould for toilets, faucets, shower enclosures and tile grout.',
                ],
            ],
            [
                'slug_hy' => 'apaki-ev-patuhani-mijocner',
                'slug_ru' => 'sredstva-dlya-okon-i-stekla',
                'slug_en' => 'glass-window-cleaners',
                'icon' => 'square',
                'sort_order' => 12,
                'name' => [
                    'hy' => 'Ապակու և պատուհանի մաքրման միջոցներ',
                    'ru' => 'Средства для окон и стекла',
                    'en' => 'Glass & Window Cleaners',
                ],
                'short_description' => [
                    'hy' => 'Առանց հետքերի արագ չորացող միջոցներ ապակու, հայելիների և վիտրաժների համար՝ բնակարանների, գրասենյակների և ցուցափեղկերի մասշտաբով։',
                    'ru' => 'Быстросохнущие средства без разводов для стёкол, зеркал и витражей — от квартир и офисов до витрин магазинов.',
                    'en' => 'Fast-drying, streak-free formulas for glass, mirrors and glazed surfaces — from apartments and offices to shopfronts.',
                ],
            ],
            [
                'slug_hy' => 'hataki-mijocner',
                'slug_ru' => 'sredstva-dlya-pola',
                'slug_en' => 'floor-cleaners',
                'icon' => 'square-stack',
                'sort_order' => 13,
                'name' => [
                    'hy' => 'Հատակի մաքրման միջոցներ',
                    'ru' => 'Средства для пола',
                    'en' => 'Floor Cleaning Chemicals',
                ],
                'short_description' => [
                    'hy' => 'Ունիվերսալ և մասնագիտացված միջոցներ տարբեր տեսակի հատակների ամենօրյա և խորքային մաքրման համար։',
                    'ru' => 'Универсальные и специализированные средства для повседневной и глубокой чистки различных типов полов.',
                    'en' => 'Universal and specialised chemicals for everyday and deep cleaning of a range of floor types.',
                ],
            ],
            [
                'slug_hy' => 'parketi-ev-laminati-mijocner',
                'slug_ru' => 'sredstva-dlya-parketa-i-laminata',
                'slug_en' => 'parquet-laminate-cleaners',
                'icon' => 'grid-3x3',
                'sort_order' => 14,
                'name' => [
                    'hy' => 'Պարկետի և լամինատի մաքրման միջոցներ',
                    'ru' => 'Средства для паркета и ламината',
                    'en' => 'Parquet & Laminate Cleaners',
                ],
                'short_description' => [
                    'hy' => 'pH-չեզոք բանաձևեր, որոնք մաքրում են առանց ջրի ավելցուկ կիրառման՝ պահպանելով փայտի և պաշտպանիչ ծածկույթի որակը։',
                    'ru' => 'pH-нейтральные составы, которые очищают без избыточного увлажнения, сохраняя качество древесины и защитного покрытия.',
                    'en' => 'pH-neutral formulas that clean without over-wetting the surface, protecting the wood and its finish.',
                ],
            ],
            [
                'slug_hy' => 'qari-ev-saliki-mijocner',
                'slug_ru' => 'sredstva-dlya-kamnya-i-plitki',
                'slug_en' => 'stone-tile-cleaners',
                'icon' => 'gem',
                'sort_order' => 15,
                'name' => [
                    'hy' => 'Քարի և սալիկի մաքրման միջոցներ',
                    'ru' => 'Средства для камня и плитки',
                    'en' => 'Stone & Tile Cleaners',
                ],
                'short_description' => [
                    'hy' => 'Միջոցներ բնական քարի, մարմարի, կերամոգրանիտի և սալիկների խնամքի համար՝ հաշվի առնելով, որ որոշ մակերեսներ զգայուն են թթուների նկատմամբ։',
                    'ru' => 'Составы для ухода за натуральным камнем, мрамором, керамогранитом и плиткой — с учётом того, что часть таких поверхностей чувствительна к кислотам.',
                    'en' => 'Care products for natural stone, marble, porcelain stoneware and tile — mindful that some of these surfaces are acid-sensitive.',
                ],
            ],
            [
                'slug_hy' => 'veranorogumic-heto-maqrman-mijocner',
                'slug_ru' => 'sredstva-dlya-uborki-posle-remonta',
                'slug_en' => 'post-renovation-cleaners',
                'icon' => 'hard-hat',
                'sort_order' => 16,
                'name' => [
                    'hy' => 'Վերանորոգումից հետո մաքրման միջոցներ',
                    'ru' => 'Средства для уборки после ремонта',
                    'en' => 'Post-Renovation Cleaning Chemicals',
                ],
                'short_description' => [
                    'hy' => 'Հզոր միջոցներ շինարարական փոշու, ցեմենտի և ներկի հետքերի հեռացման համար՝ պատուհաններից, հատակից և մակերեսներից։',
                    'ru' => 'Мощные составы для удаления строительной пыли, следов цемента и краски с окон, полов и поверхностей.',
                    'en' => 'Heavy-duty formulas for removing construction dust, cement residue and paint marks from windows, floors and surfaces.',
                ],
            ],
            [
                'slug_hy' => 'axtahanman-mijocner',
                'slug_ru' => 'dezinficiruyushchie-sredstva',
                'slug_en' => 'disinfectants',
                'icon' => 'shield-check',
                'sort_order' => 17,
                'name' => [
                    'hy' => 'Ախտահանիչ միջոցներ',
                    'ru' => 'Дезинфицирующие средства',
                    'en' => 'Disinfectants',
                ],
                'short_description' => [
                    'hy' => 'Հակամանրէային միջոցներ մակերեսների, տեքստիլի և օդի ախտահանման համար՝ բնակարանների, գրասենյակների և հասարակական տարածքների համար։',
                    'ru' => 'Антибактериальные средства для дезинфекции поверхностей, текстиля и воздуха в квартирах, офисах и общественных помещениях.',
                    'en' => 'Antibacterial products for disinfecting surfaces, fabrics and air in homes, offices and shared spaces.',
                ],
            ],
            [
                'slug_hy' => 'paraganer-ev-caxsayin-niuter',
                'slug_ru' => 'aksessuary-i-rashodnye-materialy',
                'slug_en' => 'accessories-consumables',
                'icon' => 'wrench',
                'sort_order' => 18,
                'name' => [
                    'hy' => 'Պարագաներ և ծախսային նյութեր',
                    'ru' => 'Аксессуары и расходные материалы',
                    'en' => 'Accessories & Consumables',
                ],
                'short_description' => [
                    'hy' => 'Խոզանակներ, միկրոմանրաթելե սրբիչներ, չափիչ տարաներ և այլ պարագաներ, որոնք լրացնում են քիմմաքրման միջոցների կիրառումը։',
                    'ru' => 'Щётки, микрофибровые салфетки, дозирующие ёмкости и другие принадлежности, дополняющие применение чистящих средств.',
                    'en' => 'Brushes, microfibre cloths, dosing containers and other accessories that support the use of the cleaning chemicals above.',
                ],
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->updateOrCreate(['slug_en' => $category['slug_en']], $category);
        }
    }
}

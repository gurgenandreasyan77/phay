<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Legacy MVP demo rows (originally seeded with is_active=true, a flat
     * `category` enum, and a single image path that never actually existed
     * under public/images/products). Per the market-expansion decision,
     * every one of these is migrated to `draft`: none of them has the
     * brand/pH/safety/real-photo data the spec requires before a product
     * can go live, so they stay as reusable content templates rather than
     * live catalog entries.
     */
    private const LEGACY_ITEMS = [
        // Floors
        [
            'slug' => 'floor-cleaner-concentrate',
            'category_slug' => 'floor-cleaners',
            'name' => [
                'hy' => 'Հատակի մաքրման միջոց (խտանյութ)',
                'ru' => 'Концентрат для мытья полов',
                'en' => 'Floor cleaner concentrate',
            ],
            'description' => [
                'hy' => 'Խտացված միջոց բոլոր տեսակի հատակների համար՝ սալիկ, լամինատ, պարկետ։ Հեռացնում է կպած կեղտն ու ճարպը՝ առանց հետք թողնելու։',
                'ru' => 'Концентрированное средство для всех типов полов: плитка, ламинат, паркет. Удаляет въевшуюся грязь и жир без разводов.',
                'en' => 'Concentrated cleaner for all floor types — tile, laminate, parquet. Removes ground-in dirt and grease without leaving streaks.',
            ],
            'alt' => [
                'hy' => 'Հատակի մաքրման միջոցի շիշ',
                'ru' => 'Бутылка средства для мытья полов',
                'en' => 'Bottle of floor cleaner',
            ],
            'image' => '/images/products/floor-cleaner-concentrate.webp',
            'price_amount' => 4500,
            'sort_order' => 1,
        ],
        [
            'slug' => 'laminate-parquet-cleaner',
            'category_slug' => 'parquet-laminate-cleaners',
            'name' => [
                'hy' => 'Լամինատի և պարկետի մաքրման միջոց',
                'ru' => 'Средство для ламината и паркета',
                'en' => 'Laminate & parquet cleaner',
            ],
            'description' => [
                'hy' => 'Մեղմորեն մաքրում է լամինացված և պարկետե հատակները՝ առանց հետքեր թողնելու և պաշտպանիչ ծածկույթը վնասելու։',
                'ru' => 'Бережно очищает ламинированные и паркетные полы, не оставляя разводов и не повреждая защитное покрытие.',
                'en' => 'Gently cleans laminate and parquet floors without leaving streaks or damaging the protective coating.',
            ],
            'alt' => [
                'hy' => 'Լամինատի մաքրման միջոցի շիշ',
                'ru' => 'Бутылка средства для ламината',
                'en' => 'Bottle of laminate cleaner',
            ],
            'image' => '/images/products/laminate-parquet-cleaner.webp',
            'price_amount' => 5200,
            'sort_order' => 2,
        ],
        [
            'slug' => 'tile-stone-cleaner',
            'category_slug' => 'stone-tile-cleaners',
            'name' => [
                'hy' => 'Սալիկի և քարի մաքրիչ',
                'ru' => 'Очиститель плитки и камня',
                'en' => 'Tile & stone cleaner',
            ],
            'description' => [
                'hy' => 'Հեռացնում է կրային նստվածքն ու կեղտը կերամիկական սալիկից, կերամոգրանիտից և բնական քարից։',
                'ru' => 'Удаляет известковый налёт и загрязнения с керамической плитки, керамогранита и натурального камня.',
                'en' => 'Removes limescale and grime from ceramic tile, porcelain stoneware, and natural stone.',
            ],
            'alt' => [
                'hy' => 'Սալիկի մաքրիչի շիշ',
                'ru' => 'Бутылка очистителя плитки',
                'en' => 'Bottle of tile cleaner',
            ],
            'image' => '/images/products/tile-stone-cleaner.webp',
            'price_amount' => 4800,
            'sort_order' => 3,
        ],
        [
            'slug' => 'floor-wax-polish',
            'category_slug' => 'floor-cleaners',
            'name' => [
                'hy' => 'Հատակի փայլեցնող մոմ',
                'ru' => 'Полироль-воск для пола',
                'en' => 'Floor wax polish',
            ],
            'description' => [
                'hy' => 'Հատակին տալիս է փայլ և ստեղծում պաշտպանիչ շերտ քերծվածքներից ու խոնավությունից։',
                'ru' => 'Придаёт полу глянцевый блеск и создаёт защитный слой от царапин и влаги.',
                'en' => 'Gives floors a glossy shine and creates a protective layer against scratches and moisture.',
            ],
            'alt' => [
                'hy' => 'Հատակի մոմի տուփ',
                'ru' => 'Банка воска для пола',
                'en' => 'Tin of floor wax',
            ],
            'image' => '/images/products/floor-wax-polish.webp',
            'price_amount' => 6300,
            'sort_order' => 4,
        ],
        [
            'slug' => 'marble-floor-cleaner',
            'category_slug' => 'stone-tile-cleaners',
            'name' => [
                'hy' => 'Մարմարե հատակի միջոց',
                'ru' => 'Средство для мраморных полов',
                'en' => 'Marble floor cleaner',
            ],
            'description' => [
                'hy' => 'Հատուկ բանաձև մարմարի և փայլեցված քարի համար՝ չի քայքայում մակերեսը և վերականգնում է փայլը։',
                'ru' => 'Специальная формула для мрамора и полированного камня — не разъедает поверхность и восстанавливает блеск.',
                'en' => "A special formula for marble and polished stone that won't etch the surface and restores shine.",
            ],
            'alt' => [
                'hy' => 'Մարմարե հատակի միջոցի շիշ',
                'ru' => 'Бутылка средства для мрамора',
                'en' => 'Bottle of marble floor cleaner',
            ],
            'image' => '/images/products/marble-floor-cleaner.webp',
            'price_amount' => 7500,
            'sort_order' => 5,
        ],
        [
            'slug' => 'grout-cleaner',
            'category_slug' => 'stone-tile-cleaners',
            'name' => [
                'hy' => 'Սալիկների կարերի մաքրիչ',
                'ru' => 'Очиститель швов плитки',
                'en' => 'Grout cleaner',
            ],
            'description' => [
                'hy' => 'Սպիտակեցնում և մաքրում է սալիկների միջև եղած կարերը բորբոսից ու կպած կեղտից։',
                'ru' => 'Отбеливает и очищает межплиточные швы от плесени и въевшейся грязи.',
                'en' => 'Whitens and cleans tile grout lines of mould and ground-in dirt.',
            ],
            'alt' => [
                'hy' => 'Կարերի մաքրիչի շիշ',
                'ru' => 'Бутылка очистителя швов',
                'en' => 'Bottle of grout cleaner',
            ],
            'image' => '/images/products/grout-cleaner.webp',
            'price_amount' => 4200,
            'sort_order' => 6,
        ],
        [
            'slug' => 'universal-floor-degreaser',
            'category_slug' => 'floor-cleaners',
            'name' => [
                'hy' => 'Հատակի ունիվերսալ ճարպահանիչ',
                'ru' => 'Универсальный обезжириватель для полов',
                'en' => 'Universal floor degreaser',
            ],
            'description' => [
                'hy' => 'Հզոր միջոց խոհանոցների, ավտոտնակների և պահեստների համար՝ լուծում է ճարպը, յուղի բծերը և տեխնիկական կեղտը։',
                'ru' => 'Мощное средство для кухонь, гаражей и складов — растворяет жир, масляные пятна и технические загрязнения.',
                'en' => 'A powerful solution for kitchens, garages and warehouses — dissolves grease, oil stains and technical residue.',
            ],
            'alt' => [
                'hy' => 'Ճարպահանիչի շիշ',
                'ru' => 'Бутылка обезжиривателя',
                'en' => 'Bottle of degreaser',
            ],
            'image' => '/images/products/universal-floor-degreaser.webp',
            'price_amount' => 5600,
            'sort_order' => 7,
        ],
        [
            'slug' => 'wood-floor-oil-soap',
            'category_slug' => 'parquet-laminate-cleaners',
            'name' => [
                'hy' => 'Փայտե հատակի յուղ-օճառ',
                'ru' => 'Мыло-масло для деревянных полов',
                'en' => 'Wood floor oil soap',
            ],
            'description' => [
                'hy' => 'Սնուցում և պաշտպանում է չներկված փայտը՝ պահպանելով բնական տեսքը և կանխելով չորացումը։',
                'ru' => 'Питает и защищает неокрашенную древесину, сохраняя натуральный вид и предотвращая пересыхание.',
                'en' => "Nourishes and protects unpainted wood, preserving its natural look and preventing it from drying out.",
            ],
            'alt' => [
                'hy' => 'Յուղ-օճառի շիշ',
                'ru' => 'Бутылка мыла-масла',
                'en' => 'Bottle of oil soap',
            ],
            'image' => '/images/products/wood-floor-oil-soap.webp',
            'price_amount' => 6800,
            'sort_order' => 8,
        ],

        // Upholstery
        [
            'slug' => 'upholstery-stain-remover',
            'category_slug' => 'stain-removers',
            'name' => [
                'hy' => 'Փափուկ կահույքի բծաթուր',
                'ru' => 'Пятновыводитель для мягкой мебели',
                'en' => 'Upholstery stain remover',
            ],
            'description' => [
                'hy' => 'Մասնագիտական սփրեյ բազմոցների, բազկաթոռների և գորգերի բծերի դեմ։ Անվտանգ է տեքստիլի մեծ մասի համար։',
                'ru' => 'Профессиональный спрей против пятен на диванах, креслах и коврах. Безопасен для большинства видов текстиля.',
                'en' => 'Professional spray against stains on sofas, armchairs, and rugs. Safe for most textile types.',
            ],
            'alt' => [
                'hy' => 'Կահույքի բծաթուրի սփրեյ',
                'ru' => 'Спрей-пятновыводитель для мебели',
                'en' => 'Upholstery stain remover spray',
            ],
            'image' => '/images/products/upholstery-stain-remover.webp',
            'price_amount' => 6200,
            'sort_order' => 9,
        ],
        [
            'slug' => 'carpet-shampoo',
            'category_slug' => 'carpet-cleaners',
            'name' => [
                'hy' => 'Գորգերի շամպուն',
                'ru' => 'Шампунь для ковров',
                'en' => 'Carpet shampoo',
            ],
            'description' => [
                'hy' => 'Խորապես մաքրում է թել-թելիկը, վերացնում բծերն ու տհաճ հոտերը։ Հարմար է ձեռքով և մեքենայով մաքրելու համար։',
                'ru' => 'Глубоко очищает ворс, устраняет пятна и неприятные запахи. Подходит для ручной и машинной чистки.',
                'en' => 'Deep-cleans pile, removes stains and unpleasant odours. Suitable for both hand and machine cleaning.',
            ],
            'alt' => [
                'hy' => 'Գորգերի շամպունի շիշ',
                'ru' => 'Бутылка шампуня для ковров',
                'en' => 'Bottle of carpet shampoo',
            ],
            'image' => '/images/products/carpet-shampoo.webp',
            'price_amount' => 5400,
            'sort_order' => 10,
        ],
        [
            'slug' => 'leather-upholstery-cleaner',
            'category_slug' => 'upholstery-cleaners',
            'name' => [
                'hy' => 'Կաշվե կահույքի մաքրիչ',
                'ru' => 'Очиститель кожаной мебели',
                'en' => 'Leather upholstery cleaner',
            ],
            'description' => [
                'hy' => 'Մեղմորեն մաքրում և խոնավացնում է կաշին՝ կանխելով ճաքերը և պահպանելով գույնը։',
                'ru' => 'Мягко очищает и увлажняет кожу, предотвращая растрескивание и сохраняя цвет.',
                'en' => 'Gently cleans and conditions leather, preventing cracking and preserving colour.',
            ],
            'alt' => [
                'hy' => 'Կաշվե մաքրիչի շիշ',
                'ru' => 'Бутылка очистителя кожи',
                'en' => 'Bottle of leather cleaner',
            ],
            'image' => '/images/products/leather-upholstery-cleaner.webp',
            'price_amount' => 7200,
            'sort_order' => 11,
        ],
        [
            'slug' => 'fabric-odor-eliminator',
            'category_slug' => 'odor-eliminators',
            'name' => [
                'hy' => 'Տեքստիլի հոտազերծիչ',
                'ru' => 'Нейтрализатор запаха для текстиля',
                'en' => 'Fabric odour eliminator',
            ],
            'description' => [
                'hy' => 'Վերացնում է ծխախոտի, կենդանիների և խոնավության հոտերը բազմոցներից, գորգերից ու վարագույրներից՝ ոչ թե քողարկելով, այլ ոչնչացնելով աղբյուրը։',
                'ru' => 'Устраняет запахи табака, животных и сырости с диванов, ковров и штор, не маскируя, а разрушая их источник.',
                'en' => 'Removes tobacco, pet, and damp odours from sofas, carpets and curtains by breaking down the source, not masking it.',
            ],
            'alt' => [
                'hy' => 'Հոտազերծիչի շիշ',
                'ru' => 'Бутылка нейтрализатора запаха',
                'en' => 'Bottle of odour eliminator',
            ],
            'image' => '/images/products/fabric-odor-eliminator.webp',
            'price_amount' => 4900,
            'sort_order' => 12,
        ],
        [
            'slug' => 'mattress-cleaner-foam',
            'category_slug' => 'mattress-cleaners',
            'name' => [
                'hy' => 'Ներքնակների մաքրման փրփուր',
                'ru' => 'Пена для чистки матрасов',
                'en' => 'Mattress cleaning foam',
            ],
            'description' => [
                'hy' => 'Ակտիվ փրփուրը խորապես մաքրում է ներքնակը փոշուց, բծերից ու փոշեկուլներից, արագ չորանում է։',
                'ru' => 'Активная пена глубоко очищает матрас от пыли, пятен и пылевых клещей, быстро высыхает.',
                'en' => 'An active foam that deep-cleans mattresses of dust, stains and dust mites, and dries quickly.',
            ],
            'alt' => [
                'hy' => 'Ներքնակի փրփուրի բալոն',
                'ru' => 'Баллон пены для матрасов',
                'en' => 'Can of mattress foam cleaner',
            ],
            'image' => '/images/products/mattress-cleaner-foam.webp',
            'price_amount' => 5800,
            'sort_order' => 13,
        ],
        [
            'slug' => 'curtain-cleaner-spray',
            'category_slug' => 'upholstery-cleaners',
            'name' => [
                'hy' => 'Վարագույրների մաքրման սփրեյ',
                'ru' => 'Спрей для чистки штор',
                'en' => 'Curtain cleaning spray',
            ],
            'description' => [
                'hy' => 'Թարմացնում և մաքրում է վարագույրները առանց լվանալու՝ հեռացնելով փոշին, հոտերն ու թեթև կեղտը տեղում։',
                'ru' => 'Освежает и очищает шторы без стирки — удаляет пыль, запахи и лёгкие загрязнения прямо на месте.',
                'en' => 'Refreshes and cleans curtains without washing — removes dust, odours and light soiling in place.',
            ],
            'alt' => [
                'hy' => 'Վարագույրների սփրեյի բալոն',
                'ru' => 'Баллон спрея для штор',
                'en' => 'Can of curtain spray',
            ],
            'image' => '/images/products/curtain-cleaner-spray.webp',
            'price_amount' => 4100,
            'sort_order' => 14,
        ],
        [
            'slug' => 'antistatic-fabric-spray',
            'category_slug' => 'upholstery-cleaners',
            'name' => [
                'hy' => 'Տեքստիլի հականստատիկ սփրեյ',
                'ru' => 'Антистатик для текстиля',
                'en' => 'Antistatic fabric spray',
            ],
            'description' => [
                'hy' => 'Հեռացնում է ստատիկ էլեկտրականությունը փափուկ կահույքից, գորգերից ու հագուստից՝ կանխելով փոշու կպչելը։',
                'ru' => 'Снимает статическое электричество с мягкой мебели, ковров и одежды, предотвращая притяжение пыли.',
                'en' => 'Removes static from upholstery, carpets and clothing, preventing dust from clinging.',
            ],
            'alt' => [
                'hy' => 'Հականստատիկ սփրեյի բալոն',
                'ru' => 'Баллон антистатика',
                'en' => 'Can of antistatic spray',
            ],
            'image' => '/images/products/antistatic-fabric-spray.webp',
            'price_amount' => 3400,
            'sort_order' => 15,
        ],
        [
            'slug' => 'wool-carpet-shampoo',
            'category_slug' => 'carpet-cleaners',
            'name' => [
                'hy' => 'Բրդյա գորգերի շամպուն',
                'ru' => 'Шампунь для шерстяных ковров',
                'en' => 'Wool carpet shampoo',
            ],
            'description' => [
                'hy' => 'Հատուկ, առանց ալկալիի բանաձևը մեղմորեն մաքրում է բնական բուրդը՝ պահպանելով թել-թելիկի փափկությունը։',
                'ru' => 'Особая формула без щёлочи бережно очищает натуральную шерсть, сохраняя мягкость ворса.',
                'en' => 'An alkali-free formula that gently cleans natural wool while keeping the pile soft.',
            ],
            'alt' => [
                'hy' => 'Բրդյա գորգի շամպունի շիշ',
                'ru' => 'Бутылка шампуня для шерсти',
                'en' => 'Bottle of wool carpet shampoo',
            ],
            'image' => '/images/products/wool-carpet-shampoo.webp',
            'price_amount' => 6100,
            'sort_order' => 16,
        ],

        // Windows
        [
            'slug' => 'glass-window-cleaner',
            'category_slug' => 'glass-window-cleaners',
            'name' => [
                'hy' => 'Ապակու և պատուհանի մաքրիչ',
                'ru' => 'Средство для мытья окон и стёкол',
                'en' => 'Glass and window cleaner',
            ],
            'description' => [
                'hy' => 'Առանց հետքերի մաքրում ապակու, հայելու և վիտրաժների համար։ Արագ չորացող բանաձև։',
                'ru' => 'Мытьё без разводов для стёкол, зеркал и витражей. Быстросохнущая формула.',
                'en' => 'Streak-free cleaning for glass, mirrors, and glazed surfaces. Fast-drying formula.',
            ],
            'alt' => [
                'hy' => 'Ապակու մաքրիչի շիշ',
                'ru' => 'Бутылка средства для мытья окон',
                'en' => 'Bottle of glass cleaner',
            ],
            'image' => '/images/products/glass-window-cleaner.webp',
            'price_amount' => 3200,
            'sort_order' => 17,
        ],
        [
            'slug' => 'mirror-cleaner-spray',
            'category_slug' => 'glass-window-cleaners',
            'name' => [
                'hy' => 'Հայելիների սփրեյ',
                'ru' => 'Спрей для зеркал',
                'en' => 'Mirror cleaner spray',
            ],
            'description' => [
                'hy' => 'Արագ մաքրում է հայելային մակերեսները՝ առանց հետքերի և մշուշոտման։',
                'ru' => 'Быстро очищает зеркальные поверхности без разводов и запотевания.',
                'en' => 'Quickly cleans mirrored surfaces without streaks or fogging.',
            ],
            'alt' => [
                'hy' => 'Հայելու սփրեյի բալոն',
                'ru' => 'Баллон спрея для зеркал',
                'en' => 'Can of mirror spray',
            ],
            'image' => '/images/products/mirror-cleaner-spray.webp',
            'price_amount' => 2800,
            'sort_order' => 18,
        ],
        [
            'slug' => 'streak-free-glass-polish',
            'category_slug' => 'glass-window-cleaners',
            'name' => [
                'hy' => 'Ապակու փայլեցնող միջոց առանց հետքերի',
                'ru' => 'Полироль для стекла без разводов',
                'en' => 'Streak-free glass polish',
            ],
            'description' => [
                'hy' => 'Մասնագիտական բանաձև ցուցափեղկերի և մեծ պատուհանների համար՝ արագ չորանում է և հետք չի թողնում։',
                'ru' => 'Профессиональная формула для витрин и больших окон — сохнет быстро и не оставляет следов.',
                'en' => 'A professional-grade formula for shopfronts and large windows — dries fast and leaves no marks.',
            ],
            'alt' => [
                'hy' => 'Ապակու փայլեցնող միջոցի շիշ',
                'ru' => 'Бутылка полироли для стекла',
                'en' => 'Bottle of glass polish',
            ],
            'image' => '/images/products/streak-free-glass-polish.webp',
            'price_amount' => 5100,
            'sort_order' => 19,
        ],
        [
            'slug' => 'window-frame-cleaner',
            'category_slug' => 'glass-window-cleaners',
            'name' => [
                'hy' => 'Պատուհանի շրջանակների մաքրիչ',
                'ru' => 'Очиститель оконных рам',
                'en' => 'Window frame cleaner',
            ],
            'description' => [
                'hy' => 'Հեռացնում է դեղնությունն ու կեղտը պլաստիկ շրջանակներից ու պատուհանագոգերից՝ վերականգնելով սպիտակությունը։',
                'ru' => 'Удаляет пожелтение и загрязнения с пластиковых рам и подоконников, восстанавливая белизну.',
                'en' => 'Removes yellowing and grime from plastic frames and sills, restoring their whiteness.',
            ],
            'alt' => [
                'hy' => 'Շրջանակների մաքրիչի շիշ',
                'ru' => 'Бутылка очистителя рам',
                'en' => 'Bottle of frame cleaner',
            ],
            'image' => '/images/products/window-frame-cleaner.webp',
            'price_amount' => 3900,
            'sort_order' => 20,
        ],
        [
            'slug' => 'anti-fog-glass-spray',
            'category_slug' => 'glass-window-cleaners',
            'name' => [
                'hy' => 'Ապակու հականամուշտ սփրեյ',
                'ru' => 'Антизапотеватель для стекла',
                'en' => 'Anti-fog glass spray',
            ],
            'description' => [
                'hy' => 'Պաշտպանում է հայելիներն ու պատուհանները մշուշոտումից լոգարանում և խոհանոցում մինչև 48 ժամ։',
                'ru' => 'Защищает зеркала и окна от запотевания в ванной и на кухне до 48 часов.',
                'en' => 'Protects mirrors and windows from fogging in bathrooms and kitchens for up to 48 hours.',
            ],
            'alt' => [
                'hy' => 'Հականամուշտ սփրեյի բալոն',
                'ru' => 'Баллон антизапотевателя',
                'en' => 'Can of anti-fog spray',
            ],
            'image' => '/images/products/anti-fog-glass-spray.webp',
            'price_amount' => 4300,
            'sort_order' => 21,
        ],
        [
            'slug' => 'car-glass-cleaner',
            'category_slug' => 'car-interior-cleaners',
            'name' => [
                'hy' => 'Ավտոապակիների մաքրիչ',
                'ru' => 'Очиститель автостёкол',
                'en' => 'Car glass cleaner',
            ],
            'description' => [
                'hy' => 'Հեռացնում է ճանապարհային նստվածքը, միջատներն ու հետքերը ավտոմեքենայի առջևի և կողքի ապակիներից։',
                'ru' => 'Удаляет дорожный налёт, насекомых и разводы с лобового и боковых стёкол автомобиля.',
                'en' => "Removes road film, insects, and streaks from a car's windshield and side windows.",
            ],
            'alt' => [
                'hy' => 'Ավտոապակու մաքրիչի շիշ',
                'ru' => 'Бутылка очистителя автостёкол',
                'en' => 'Bottle of car glass cleaner',
            ],
            'image' => '/images/products/car-glass-cleaner.webp',
            'price_amount' => 3600,
            'sort_order' => 22,
        ],
        [
            'slug' => 'facade-glass-cleaner-concentrate',
            'category_slug' => 'glass-window-cleaners',
            'name' => [
                'hy' => 'Ֆասադային ապակեպատման մաքրման խտանյութ',
                'ru' => 'Концентрат для мытья фасадного остекления',
                'en' => 'Facade glazing cleaner concentrate',
            ],
            'description' => [
                'hy' => 'Մասնագիտական միջոց մեծ վիտրաժների ու ճակատների համար՝ խնայող ծախս, առանց հետքերի։',
                'ru' => 'Профессиональное средство для больших витражей и фасадов — экономичный расход, без разводов.',
                'en' => 'A professional solution for large glazed facades — economical to use, streak-free results.',
            ],
            'alt' => [
                'hy' => 'Ֆասադային մաքրիչի շիշ',
                'ru' => 'Бутылка фасадного концентрата',
                'en' => 'Bottle of facade cleaner concentrate',
            ],
            'image' => '/images/products/facade-glass-cleaner-concentrate.webp',
            'price_amount' => 8200,
            'sort_order' => 23,
        ],

        // Disinfection
        [
            'slug' => 'surface-disinfectant',
            'category_slug' => 'disinfectants',
            'name' => [
                'hy' => 'Մակերեսների ախտահանիչ',
                'ru' => 'Дезинфицирующее средство для поверхностей',
                'en' => 'Surface disinfectant',
            ],
            'description' => [
                'hy' => 'Հակամանրէային միջոց՝ բակտերիաների և վիրուսների ոչնչացման համար խոհանոցում, լոգարանում և գրասենյակում։',
                'ru' => 'Антибактериальное средство для уничтожения бактерий и вирусов на кухне, в ванной и офисе.',
                'en' => 'Antibacterial solution for eliminating bacteria and viruses in kitchens, bathrooms, and offices.',
            ],
            'alt' => [
                'hy' => 'Ախտահանիչ միջոցի շիշ',
                'ru' => 'Бутылка дезинфицирующего средства',
                'en' => 'Bottle of disinfectant',
            ],
            'image' => '/images/products/surface-disinfectant.webp',
            'price_amount' => 3800,
            'sort_order' => 24,
        ],
        [
            'slug' => 'hand-sanitizer-gel',
            'category_slug' => 'disinfectants',
            'name' => [
                'hy' => 'Ձեռքերի հականեխիչ',
                'ru' => 'Антисептик для рук',
                'en' => 'Hand sanitizer gel',
            ],
            'description' => [
                'hy' => 'Սպիրտային ժելը ոչնչացնում է մանրէների և վիրուսների 99,9%-ը, գլիցերինի շնորհիվ չի չորացնում մաշկը։',
                'ru' => 'Спиртовой гель уничтожает 99,9% бактерий и вирусов, не сушит кожу благодаря глицерину.',
                'en' => "An alcohol-based gel that kills 99.9% of bacteria and viruses, with glycerin so it won't dry out skin.",
            ],
            'alt' => [
                'hy' => 'Հականեխիչ ժելի շիշ',
                'ru' => 'Бутылка антисептика для рук',
                'en' => 'Bottle of hand sanitizer',
            ],
            'image' => '/images/products/hand-sanitizer-gel.webp',
            'price_amount' => 2500,
            'sort_order' => 25,
        ],
        [
            'slug' => 'air-disinfectant-spray',
            'category_slug' => 'disinfectants',
            'name' => [
                'hy' => 'Օդի ախտահանման սփրեյ',
                'ru' => 'Спрей для дезинфекции воздуха',
                'en' => 'Air disinfectant spray',
            ],
            'description' => [
                'hy' => 'Ախտահանում է սենյակի օդն ու տեքստիլը, չեզոքացնում մանրէները հանրային օգտագործման վայրերում։',
                'ru' => 'Обеззараживает воздух и текстиль в помещении, нейтрализует бактерии в местах общего пользования.',
                'en' => 'Sanitises indoor air and fabrics, neutralising bacteria in shared spaces.',
            ],
            'alt' => [
                'hy' => 'Օդի ախտահանիչ բալոն',
                'ru' => 'Баллон спрея для воздуха',
                'en' => 'Can of air disinfectant',
            ],
            'image' => '/images/products/air-disinfectant-spray.webp',
            'price_amount' => 3300,
            'sort_order' => 26,
        ],
        [
            'slug' => 'bathroom-mold-remover',
            'category_slug' => 'bathroom-plumbing-cleaners',
            'name' => [
                'hy' => 'Լոգարանի բորբոսի դեմ միջոց',
                'ru' => 'Средство против плесени в ванной',
                'en' => 'Bathroom mould remover',
            ],
            'description' => [
                'hy' => 'Ոչնչացնում է բորբոսն ու սնկերը սալիկների կարերին, սիլիկոնին և պատերին՝ կանխելով կրկնակի հայտնվելը։',
                'ru' => 'Уничтожает плесень и грибок на швах плитки, силиконе и стенах, предотвращая повторное появление.',
                'en' => "Kills mould and fungus on tile grout, silicone seals and walls, and helps prevent it from coming back.",
            ],
            'alt' => [
                'hy' => 'Բորբոսի դեմ միջոցի շիշ',
                'ru' => 'Бутылка средства против плесени',
                'en' => 'Bottle of mould remover',
            ],
            'image' => '/images/products/bathroom-mold-remover.webp',
            'price_amount' => 4700,
            'sort_order' => 27,
        ],
        [
            'slug' => 'toilet-disinfectant-gel',
            'category_slug' => 'bathroom-plumbing-cleaners',
            'name' => [
                'hy' => 'Զուգարանակոնքի ախտահանիչ ժել',
                'ru' => 'Дезинфицирующий гель для унитаза',
                'en' => 'Toilet disinfectant gel',
            ],
            'description' => [
                'hy' => 'Խիտ ժելը պատում է մակերեսը, ոչնչացնում մանրէները և հեռացնում կրային նստվածքը։',
                'ru' => 'Густой гель обволакивает поверхность, уничтожает бактерии и устраняет известковый налёт.',
                'en' => 'A thick gel that clings to the bowl, kills bacteria and removes limescale.',
            ],
            'alt' => [
                'hy' => 'Ախտահանիչ ժելի շիշ',
                'ru' => 'Бутылка дезинфицирующего геля',
                'en' => 'Bottle of toilet gel',
            ],
            'image' => '/images/products/toilet-disinfectant-gel.webp',
            'price_amount' => 2900,
            'sort_order' => 28,
        ],
        [
            'slug' => 'kitchen-disinfectant-degreaser',
            'category_slug' => 'kitchen-cleaners',
            'name' => [
                'hy' => 'Խոհանոցի ախտահանիչ ճարպահանիչ',
                'ru' => 'Дезинфицирующий обезжириватель для кухни',
                'en' => 'Kitchen disinfectant degreaser',
            ],
            'description' => [
                'hy' => 'Միաժամանակ լուծում է ճարպը և ոչնչացնում մանրէները սեղանների ու էլեկտրական սալօջախների վրա, անվտանգ է ողողումից հետո։',
                'ru' => 'Одновременно растворяет жир и уничтожает бактерии на столешницах и плитах, безопасен после ополаскивания.',
                'en' => 'Dissolves grease and kills bacteria on countertops and stovetops at the same time; safe once rinsed.',
            ],
            'alt' => [
                'hy' => 'Խոհանոցի ախտահանիչի շիշ',
                'ru' => 'Бутылка кухонного дезинфектора',
                'en' => 'Bottle of kitchen disinfectant',
            ],
            'image' => '/images/products/kitchen-disinfectant-degreaser.webp',
            'price_amount' => 3700,
            'sort_order' => 29,
        ],
        [
            'slug' => 'antibacterial-fabric-spray',
            'category_slug' => 'disinfectants',
            'name' => [
                'hy' => 'Հակամանրէային սփրեյ տեքստիլի համար',
                'ru' => 'Антибактериальный спрей для тканей',
                'en' => 'Antibacterial fabric spray',
            ],
            'description' => [
                'hy' => 'Ախտահանում է փափուկ կահույքը, ներքնակներն ու մանկական խաղալիքները՝ առանց հետքերի և քլորի հոտի։',
                'ru' => 'Дезинфицирует мягкую мебель, матрасы и детские игрушки без следов и запаха хлора.',
                'en' => 'Disinfects upholstery, mattresses and children\'s toys without residue or a chlorine smell.',
            ],
            'alt' => [
                'hy' => 'Հակամանրէային սփրեյի բալոն',
                'ru' => 'Баллон антибактериального спрея',
                'en' => 'Can of antibacterial spray',
            ],
            'image' => '/images/products/antibacterial-fabric-spray.webp',
            'price_amount' => 4400,
            'sort_order' => 30,
        ],
    ];

    /**
     * The 20 new draft SKU templates from the market plan (section 5): only
     * a name and a category. Brand, price, stock, pH, composition, dilution,
     * certificates, safety copy and photos are intentionally left blank —
     * inventing any of those would violate the "don't fabricate manufacturer
     * data" rule. `admin_note` flags exactly what's missing so the admin
     * list can surface "needs data" items; these must stay Draft until a
     * human fills them in with verified information.
     */
    private const DRAFT_TEMPLATES = [
        [
            'slug' => 'alkaline-prespray-upholstery-carpet',
            'category_slug' => 'presprays-detergents',
            'name' => [
                'hy' => 'Հիմնային պրեսփրեյ կահույքի և գորգերի քիմմաքրման համար',
                'ru' => 'Щелочной преспрей для химчистки мебели и ковров',
                'en' => 'Alkaline prespray for upholstery and carpet cleaning',
            ],
        ],
        [
            'slug' => 'neutral-cleaner-delicate-upholstery',
            'category_slug' => 'upholstery-cleaners',
            'name' => [
                'hy' => 'Չեզոք միջոց նուրբ երեսպատման համար',
                'ru' => 'Нейтральное средство для деликатной обивки',
                'en' => 'Neutral cleaner for delicate upholstery',
            ],
        ],
        [
            'slug' => 'acid-rinse-textile-carpet',
            'category_slug' => 'acid-rinses',
            'name' => [
                'hy' => 'Թթվային ողողանյութ տեքստիլի և գորգերի համար',
                'ru' => 'Кислотный ополаскиватель для текстиля и ковров',
                'en' => 'Acid rinse for textile and carpet',
            ],
        ],
        [
            'slug' => 'enzyme-urine-odor-remover',
            'category_slug' => 'odor-eliminators',
            'name' => [
                'hy' => 'Էնզիմային միջոց մեզի և օրգանական հոտերի դեմ',
                'ru' => 'Энзимное средство против мочи и органических запахов',
                'en' => 'Enzymatic remover for urine and organic odors',
            ],
        ],
        [
            'slug' => 'blood-protein-stain-remover',
            'category_slug' => 'stain-removers',
            'name' => [
                'hy' => 'Միջոց արյան և սպիտակուցային բծերի դեմ',
                'ru' => 'Средство против крови и белковых пятен',
                'en' => 'Blood and protein stain remover',
            ],
        ],
        [
            'slug' => 'ink-marker-remover',
            'category_slug' => 'stain-removers',
            'name' => [
                'hy' => 'Մարկերի և մելանի հետքերի հեռացման միջոց',
                'ru' => 'Средство для удаления чернил и маркера',
                'en' => 'Ink and marker remover',
            ],
        ],
        [
            'slug' => 'grease-oil-stain-remover',
            'category_slug' => 'stain-removers',
            'name' => [
                'hy' => 'Ճարպի և յուղի բծերի հեռացման միջոց',
                'ru' => 'Средство для удаления жира и масла',
                'en' => 'Grease and oil stain remover',
            ],
        ],
        [
            'slug' => 'coffee-wine-food-stain-remover',
            'category_slug' => 'stain-removers',
            'name' => [
                'hy' => 'Միջոց սուրճի, գինու և սննդային բծերի դեմ',
                'ru' => 'Средство против кофе, вина и пищевых пятен',
                'en' => 'Coffee, wine and food stain remover',
            ],
        ],
        [
            'slug' => 'rust-stain-remover',
            'category_slug' => 'stain-removers',
            'name' => [
                'hy' => 'Ժանգի հեռացման միջոց',
                'ru' => 'Средство для удаления ржавчины',
                'en' => 'Rust stain remover',
            ],
        ],
        [
            'slug' => 'glue-gum-remover',
            'category_slug' => 'stain-removers',
            'name' => [
                'hy' => 'Սոսինձի և ծամոնի հեռացման միջոց',
                'ru' => 'Средство для удаления клея и жевательной резинки',
                'en' => 'Glue and chewing gum remover',
            ],
        ],
        [
            'slug' => 'odor-neutralizer-general',
            'category_slug' => 'odor-eliminators',
            'name' => [
                'hy' => 'Տհաճ հոտերի նեյտրալիզատոր',
                'ru' => 'Нейтрализатор неприятных запахов',
                'en' => 'General odor neutralizer',
            ],
        ],
        [
            'slug' => 'extractor-defoamer',
            'category_slug' => 'defoamers',
            'name' => [
                'hy' => 'Պենոգասիչ էքստրակտորի համար',
                'ru' => 'Пеногаситель для экстрактора',
                'en' => 'Defoamer for extractors',
            ],
        ],
        [
            'slug' => 'car-interior-cleaner',
            'category_slug' => 'car-interior-cleaners',
            'name' => [
                'hy' => 'Ավտոսալոնի քիմմաքրման միջոց',
                'ru' => 'Средство для химчистки автомобильного салона',
                'en' => 'Car interior cleaning solution',
            ],
        ],
        [
            'slug' => 'leather-faux-leather-cleaner',
            'category_slug' => 'upholstery-cleaners',
            'name' => [
                'hy' => 'Միջոց կաշվի և էկոկաշվի համար',
                'ru' => 'Средство для кожи и экокожи',
                'en' => 'Leather and faux-leather cleaner',
            ],
        ],
        [
            'slug' => 'kitchen-degreaser-professional',
            'category_slug' => 'kitchen-cleaners',
            'name' => [
                'hy' => 'Մասնագիտական ճարպահանիչ խոհանոցի համար',
                'ru' => 'Профессиональный обезжириватель для кухни',
                'en' => 'Professional kitchen degreaser',
            ],
        ],
        [
            'slug' => 'limescale-remover-acidic',
            'category_slug' => 'bathroom-plumbing-cleaners',
            'name' => [
                'hy' => 'Թթվային միջոց կրային նստվածքի դեմ',
                'ru' => 'Кислотное средство против известкового налёта',
                'en' => 'Acidic limescale remover',
            ],
        ],
        [
            'slug' => 'glass-mirror-professional-cleaner',
            'category_slug' => 'glass-window-cleaners',
            'name' => [
                'hy' => 'Մասնագիտական միջոց ապակու և հայելիների համար',
                'ru' => 'Профессиональное средство для стекла и зеркал',
                'en' => 'Professional glass and mirror cleaner',
            ],
        ],
        [
            'slug' => 'neutral-daily-floor-cleaner',
            'category_slug' => 'floor-cleaners',
            'name' => [
                'hy' => 'Չեզոք միջոց հատակի ամենօրյա մաքրման համար',
                'ru' => 'Нейтральное средство для ежедневного мытья пола',
                'en' => 'Neutral daily floor cleaner',
            ],
        ],
        [
            'slug' => 'parquet-laminate-cleaner-new',
            'category_slug' => 'parquet-laminate-cleaners',
            'name' => [
                'hy' => 'Միջոց պարկետի և լամինատի համար',
                'ru' => 'Средство для паркета и ламината',
                'en' => 'Parquet and laminate cleaner',
            ],
        ],
        [
            'slug' => 'post-renovation-dust-cleaner',
            'category_slug' => 'post-renovation-cleaners',
            'name' => [
                'hy' => 'Միջոց շինարարական փոշու և վերանորոգումից հետո մաքրման համար',
                'ru' => 'Средство для уборки строительной пыли и следов ремонта',
                'en' => 'Construction dust and post-renovation cleaner',
            ],
        ],
    ];

    private const NEEDS_DATA_NOTE = 'Требуется заполнение: бренд, цена, наличие, объём, pH, тип состава, '
        .'пропорции разведения, инструкция по применению, меры безопасности, сертификаты, реальное фото. '
        .'Не публиковать без проверенных данных от производителя.';

    public function run(): void
    {
        foreach (self::LEGACY_ITEMS as $item) {
            $this->upsert($item, 'draft');
        }

        foreach (self::DRAFT_TEMPLATES as $item) {
            $this->upsert($item, 'draft', self::NEEDS_DATA_NOTE);
        }
    }

    private function upsert(array $item, string $status, ?string $adminNote = null): void
    {
        $categorySlug = $item['category_slug'];
        unset($item['category_slug']);

        $item['status'] = $status;
        $item['slug_hy'] = $item['slug'];
        $item['slug_ru'] = $item['slug'];
        $item['slug_en'] = $item['slug'];

        if ($adminNote !== null) {
            $item['admin_note'] = $adminNote;
        }

        $product = Product::query()->updateOrCreate(['slug' => $item['slug']], $item);

        $category = ProductCategory::query()->where('slug_en', $categorySlug)->first();

        if ($category) {
            $product->categories()->syncWithoutDetaching([$category->id => ['is_primary' => true]]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Article bodies use a literal "{locale}" token in internal links
 * (e.g. href="/{locale}/services/general-cleaning") instead of a hardcoded
 * locale prefix, because the same HTML is served for all three locales.
 * The frontend's ArticleBody component replaces the token with the actual
 * locale before rendering.
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        // Slugs were changed for SEO (Armenian-transliteration URLs). Renaming
        // in place via the query builder (not the Eloquent model) preserves
        // created_at/updated_at/published_at exactly, since the content
        // itself hasn't changed — only next.config.ts's redirects() need to
        // know about the old -> new mapping.
        $slugRenames = [
            'home-cleaning-allergy-season' => 'tan-maqrum-allergiayi-depqum-yerevan',
            'spring-deep-cleaning-checklist' => 'garnanayin-maqrum-yerevan',
            'remove-pet-hair-odor-from-sofa' => 'bazmoci-qimmaqrum-kendanu-mazi-ev-hoti-dem',
            'bathroom-deep-cleaning-guide' => 'sanhanguyci-xorqyayin-maqrum-yerevan',
            'kitchen-deep-cleaning-guide' => 'xohanoci-xorqyayin-maqrum-yerevan',
            'move-in-move-out-cleaning-yerevan' => 'bnakarani-maqrum-texapoxveluc-araj-ev-heto',
            'carpet-cleaning-at-home-yerevan' => 'gorgeri-qimmaqrum-gin-tany-yerevan',
            'airbnb-short-term-rental-cleaning-yerevan' => 'oravardzov-bnakaranneri-maqrum-yerevan',
            'office-cleaning-yerevan' => 'grasenyaki-maqrum-gin-yerevan',
            'mattress-cleaning-yerevan' => 'nerqnaki-qimmaqrum-gin-yerevan',
            'window-cleaning-yerevan-guide' => 'patuhanneri-lvacum-gin-yerevan',
            'apartment-cleaning-yerevan-guide' => 'bnakarani-maqrum-gin-inch-e-nerarvum-yerevan',
        ];

        foreach ($slugRenames as $old => $new) {
            Post::query()->where('slug', $old)->toBase()->update(['slug' => $new]);
        }

        // The rename above only touches the slug, but below the matching
        // related_post_slugs entries also get repointed at the new slugs —
        // that's a real attribute change, so Eloquent's dirty-check would
        // otherwise bump updated_at even though the article's own title,
        // body and FAQ never changed. Snapshot updated_at now (post-rename,
        // pre-upsert) and restore it after, via the query builder so the
        // restore itself doesn't touch timestamps again.
        $preservedUpdatedAt = Post::query()
            ->whereIn('slug', array_values($slugRenames))
            ->pluck('updated_at', 'slug');

        // Retired articles — content merged into the corresponding service
        // pages. Deleting the Post cascades to the post_service pivot; the
        // URLs permanently redirect to /services/* (see next.config.ts).
        Post::query()->whereIn('slug', [
            'sofa-dry-cleaning-how-it-works',
            'post-construction-cleaning-checklist',
            'what-is-included-in-general-cleaning',
            'general-vs-regular-cleaning',
        ])->delete();

        $posts = [
            [
                'slug' => 'bnakarani-maqrum-gin-inch-e-nerarvum-yerevan',
                'category' => 'guides',
                'tags' => ['բնակարանի մաքրում Երևանում', 'մաքրման ծառայություն Երևանում', 'уборка квартир в Ереване', 'apartment cleaning Yerevan'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-07-28'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/apartment-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Բնակարանի մասնագիտական մաքրում Երևանում՝ Servio-ի աշխատակցի կողմից',
                    'ru' => 'Профессиональная уборка квартиры в Ереване сотрудником Servio',
                    'en' => 'Professional apartment cleaning in Yerevan by a Servio team member',
                ],
                'title' => [
                    'hy' => 'Բնակարանի մաքրում Երևանում. ինչպես ընտրել ճիշտ ծառայությունը',
                    'ru' => 'Уборка квартир в Ереване: как выбрать услугу',
                    'en' => 'Apartment Cleaning in Yerevan: How to Choose the Right Service',
                ],
                'excerpt' => [
                    'hy' => 'Ինչպես ընտրել բնակարանի մաքրման տեսակը Երևանում, ինչ է ներառվում ծառայության մեջ և ինչից է կախված արժեքը։',
                    'ru' => 'Как выбрать вид уборки квартиры в Ереване, что входит в услугу и от чего зависит стоимость.',
                    'en' => 'How to choose the right apartment cleaning service in Yerevan, what\'s included, and what drives the price.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p><a href="/{locale}/services/apartment-cleaning">Բնակարանի մաքրումը</a> միայն փոշին սրբելը կամ հատակը լվանալը չէ։ Ճիշտ ընտրված ծառայությունը պետք է համապատասխանի տան վիճակին, մակերեսին և այն արդյունքին, որը ցանկանում եք ստանալ։ Երևանում սովորաբար ընտրում են <a href="/{locale}/services/regular-cleaning">կանոնավոր</a>, <a href="/{locale}/services/general-cleaning">ընդհանուր</a> կամ վերանորոգումից հետո մաքրում։</p>

<h2>Ո՞ր տեսակն ընտրել</h2>
<p>Կանոնավոր մաքրումը հարմար է շաբաթական կամ ամսական խնամքի համար։ Այն ներառում է տեսանելի մակերեսների փոշու մաքրում, հատակի չոր և խոնավ մաքրում, խոհանոցի ու սանհանգույցի ընթացիկ խնամք։ Ընդհանուր մաքրումն ավելի խորքային է․ մշակվում են դժվարամատչելի հատվածները, դռները, սալիկները, կահույքի արտաքին մակերեսները և կուտակված աղտոտումները։ Շինարարական փոշու, ներկի կամ սոսնձի հետքերի դեպքում անհրաժեշտ է առանձին՝ հետվերանորոգման ծառայություն։</p>

<h2>Ինչի՞ց է կախված արժեքը</h2>
<p>Գինը ճիշտ է գնահատել ոչ միայն քառակուսի մետրով։ Կարևոր են սենյակների ու սանհանգույցների քանակը, պատուհանները, կահույքի ծանրաբեռնվածությունը, աղտոտվածության աստիճանը և լրացուցիչ ծառայությունները։ Լուսանկարները կամ կարճ տեսանյութը օգնում են նախապես կազմել ավելի ճշգրիտ <a href="/{locale}/calculator">հաշվարկ</a>։</p>

<p>Servio-ն իր հետ բերում է անհրաժեշտ գույքն ու մասնագիտական միջոցները։ Պատվերից առաջ ճշտվում են աշխատանքի ծավալը, հատուկ մակերեսները և ցանկալի արդյունքը։ Այդպես հաճախորդը հասկանում է՝ ինչ է ներառված և որ ծառայությունների համար կարող է լինել լրացուցիչ հաշվարկ։</p>
HTML,
                    'ru' => <<<'HTML'
<p><a href="/{locale}/services/apartment-cleaning">Уборка квартиры</a> — это не только протереть пыль или помыть пол. Правильно подобранная услуга должна соответствовать состоянию жилья, его площади и результату, который вы хотите получить. В Ереване обычно выбирают между <a href="/{locale}/services/regular-cleaning">регулярной</a>, <a href="/{locale}/services/general-cleaning">генеральной</a> уборкой и уборкой после ремонта.</p>

<h2>Какой вид выбрать</h2>
<p>Регулярная уборка подходит для еженедельного или ежемесячного поддержания порядка. Она включает протирание пыли с видимых поверхностей, сухую и влажную уборку пола, текущий уход за кухней и санузлом. Генеральная уборка идёт глубже: обрабатываются труднодоступные места, двери, плитка, внешние поверхности мебели и накопившиеся загрязнения. При следах строительной пыли, краски или клея нужна отдельная услуга — уборка после ремонта.</p>

<h2>От чего зависит стоимость</h2>
<p>Цену правильно оценивать не только по квадратным метрам. Важны количество комнат и санузлов, окна, загруженность мебелью, степень загрязнения и дополнительные услуги. Фотографии или короткое видео помогают заранее составить более точный <a href="/{locale}/calculator">расчёт</a>.</p>

<p>Servio привозит с собой необходимый инвентарь и профессиональные средства. Перед заказом уточняются объём работы, особые поверхности и желаемый результат. Так клиент понимает, что входит в уборку, а что может потребовать отдельного расчёта.</p>
HTML,
                    'en' => <<<'HTML'
<p><a href="/{locale}/services/apartment-cleaning">Apartment cleaning</a> isn't just wiping down dust or mopping the floor. The right service should match your home's condition, size, and the result you're after. In Yerevan, people usually choose between <a href="/{locale}/services/regular-cleaning">regular</a> and <a href="/{locale}/services/general-cleaning">general (deep)</a> cleaning, or a post-renovation clean.</p>

<h2>Which type to choose</h2>
<p>Regular cleaning suits weekly or monthly upkeep. It covers dusting visible surfaces, dry and wet floor cleaning, and routine kitchen and bathroom care. General cleaning goes deeper — hard-to-reach spots, doors, tiles, the outside of furniture, and built-up grime all get attention. If there's construction dust, paint or adhesive residue, you need a separate post-construction cleaning service instead.</p>

<h2>What drives the price</h2>
<p>Price shouldn't be judged by square meters alone. What matters is the number of rooms and bathrooms, windows, how furnished the space is, how dirty it is, and any add-on services. Photos or a short video help put together a more accurate <a href="/{locale}/calculator">estimate</a> in advance.</p>

<p>Servio brings the equipment and professional supplies needed for the job. Before booking, we confirm the scope of work, any special surfaces, and the result you want — so you know exactly what's included and what might need a separate quote.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Բնակարանի մաքրում Երևանում․ ամբողջական ուղեցույց — Servio',
                    'ru' => 'Уборка квартир в Ереване: полный гид — Servio',
                    'en' => 'Apartment Cleaning in Yerevan: Complete Guide — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես ընտրել բնակարանի մաքրման տեսակը Երևանում, ինչ է ներառվում ծառայության մեջ և ինչից է կախված արժեքը։',
                    'ru' => 'Как выбрать вид уборки квартиры в Ереване, что входит в услугу и от чего зависит стоимость.',
                    'en' => 'How to choose an apartment cleaning service in Yerevan, what it includes, and what determines the price.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Պե՞տք է տանը լինել', 'answer' => 'Պարտադիր չէ, եթե նախապես կազմակերպված է մուտքը և համաձայնեցված է աշխատանքների ցանկը։'],
                        ['question' => 'Մաքրող միջոցները ներառվա՞ծ են', 'answer' => 'Այո, Servio-ն բերում է հիմնական գույքն ու միջոցները։'],
                        ['question' => 'Ինչպե՞ս իմանալ վերջնական արժեքը', 'answer' => 'Ուղարկեք մակերեսը, սենյակների քանակը և լուսանկարներ կամ օգտվեք հաշվիչից։'],
                    ],
                    'ru' => [
                        ['question' => 'Нужно ли быть дома', 'answer' => 'Не обязательно, если заранее организован доступ и согласован список работ.'],
                        ['question' => 'Чистящие средства уже включены', 'answer' => 'Да, Servio привозит с собой весь необходимый инвентарь и средства.'],
                        ['question' => 'Как узнать точную стоимость', 'answer' => 'Пришлите площадь, количество комнат и фотографии — либо воспользуйтесь калькулятором.'],
                    ],
                    'en' => [
                        ['question' => 'Do I need to be home', 'answer' => 'Not necessarily, if access has been arranged in advance and the scope of work is agreed on.'],
                        ['question' => 'Are cleaning products included', 'answer' => 'Yes, Servio brings the necessary equipment and supplies.'],
                        ['question' => 'How do I find out the final price', 'answer' => 'Send the size, number of rooms and photos, or use the calculator.'],
                    ],
                ],
                'related_services' => ['apartment-cleaning', 'regular-cleaning', 'general-cleaning'],
                'related_post_slugs' => ['bnakarani-maqrum-texapoxveluc-araj-ev-heto', 'grasenyaki-maqrum-gin-yerevan'],
            ],
            [
                'slug' => 'patuhanneri-lvacum-gin-yerevan',
                'category' => 'cleaning-tips',
                'tags' => ['պատուհանների լվացում Երևանում', 'ապակիների մաքրում', 'мойка окон в Ереване', 'window cleaning Yerevan'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-07-31'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/window-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Մասնագիտական պատուհանների լվացում Երևանում ռետինե քերիչով',
                    'ru' => 'Профессиональная мойка окон в Ереване стеклоочистителем',
                    'en' => 'Professional window cleaning in Yerevan with a squeegee',
                ],
                'title' => [
                    'hy' => 'Պատուհանների լվացում Երևանում. ինչ է ներառվում ծառայության մեջ',
                    'ru' => 'Мойка окон в Ереване: что входит и как считается цена',
                    'en' => 'Window Cleaning in Yerevan: What\'s Included and How Pricing Works',
                ],
                'excerpt' => [
                    'hy' => 'Պատուհանների, շրջանակների և վիտրաժների մասնագիտական լվացում Երևանում․ երբ պատվիրել, ինչ է ներառվում և ինչպես է հաշվվում գինը։',
                    'ru' => 'Профессиональная мойка окон, рам и витражей в Ереване: когда заказывать, что входит и как считается цена.',
                    'en' => 'Professional window, frame and stained-glass cleaning in Yerevan: when to book it, what\'s included, and how pricing works.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Մաքուր ապակին ավելի շատ բնական լույս է թողնում տուն և տեսանելիորեն թարմացնում է ամբողջ սենյակը։ Սակայն լավ արդյունքի համար միայն ապակին սրբելը բավարար չէ։ Մասնագիտական լվացման ժամանակ ուշադրություն է դարձվում ապակուն, շրջանակին, պատուհանագոգին, անկյուններին և հասանելի արտաքին հատվածներին։</p>

<h2>Ե՞րբ է հատկապես անհրաժեշտ</h2>
<p>Պատուհանները հաճախ լվանում են գարնանը և աշնանը, <a href="/{locale}/services/general-cleaning">ընդհանուր մաքրման</a> շրջանակում, բնակարան հանձնելուց առաջ կամ երկարատև փոշուց հետո։ Վիտրաժները, մեծ բարձրությամբ ապակիները և դժվար բացվող կառուցվածքները նախապես գնահատվում են, քանի որ կարող են պահանջել հատուկ գործիք կամ անվտանգության լրացուցիչ պայմաններ։</p>

<h2>Ինչի՞ց է կախված գինը</h2>
<p>Գնի վրա ազդում են պատուհանների քանակը, փեղկերի չափը, արտաքին կողմի հասանելիությունը, ցանցերը, շրջանակների վիճակը և շինարարական հետքերը։ Ճշգրիտ <a href="/{locale}/calculator">հաշվարկի</a> համար օգտակար է ուղարկել յուրաքանչյուր պատուհանի ընդհանուր տեսքով լուսանկար։</p>

<p>Չի կարելի ներկի կամ ցեմենտի կաթիլները հեռացնել պատահական սուր գործիքով․ ապակին կամ պրոֆիլը կարող են քերծվել։ Servio-ն ընտրում է եղանակը՝ մակերեսի և աղտոտման տեսակից ելնելով, և կարող է առաջարկել պատուհանների լվացումը զուգակցել <a href="/{locale}/services/apartment-cleaning">բնակարանի ընդհանուր մաքրման</a> հետ։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Чистое стекло пропускает больше естественного света и заметно освежает всю комнату. Но для хорошего результата недостаточно просто протереть стекло. При профессиональной мойке внимание уделяется стеклу, раме, подоконнику, углам и доступным наружным участкам.</p>

<h2>Когда это особенно нужно</h2>
<p>Окна чаще всего моют весной и осенью, в рамках <a href="/{locale}/services/general-cleaning">генеральной уборки</a>, перед сдачей квартиры или после длительного скопления пыли. Витражи, окна на большой высоте и конструкции со сложным открыванием оцениваются заранее — им может понадобиться специальный инструмент или дополнительные условия безопасности.</p>

<h2>От чего зависит цена</h2>
<p>На цену влияют количество окон, размер створок, доступность наружной стороны, наличие сеток, состояние рам и следы после ремонта. Для точного <a href="/{locale}/calculator">расчёта</a> полезно прислать общее фото каждого окна.</p>

<p>Капли краски или цемента нельзя удалять случайным острым предметом — можно поцарапать стекло или профиль. Servio подбирает средство исходя из типа поверхности и загрязнения и может предложить совместить мойку окон с <a href="/{locale}/services/apartment-cleaning">генеральной уборкой квартиры</a>.</p>
HTML,
                    'en' => <<<'HTML'
<p>Clean glass lets in more natural light and visibly refreshes an entire room. But wiping the glass alone isn't enough for a good result. Professional window cleaning covers the glass, the frame, the sill, the corners, and any accessible exterior sections.</p>

<h2>When it's especially worth booking</h2>
<p>Windows are typically washed in spring and autumn, as part of a <a href="/{locale}/services/general-cleaning">general clean</a>, before handing over an apartment, or after long periods of dust buildup. Stained glass, high windows and hard-to-open frames are assessed in advance, since they may need special tools or extra safety measures.</p>

<h2>What determines the price</h2>
<p>Price depends on the number of windows, sash size, how accessible the exterior side is, screens, frame condition, and any construction residue. For an accurate <a href="/{locale}/calculator">estimate</a>, it helps to send an overall photo of each window.</p>

<p>Paint or cement drips shouldn't be scraped off with whatever sharp tool is at hand — that can scratch the glass or the frame. Servio chooses its method based on the surface and the type of residue, and can pair window cleaning with a full <a href="/{locale}/services/apartment-cleaning">apartment clean</a>.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Պատուհանների լվացում Երևանում․ գին և ընթացք — Servio',
                    'ru' => 'Мойка окон в Ереване: что входит и как считается цена — Servio',
                    'en' => 'Window Cleaning in Yerevan: Service Guide — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Պատուհանների, շրջանակների և վիտրաժների մասնագիտական լվացում Երևանում․ երբ պատվիրել, ինչ է ներառվում և ինչպես է հաշվվում գինը։',
                    'ru' => 'Профессиональная мойка окон, рам и витражей в Ереване: когда заказывать, что входит и как считается цена.',
                    'en' => 'Professional window, frame and stained-glass cleaning in Yerevan: when to book it, what\'s included, and how pricing works.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Շրջանակները նույնպես մաքրվո՞ւմ են', 'answer' => 'Ծավալը համաձայնեցվում է պատվերի ժամանակ, և կարող է ներառել շրջանակներն ու պատուհանագոգերը։'],
                        ['question' => 'Դժվարամատչելի արտաքին ապակիները լվացվո՞ւմ են', 'answer' => 'Միայն անվտանգ հասանելիության դեպքում կամ համապատասխան հատուկ լուծմամբ։'],
                        ['question' => 'Ե՞րբ պատվիրել', 'answer' => 'Ցանկալի է ընտրել առանց ուժեղ քամու և տեղումների օր։'],
                    ],
                    'ru' => [
                        ['question' => 'Рамы тоже моются', 'answer' => 'Объём согласовывается при заказе и может включать рамы и подоконники.'],
                        ['question' => 'Труднодоступные наружные стёкла моются', 'answer' => 'Только при безопасном доступе или с использованием специального решения.'],
                        ['question' => 'Когда лучше заказывать', 'answer' => 'Желательно выбрать день без сильного ветра и осадков.'],
                    ],
                    'en' => [
                        ['question' => 'Are the frames cleaned too', 'answer' => 'The scope is agreed when booking and can include frames and windowsills.'],
                        ['question' => 'Do you clean hard-to-reach exterior glass', 'answer' => 'Only with safe access or a suitable specialized solution.'],
                        ['question' => 'When\'s the best time to book', 'answer' => 'A day without strong wind or rain works best.'],
                    ],
                ],
                'related_services' => ['general-cleaning', 'apartment-cleaning'],
                'related_post_slugs' => ['garnanayin-maqrum-yerevan', 'bnakarani-maqrum-gin-inch-e-nerarvum-yerevan'],
            ],
            [
                'slug' => 'nerqnaki-qimmaqrum-gin-yerevan',
                'category' => 'upholstery-care',
                'tags' => ['ներքնակի քիմմաքրում Երևանում', 'մատրասի քիմմաքրում', 'химчистка матраса Ереван', 'mattress cleaning Yerevan'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-04'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/mattress-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Երկտեղանոց ներքնակի խորը քիմմաքրում էքստրակցիոն սարքով',
                    'ru' => 'Глубокая химчистка двуспального матраса экстракционным методом',
                    'en' => 'Deep extraction cleaning of a double mattress',
                ],
                'title' => [
                    'hy' => 'Ներքնակի քիմմաքրում Երևանում. փուլեր և չորացում',
                    'ru' => 'Химчистка матраса в Ереване: этапы и сушка',
                    'en' => 'Mattress Cleaning in Yerevan: Process and Drying',
                ],
                'excerpt' => [
                    'hy' => 'Ինչպես է կատարվում ներքնակի խորը քիմմաքրումը, որքան է տևում չորացումը և երբ արժե պատվիրել ծառայությունը։',
                    'ru' => 'Как проходит глубокая химчистка матраса, сколько занимает сушка и когда стоит заказывать услугу.',
                    'en' => 'How deep mattress cleaning works, how long drying takes, and when it\'s worth booking.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Ներքնակն ամեն օր երկար ժամեր շփվում է մարմնի հետ և աստիճանաբար կուտակում է փոշի, մաշկի մանր մասնիկներ, քրտինքի հետքեր և հոտեր։ Մակերեսը կարող է մաքուր թվալ, սակայն սովորական փոշեկուլը չի հեռացնում խորքում նստած լուծվող աղտոտումը։</p>

<h2>Ինչպես է ընթանում աշխատանքը</h2>
<p>Մասնագիտական <a href="/{locale}/services/mattress-cleaning">քիմմաքրման</a> գործընթացը սկսվում է գործվածքի և բծերի զննումից։ Այնուհետև կատարվում է չոր փոշեկուլում, ընտրվում է նյութին համապատասխան լուծույթ, մշակվում են բծերը և էքստրակցիոն սարքով հեռացվում է լուծված կեղտը։ Չափազանց թրջելը ցանկալի չէ, քանի որ երկարացնում է չորացումը և կարող է վնասել ներքին շերտերը։</p>

<h2>Չորացում և ե՞րբ պատվիրել</h2>
<p>Չորացման տևողությունը կախված է օդափոխությունից, խոնավությունից, նյութից և օգտագործված ջրի քանակից։ Մինչև ամբողջական չորացումը ներքնակի վրա քնել կամ այն ծածկել խորհուրդ չի տրվում։ Ծառայությունը հատկապես օգտակար է տեղափոխվելուց առաջ, վարձակալից հետո, տեսանելի բծերի, հոտի կամ երկար տարիներ առանց խորքային խնամքի օգտագործելու դեպքում։ Հոտի դեպքում կարող է օգտակար լինել նաև <a href="/{locale}/services/steam-cleaning-disinfection">գոլորշային ախտահանումը</a>։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Матрас каждый день часами контактирует с телом и постепенно накапливает пыль, мельчайшие частицы кожи, следы пота и запахи. Поверхность может выглядеть чистой, но обычный пылесос не удаляет растворимые загрязнения, осевшие в глубине.</p>

<h2>Как проходит работа</h2>
<p>Профессиональная <a href="/{locale}/services/mattress-cleaning">химчистка</a> начинается с осмотра ткани и пятен. Затем выполняется сухая уборка пылесосом, подбирается раствор под материал, обрабатываются пятна, а экстракционное оборудование извлекает растворённое загрязнение. Излишнее увлажнение нежелательно — оно удлиняет сушку и может повредить внутренние слои.</p>

<h2>Сушка и когда заказывать</h2>
<p>Время высыхания зависит от вентиляции, влажности, материала и количества использованной воды. Спать на матрасе или накрывать его до полного высыхания не рекомендуется. Услуга особенно полезна перед переездом, после съёмщиков, при заметных пятнах, запахе или многолетнем использовании без глубокого ухода. При запахе также может помочь <a href="/{locale}/services/steam-cleaning-disinfection">паровая дезинфекция</a>.</p>
HTML,
                    'en' => <<<'HTML'
<p>A mattress spends hours in contact with the body every day and gradually builds up dust, tiny skin particles, sweat traces and odors. The surface can look clean while a regular vacuum still leaves the soluble grime sitting deeper down.</p>

<h2>How the process works</h2>
<p>Professional <a href="/{locale}/services/mattress-cleaning">mattress cleaning</a> starts with checking the fabric and any stains. Next comes a dry vacuum pass, choosing a solution suited to the material, treating stains, and using extraction equipment to pull out the dissolved dirt. Oversoaking isn't the goal here — it just extends drying time and can damage the inner layers.</p>

<h2>Drying, and when to book it</h2>
<p>Drying time depends on ventilation, humidity, the material, and how much water was used. Sleeping on the mattress or covering it before it's fully dry isn't recommended. This service is especially useful before moving, after tenants move out, when stains or odor are visible, or after years of use without a deep clean. For odor specifically, <a href="/{locale}/services/steam-cleaning-disinfection">steam disinfection</a> can help too.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Ներքնակի քիմմաքրում Երևանում․ փուլեր և չորացում — Servio',
                    'ru' => 'Химчистка матраса в Ереване: этапы и сушка — Servio',
                    'en' => 'Mattress Cleaning in Yerevan: Process and Drying — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես է կատարվում ներքնակի խորը քիմմաքրումը, որքան է տևում չորացումը և երբ արժե պատվիրել ծառայությունը։',
                    'ru' => 'Как проходит глубокая химчистка матраса, сколько занимает сушка и когда стоит заказывать услугу.',
                    'en' => 'How deep mattress cleaning works, how long drying takes, and when it\'s worth booking.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Բոլոր բծերը լիովին հեռացվո՞ւմ են', 'answer' => 'Ոչ մի մասնագետ չի կարող դա խոստանալ առանց զննման․ արդյունքը կախված է բծի տեսակից և հնությունից։'],
                        ['question' => 'Երկու կողմն էլ մաքրվո՞ւմ է', 'answer' => 'Դա պետք է նշել պատվերի ժամանակ և ներառել հաշվարկում։'],
                        ['question' => 'Որքա՞ն է չորանում', 'answer' => 'Սովորաբար մի քանի ժամ, բայց իրական ժամանակը կախված է պայմաններից։'],
                    ],
                    'ru' => [
                        ['question' => 'Все пятна удаляются полностью', 'answer' => 'Ни один специалист не даст такую гарантию без осмотра — результат зависит от типа пятна и его «возраста».'],
                        ['question' => 'Чистится ли матрас с двух сторон', 'answer' => 'Это нужно указать при заказе — тогда стороны включат в расчёт.'],
                        ['question' => 'Сколько сохнет матрас', 'answer' => 'Обычно несколько часов, но точное время зависит от условий.'],
                    ],
                    'en' => [
                        ['question' => 'Will every stain come out completely', 'answer' => 'No professional can promise that without seeing it first — the result depends on the stain\'s type and age.'],
                        ['question' => 'Is both sides of the mattress cleaned', 'answer' => 'Flag that when booking so both sides are included in the quote.'],
                        ['question' => 'How long does it take to dry', 'answer' => 'Usually a few hours, though the exact time depends on conditions.'],
                    ],
                ],
                'related_services' => ['mattress-cleaning', 'steam-cleaning-disinfection'],
                'related_post_slugs' => ['bazmoci-qimmaqrum-kendanu-mazi-ev-hoti-dem', 'tan-maqrum-allergiayi-depqum-yerevan'],
            ],
            [
                'slug' => 'gorgeri-qimmaqrum-gin-tany-yerevan',
                'category' => 'upholstery-care',
                'tags' => ['գորգերի քիմմաքրում Երևանում', 'химчистка ковров Ереван', 'carpet cleaning Yerevan'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-14'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/carpet-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Գորգի խորը քիմմաքրում էքստրակցիոն սարքով Երևանում',
                    'ru' => 'Глубокая химчистка ковра экстракционным методом в Ереване',
                    'en' => 'Deep extraction carpet cleaning in Yerevan',
                ],
                'title' => [
                    'hy' => 'Գորգերի քիմմաքրում Երևանում՝ տանը',
                    'ru' => 'Химчистка ковров на дому в Ереване',
                    'en' => 'Carpet Cleaning at Home in Yerevan',
                ],
                'excerpt' => [
                    'hy' => 'Գորգի անվտանգ խորը մաքրում տանը․ ինչ բծեր են մշակվում, ինչպես է ընտրվում մեթոդը և ինչից է կախված չորացումը։',
                    'ru' => 'Безопасная глубокая чистка ковра на дому: какие пятна обрабатываются, как выбирается метод и от чего зависит сушка.',
                    'en' => 'Safe deep carpet cleaning at home: which stains get treated, how the method is chosen, and what affects drying time.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Գորգը պահում է ոչ միայն տեսանելի փշրանքները, այլև մանր փոշին, կենդանու մազը և հեղուկների հետքերը։ Խորը մաքրումը թարմացնում է գույնը և հեռացնում այն աղտոտումը, որը սովորական փոշեկուլով չի դուրս գալիս։</p>

<h2>Ինչպես է ընտրվում մեթոդը</h2>
<p>Մինչ աշխատանքը պետք է որոշել նյութը, ներկի կայունությունը, հիմքի տեսակը և բծերի բնույթը։ Բուրդը, վիսկոզը, սինթետիկան և ձեռագործ գորգերը նույն եղանակով մշակել չի կարելի։ Տեղում <a href="/{locale}/services/carpet-textile-cleaning">քիմմաքրումը</a> լավագույնն է այն գորգերի համար, որոնց նյութն ու հիմքը թույլ են տալիս խոնավ էքստրակցիա։ Զգայուն կամ արժեքավոր գործվածքների դեպքում կարող է պահանջվել այլ մեթոդ։</p>

<h2>Չորացում և արժեք</h2>
<p>Աշխատանքից հետո անհրաժեշտ է լավ օդափոխություն և ազատ օդի շրջանառություն։ Գորգի վրա կահույք դնելը կամ քայլելը ցանկալի չէ մինչև լիարժեք չորացումը։ Գինը հաշվվում է ըստ մակերեսի, նյութի, աղտոտվածության և հատուկ բծերի մշակման անհրաժեշտության։ Նույն սկզբունքով կարելի է մաքրել նաև <a href="/{locale}/services/sofa-cleaning">փափուկ կահույքը</a> միևնույն այցի ընթացքում։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Ковёр удерживает не только видимые крошки, но и мелкую пыль, шерсть животных и следы жидкостей. Глубокая чистка освежает цвет и удаляет те загрязнения, которые не выходят обычным пылесосом.</p>

<h2>Как выбирается метод</h2>
<p>Перед началом работы нужно определить материал, стойкость краски, тип основы и характер пятен. Шерсть, вискозу, синтетику и ручную работу нельзя обрабатывать одним и тем же способом. <a href="/{locale}/services/carpet-textile-cleaning">Химчистка</a> на месте лучше всего подходит коврам, чей материал и основа допускают влажную экстракцию. Для деликатных или ценных тканей может потребоваться другой метод.</p>

<h2>Сушка и стоимость</h2>
<p>После работы важны хорошая вентиляция и свободная циркуляция воздуха. Ставить мебель на ковёр или ходить по нему до полного высыхания нежелательно. Цена рассчитывается исходя из площади, материала, степени загрязнения и необходимости обработки отдельных пятен. По тому же принципу за один визит можно почистить и <a href="/{locale}/services/sofa-cleaning">мягкую мебель</a>.</p>
HTML,
                    'en' => <<<'HTML'
<p>A carpet holds onto more than visible crumbs — it traps fine dust, pet hair and liquid residue too. A deep clean refreshes the color and lifts out the grime a regular vacuum can't reach.</p>

<h2>How the method is chosen</h2>
<p>Before starting, we need to know the material, how colorfast the dye is, the backing type, and what kind of stains are present. Wool, viscose, synthetic fibers and hand-woven rugs can't all be treated the same way. On-site <a href="/{locale}/services/carpet-textile-cleaning">dry cleaning</a> works best for carpets whose material and backing tolerate wet extraction. Delicate or valuable pieces may call for a different method.</p>

<h2>Drying and price</h2>
<p>Good ventilation and airflow matter after the job is done. Putting furniture back or walking on the carpet before it's fully dry isn't a good idea. Price is based on surface area, material, how soiled it is, and whether specific stains need extra treatment. The same visit can often cover <a href="/{locale}/services/sofa-cleaning">upholstery cleaning</a> too.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Գորգերի քիմմաքրում Երևանում՝ տանը — Servio',
                    'ru' => 'Химчистка ковров на дому в Ереване — Servio',
                    'en' => 'Carpet Cleaning at Home in Yerevan — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Գորգի անվտանգ խորը մաքրում տանը․ ինչ բծեր են մշակվում, ինչպես է ընտրվում մեթոդը և ինչից է կախված չորացումը։',
                    'ru' => 'Безопасная глубокая чистка ковра на дому: какие пятна обрабатываются, как выбирается метод и от чего зависит сушка.',
                    'en' => 'Safe deep carpet cleaning at home: which stains get treated, how the method is chosen, and what affects drying time.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Հոտը կվերանա՞', 'answer' => 'Շատ դեպքերում զգալիորեն նվազում է, բայց աղբյուրը և գորգի հիմքի վիճակը կարևոր են։'],
                        ['question' => 'Կարելի՞ է մաքրել առանց գորգը տանելու', 'answer' => 'Այո, եթե նյութն ու հիմքը հարմար են տեղում մաքրման համար։'],
                        ['question' => 'Ինչպե՞ս պատրաստվել', 'answer' => 'Հեռացրեք մանր իրերը և նախապես նշեք բոլոր հայտնի բծերը։'],
                    ],
                    'ru' => [
                        ['question' => 'Запах уйдёт полностью', 'answer' => 'В большинстве случаев заметно уменьшается, но многое зависит от источника и состояния основы ковра.'],
                        ['question' => 'Можно почистить, не вывозя ковёр', 'answer' => 'Да, если материал и основа подходят для чистки на месте.'],
                        ['question' => 'Как подготовиться', 'answer' => 'Уберите мелкие предметы и заранее укажите все известные пятна.'],
                    ],
                    'en' => [
                        ['question' => 'Will the odor go away completely', 'answer' => 'In most cases it\'s noticeably reduced, but the source and the carpet backing\'s condition matter.'],
                        ['question' => 'Can it be cleaned without taking the carpet away', 'answer' => 'Yes, if the material and backing are suitable for on-site cleaning.'],
                        ['question' => 'How should I prepare', 'answer' => 'Clear away small items and point out any known stains beforehand.'],
                    ],
                ],
                'related_services' => ['carpet-textile-cleaning', 'sofa-cleaning'],
                'related_post_slugs' => ['bazmoci-qimmaqrum-kendanu-mazi-ev-hoti-dem', 'nerqnaki-qimmaqrum-gin-yerevan'],
            ],
            [
                'slug' => 'grasenyaki-maqrum-gin-yerevan',
                'category' => 'guides',
                'tags' => ['գրասենյակների մաքրում Երևանում', 'уборка офисов Ереван', 'office cleaning Yerevan'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-07'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/office-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Գրասենյակի մասնագիտական երեկոյան մաքրում Երևանում',
                    'ru' => 'Профессиональная вечерняя уборка офиса в Ереване',
                    'en' => 'Professional evening office cleaning in Yerevan',
                ],
                'title' => [
                    'hy' => 'Գրասենյակների մաքրում Երևանում՝ կանոնավոր սպասարկում',
                    'ru' => 'Уборка офисов в Ереване: регулярное обслуживание',
                    'en' => 'Office Cleaning in Yerevan: Regular Service',
                ],
                'excerpt' => [
                    'hy' => 'Գրասենյակի կանոնավոր մաքրման ծրագիր՝ աշխատանքային գոտիներ, խոհանոց, սանհանգույց և անհատական գրաֆիկ։',
                    'ru' => 'Программа регулярной уборки офиса: рабочие зоны, кухня, санузел и индивидуальный график.',
                    'en' => 'A regular office cleaning program covering work areas, the kitchen, restrooms, and a schedule built around your office.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Մաքուր գրասենյակը կարևոր է ոչ միայն արտաքին տեսքի, այլև աշխատակիցների հարմարավետության և հյուրերի առաջին տպավորության համար։ Արդյունավետ <a href="/{locale}/services/office-cleaning">սպասարկումը</a> սկսվում է տարածքի զննումից և հստակ checklist-ից։</p>

<h2>Ինչ է ներառվում ամենօրյա ծրագրում</h2>
<p>Ամենօրյա կամ պարբերական ծրագրում սովորաբար ներառվում են հատակները, աշխատանքային սեղանների հասանելի հատվածները, ընդհանուր օգտագործման գոտիները, խոհանոցը, սանհանգույցը և աղբամանները։ Հանդիպումների սենյակները, ապակե միջնապատերը, գորգածածկը և փափուկ կահույքը կարող են ունենալ առանձին պարբերականություն։</p>

<h2>Ինչպես կազմել գրաֆիկը</h2>
<p>Գրաֆիկը ընտրվում է մարդկանց հոսքից և աշխատանքային ժամերից ելնելով։ Փոքր գրասենյակին կարող է բավարար լինել շաբաթական մի քանի այց, իսկ մեծ թիմի կամ հաճախորդների մեծ հոսքի դեպքում անհրաժեշտ է ամենօրյա <a href="/{locale}/services/regular-cleaning">սպասարկում</a>։ Անվտանգության համար նախապես սահմանվում են մուտքի կանոնները, բանալիների փոխանցումը և այն սեղանները կամ տեխնիկան, որոնց աշխատակիցը չպետք է դիպչի։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Чистый офис важен не только для внешнего вида, но и для комфорта сотрудников и первого впечатления гостей. Эффективное <a href="/{locale}/services/office-cleaning">обслуживание</a> начинается с осмотра помещения и чёткого чек-листа.</p>

<h2>Что входит в ежедневную программу</h2>
<p>В ежедневную или периодическую программу обычно входят полы, доступные участки рабочих столов, зоны общего пользования, кухня, санузел и корзины для мусора. Переговорные, стеклянные перегородки, ковровое покрытие и мягкая мебель могут иметь отдельную периодичность.</p>

<h2>Как составить график</h2>
<p>График подбирается исходя из потока людей и рабочих часов. Небольшому офису может хватить нескольких визитов в неделю, а при большой команде или потоке клиентов нужно ежедневное <a href="/{locale}/services/regular-cleaning">обслуживание</a>. Для безопасности заранее оговариваются правила доступа, передача ключей и столы или техника, к которым сотруднику клининга нельзя прикасаться.</p>
HTML,
                    'en' => <<<'HTML'
<p>A clean office matters not just for appearances, but for employee comfort and the first impression guests get. Effective <a href="/{locale}/services/office-cleaning">office cleaning</a> starts with a walkthrough of the space and a clear checklist.</p>

<h2>What a daily program covers</h2>
<p>A daily or recurring program usually covers floors, the accessible parts of desks, common areas, the kitchen, restrooms and trash bins. Meeting rooms, glass partitions, carpeted flooring and upholstered furniture can run on their own separate schedule.</p>

<h2>How to build a schedule</h2>
<p>The schedule is built around foot traffic and working hours. A small office might only need a few visits a week, while a larger team or heavy client traffic calls for daily <a href="/{locale}/services/regular-cleaning">service</a>. For security, access rules, key handover, and any desks or equipment the cleaning staff shouldn't touch are agreed on in advance.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Գրասենյակների մաքրում Երևանում՝ կանոնավոր սպասարկում — Servio',
                    'ru' => 'Уборка офисов в Ереване: регулярное обслуживание — Servio',
                    'en' => 'Office Cleaning in Yerevan — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Գրասենյակի կանոնավոր մաքրման ծրագիր՝ աշխատանքային գոտիներ, խոհանոց, սանհանգույց և անհատական գրաֆիկ։',
                    'ru' => 'Программа регулярной уборки офиса: рабочие зоны, кухня, санузел и индивидуальный график.',
                    'en' => 'A regular office cleaning program covering work areas, the kitchen, restrooms, and a schedule built around your office.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եք աշխատել երեկոյան', 'answer' => 'Գրաֆիկը համաձայնեցվում է ըստ օբյեկտի աշխատանքի։'],
                        ['question' => 'Նյութերն ու գույքը ո՞վ է տրամադրում', 'answer' => 'Servio-ն կարող է կազմակերպել անհրաժեշտ հիմնական միջոցներն ու գույքը։'],
                        ['question' => 'Հնարավո՞ր է երկարաժամկետ պայմանագիր', 'answer' => 'Այո, ծավալը և հաճախականությունը գնահատելուց հետո։'],
                    ],
                    'ru' => [
                        ['question' => 'Можете работать вечером', 'answer' => 'График согласовывается исходя из режима работы объекта.'],
                        ['question' => 'Кто предоставляет средства и инвентарь', 'answer' => 'Servio может организовать необходимые основные средства и инвентарь.'],
                        ['question' => 'Возможен ли долгосрочный договор', 'answer' => 'Да, после оценки объёма и периодичности работ.'],
                    ],
                    'en' => [
                        ['question' => 'Can you work in the evening', 'answer' => 'The schedule is agreed based on how the site operates.'],
                        ['question' => 'Who supplies the products and equipment', 'answer' => 'Servio can arrange the necessary basic supplies and equipment.'],
                        ['question' => 'Is a long-term contract possible', 'answer' => 'Yes, once the scope and frequency have been assessed.'],
                    ],
                ],
                'related_services' => ['office-cleaning', 'regular-cleaning'],
                'related_post_slugs' => ['bnakarani-maqrum-gin-inch-e-nerarvum-yerevan', 'oravardzov-bnakaranneri-maqrum-yerevan'],
            ],
            [
                'slug' => 'oravardzov-bnakaranneri-maqrum-yerevan',
                'category' => 'guides',
                'tags' => ['օրավարձով բնակարանի մաքրում Երևանում', 'уборка посуточных квартир Ереван', 'Airbnb cleaning Yerevan'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-11'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/airbnb-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Հյուրերի ընդունմանը պատրաստ օրավարձով բնակարանի մաքրում Երևանում',
                    'ru' => 'Уборка посуточной квартиры в Ереване перед заездом гостей',
                    'en' => 'Short-term rental cleaning in Yerevan, ready for the next guest',
                ],
                'title' => [
                    'hy' => 'Օրավարձով և Airbnb բնակարանների մաքրում Երևանում',
                    'ru' => 'Уборка Airbnb и посуточных квартир в Ереване',
                    'en' => 'Airbnb and Short-Term Rental Cleaning in Yerevan',
                ],
                'excerpt' => [
                    'hy' => 'Արագ և ստանդարտացված մաքրում հյուրերի միջև՝ սպիտակեղեն, սանհանգույց, խոհանոց և վերջնական լուսանկարներ։',
                    'ru' => 'Быстрая и стандартизированная уборка между заездами гостей: бельё, санузел, кухня и финальные фото.',
                    'en' => 'Fast, standardized turnover cleaning between guests: linens, bathroom, kitchen, and a final photo check.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Կարճաժամկետ վարձակալության դեպքում մաքրման ժամանակը սահմանափակ է, իսկ հյուրի սպասելիքը՝ բարձր։ Այդ պատճառով անհրաժեշտ է ոչ թե պարզապես արագ մաքրում, այլ կրկնվող ստանդարտ, որով յուրաքանչյուր այցից հետո <a href="/{locale}/services/apartment-cleaning">բնակարանը</a> նույն պատրաստ տեսքն ունի։</p>

<h2>Ինչ է ներառում turnover մաքրումը</h2>
<p>Turnover մաքրումը ներառում է սանհանգույցի և խոհանոցի մշակումը, հատակները, փոշին, հայելիները, մահճակալի պատրաստումը և հյուրի թողած տեսանելի աղբի հեռացումը։ Առանձին համաձայնությամբ կազմակերպվում են սպիտակեղենի ու սրբիչների փոխարինումը, պարագաների մնացորդի ստուգումը և վնասվածքների լուսանկարումը։</p>

<h2>Ինչպես կազմակերպել գործընթացը</h2>
<p>Սեփականատիրոջ համար օգտակար է ունենալ պարզ checklist՝ ըստ սենյակների, պահեստային սպիտակեղեն նույն բնակարանում և մեկ կոնտակտային անձ։ Մաքրումից հետո արված մի քանի ստուգիչ լուսանկար օգնում է արագ վերահսկել պատրաստվածությունը, հատկապես երբ սեփականատերը քաղաքում չէ։ Ամսական <a href="/{locale}/services/regular-cleaning">պարբերական մաքրման</a> ձևաչափը հարմար է այն օբյեկտների համար, որտեղ հյուրերի հոսքը կայուն է։</p>
HTML,
                    'ru' => <<<'HTML'
<p>При краткосрочной аренде время на уборку ограничено, а ожидания гостя высокие. Поэтому важна не просто быстрая уборка, а повторяемый стандарт, благодаря которому <a href="/{locale}/services/apartment-cleaning">квартира</a> после каждого заезда выглядит одинаково готовой.</p>

<h2>Что входит в turnover-уборку</h2>
<p>Turnover-уборка включает обработку санузла и кухни, полы, пыль, зеркала, заправку постели и вынос заметного мусора, оставленного гостем. По отдельной договорённости организуются замена белья и полотенец, проверка остатка расходных материалов и фотофиксация повреждений.</p>

<h2>Как организовать процесс</h2>
<p>Владельцу полезно иметь простой чек-лист по комнатам, запас белья в самой квартире и одного контактного человека. Несколько контрольных фото после уборки помогают быстро проверить готовность, особенно когда владельца нет в городе. Формат ежемесячной <a href="/{locale}/services/regular-cleaning">регулярной уборки</a> подходит объектам со стабильным потоком гостей.</p>
HTML,
                    'en' => <<<'HTML'
<p>With short-term rentals, cleaning time is tight and guest expectations are high. What's needed isn't just a fast clean, but a repeatable standard so the <a href="/{locale}/services/apartment-cleaning">apartment</a> looks equally ready after every stay.</p>

<h2>What turnover cleaning covers</h2>
<p>Turnover cleaning covers the bathroom and kitchen, floors, dusting, mirrors, making the bed, and clearing any visible trash the guest left behind. By separate arrangement, it can also include swapping linens and towels, checking supply levels, and photographing any damage.</p>

<h2>How to organize the process</h2>
<p>It helps the owner to have a simple room-by-room checklist, a spare set of linens kept in the apartment, and one point of contact. A few check photos taken after cleaning make it easy to confirm the place is ready, especially when the owner is out of town. A monthly <a href="/{locale}/services/regular-cleaning">regular cleaning</a> arrangement suits listings with a steady flow of guests.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Օրավարձով և Airbnb բնակարանների մաքրում Երևանում — Servio',
                    'ru' => 'Уборка Airbnb и посуточных квартир в Ереване — Servio',
                    'en' => 'Airbnb and Short-Term Rental Cleaning in Yerevan — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Արագ և ստանդարտացված մաքրում հյուրերի միջև՝ սպիտակեղեն, սանհանգույց, խոհանոց և վերջնական լուսանկարներ։',
                    'ru' => 'Быстрая и стандартизированная уборка между заездами гостей: бельё, санузел, кухня и финальные фото.',
                    'en' => 'Fast, standardized turnover cleaning between guests: linens, bathroom, kitchen, and a final photo check.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եք ավարտել մինչև check-in', 'answer' => 'Ժամը հաստատվում է միայն նախորդ հյուրի դուրս գալու և իրական ծավալի հիման վրա։'],
                        ['question' => 'Սպիտակեղենի լվացումը ներառվա՞ծ է', 'answer' => 'Այն պետք է նախապես համաձայնեցնել որպես առանձին գործընթաց։'],
                        ['question' => 'Կարո՞ղ եք հաղորդել վնասների մասին', 'answer' => 'Այո, եթե դա ներառված է օբյեկտի checklist-ում։'],
                    ],
                    'ru' => [
                        ['question' => 'Успеете до заезда гостя', 'answer' => 'Время подтверждается только после выезда предыдущего гостя и с учётом реального объёма работ.'],
                        ['question' => 'Стирка белья входит в услугу', 'answer' => 'Это нужно заранее согласовать как отдельный процесс.'],
                        ['question' => 'Сообщите ли вы о повреждениях', 'answer' => 'Да, если это предусмотрено чек-листом объекта.'],
                    ],
                    'en' => [
                        ['question' => 'Can you finish before check-in', 'answer' => 'The time is only confirmed once the previous guest has checked out and the real scope of work is known.'],
                        ['question' => 'Is laundering the linens included', 'answer' => 'That needs to be agreed in advance as a separate process.'],
                        ['question' => 'Will you flag any damage', 'answer' => 'Yes, if that\'s part of the property\'s checklist.'],
                    ],
                ],
                'related_services' => ['apartment-cleaning', 'regular-cleaning'],
                'related_post_slugs' => ['bnakarani-maqrum-gin-inch-e-nerarvum-yerevan', 'bnakarani-maqrum-texapoxveluc-araj-ev-heto'],
            ],
            [
                'slug' => 'bnakarani-maqrum-texapoxveluc-araj-ev-heto',
                'category' => 'guides',
                'tags' => ['մաքրում տեղափոխվելուց առաջ Երևանում', 'уборка перед переездом Ереван', 'move-out cleaning Yerevan'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-18'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/move-in-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Դատարկ բնակարանի մաքրում տեղափոխվելուց առաջ Երևանում',
                    'ru' => 'Уборка пустой квартиры перед переездом в Ереване',
                    'en' => 'Cleaning an empty apartment before moving in, Yerevan',
                ],
                'title' => [
                    'hy' => 'Մաքրում տեղափոխվելուց առաջ կամ հետո Երևանում',
                    'ru' => 'Уборка квартиры до или после переезда',
                    'en' => 'Move-In and Move-Out Cleaning in Yerevan',
                ],
                'excerpt' => [
                    'hy' => 'Դատարկ բնակարանի խորքային մաքրում՝ պահարաններ, խոհանոց, սանհանգույց և մակերեսներ նոր բնակվելու կամ հանձնելու համար։',
                    'ru' => 'Глубокая уборка пустой квартиры: шкафы, кухня, санузел и поверхности перед заселением или сдачей жилья.',
                    'en' => 'A deep clean of an empty apartment — cabinets, kitchen, bathroom and surfaces — before moving in or handing the place over.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Դատարկ բնակարանը մաքրելու ամենամեծ առավելությունն այն է, որ գրեթե բոլոր մակերեսները հասանելի են։ Սա լավ պահ է պահարանների ներսը, անկյունները, դռները, սալիկները և տեխնիկայի շուրջ հատվածները մշակելու համար։</p>

<h2>Ինչու է սա հարմար պահ</h2>
<p>Տեղափոխվելուց առաջ <a href="/{locale}/services/general-cleaning">մաքրումը</a> ստեղծում է պատրաստ և հիգիենիկ միջավայր նոր բնակիչների համար։ Դուրս գալուց հետո մաքրումը օգնում է բնակարանը պատշաճ վիճակով հանձնել սեփականատիրոջը կամ հաջորդ վարձակալին։ Սակայն եթե բնակարանում կան վերանորոգման թարմ հետքեր, անհրաժեշտ է ընտրել ոչ թե սովորական move-out, այլ հետշինարարական մաքրում։</p>

<h2>Ինչ ճշտել պատվերից առաջ</h2>
<p>Պատվերից առաջ պետք է ճշտել՝ <a href="/{locale}/services/apartment-cleaning">բնակարանը</a> լիովին դատա՞րկ է, արդյոք մաքրվելու են պահարանների ու տեխնիկայի ներսը, կան պատուհաններ, պատշգամբ կամ դժվար բծեր։ Այս մանրամասները զգալիորեն փոխում են աշխատանքի ծավալը։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Главное преимущество уборки пустой квартиры в том, что почти все поверхности доступны. Это удачный момент, чтобы обработать внутреннюю часть шкафов, углы, двери, плитку и участки вокруг техники.</p>

<h2>Почему это удачный момент</h2>
<p><a href="/{locale}/services/general-cleaning">Уборка</a> перед переездом создаёт готовую и гигиеничную среду для новых жильцов. Уборка после выезда помогает сдать квартиру в достойном виде собственнику или следующему арендатору. Но если в квартире остались свежие следы ремонта, нужна не обычная уборка при переезде, а уборка после ремонта.</p>

<h2>Что уточнить перед заказом</h2>
<p>Перед заказом стоит уточнить: полностью ли пуста <a href="/{locale}/services/apartment-cleaning">квартира</a>, нужно ли мыть шкафы и технику изнутри, есть ли окна, балкон или сложные пятна. Эти детали заметно меняют объём работы.</p>
HTML,
                    'en' => <<<'HTML'
<p>The biggest advantage of cleaning an empty apartment is that almost every surface is accessible. It's a good moment to tackle the inside of cabinets, corners, doors, tiles, and the areas around appliances.</p>

<h2>Why this is the right moment</h2>
<p><a href="/{locale}/services/general-cleaning">Cleaning</a> before you move in creates a ready, hygienic space for the new residents. Cleaning after you move out helps hand the apartment back in good shape to the owner or the next tenant. But if there are fresh renovation traces in the apartment, what's needed is post-construction cleaning, not a standard move-out clean.</p>

<h2>What to confirm before booking</h2>
<p>Before booking, confirm whether the <a href="/{locale}/services/apartment-cleaning">apartment</a> is completely empty, whether cabinets and appliances need cleaning inside, and whether there are windows, a balcony, or stubborn stains. These details noticeably change the scope of work.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Մաքրում տեղափոխվելուց առաջ Երևանում — Servio',
                    'ru' => 'Уборка квартиры до или после переезда — Servio',
                    'en' => 'Move-In and Move-Out Cleaning in Yerevan — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Դատարկ բնակարանի խորքային մաքրում՝ պահարաններ, խոհանոց, սանհանգույց և մակերեսներ նոր բնակվելու կամ հանձնելու համար։',
                    'ru' => 'Глубокая уборка пустой квартиры: шкафы, кухня, санузел и поверхности перед заселением или сдачей жилья.',
                    'en' => 'A deep clean of an empty apartment — cabinets, kitchen, bathroom and surfaces — before moving in or handing the place over.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Պահարանների ներսը մաքրվո՞ւմ է', 'answer' => 'Այո, եթե դրանք դատարկ են և աշխատանքը ներառված է պատվերում։'],
                        ['question' => 'Պատուհանները մտնո՞ւմ են գնի մեջ', 'answer' => 'Կախված է ընտրված փաթեթից և պետք է նախապես հաստատվի։'],
                        ['question' => 'Ե՞րբ պատվիրել', 'answer' => 'Լավագույնը՝ կահույքը դուրս բերելուց հետո և տեղափոխման իրերը ներս բերելուց առաջ։'],
                    ],
                    'ru' => [
                        ['question' => 'Внутренняя часть шкафов чистится', 'answer' => 'Да, если они пусты и это включено в заказ.'],
                        ['question' => 'Окна входят в стоимость', 'answer' => 'Зависит от выбранного пакета и должно быть согласовано заранее.'],
                        ['question' => 'Когда лучше заказывать', 'answer' => 'Лучше всего — после вывоза мебели и до внесения вещей на новом месте.'],
                    ],
                    'en' => [
                        ['question' => 'Do you clean inside the cabinets', 'answer' => 'Yes, if they\'re empty and it\'s included in the order.'],
                        ['question' => 'Are windows included in the price', 'answer' => 'It depends on the package chosen and should be confirmed in advance.'],
                        ['question' => 'When\'s the best time to book', 'answer' => 'Ideally after the furniture has been moved out and before moving boxes in.'],
                    ],
                ],
                'related_services' => ['general-cleaning', 'apartment-cleaning'],
                'related_post_slugs' => ['bnakarani-maqrum-gin-inch-e-nerarvum-yerevan', 'xohanoci-xorqyayin-maqrum-yerevan'],
            ],
            [
                'slug' => 'tan-maqrum-allergiayi-depqum-yerevan',
                'category' => 'cleaning-tips',
                'tags' => ['տան մաքրում ալերգիայի դեպքում', 'уборка дома при аллергии', 'home cleaning allergy season'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-26'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/allergy-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Ննջասենյակի մանրակրկիտ փոշեկուլում ալերգիայի սեզոնին',
                    'ru' => 'Тщательная уборка пылесосом в спальне в сезон аллергии',
                    'en' => 'Thorough bedroom vacuuming during allergy season',
                ],
                'title' => [
                    'hy' => 'Տան մաքրում ալերգիայի սեզոնին․ գործնական քայլեր',
                    'ru' => 'Уборка дома в сезон аллергии: практические советы',
                    'en' => 'Home Cleaning During Allergy Season: Practical Steps',
                ],
                'excerpt' => [
                    'hy' => 'Ինչպես նվազեցնել տան փոշին ու ծաղկափոշին ճիշտ մաքրման հերթականությամբ՝ առանց բժշկական անհիմն խոստումների։',
                    'ru' => 'Как снизить количество пыли и пыльцы в доме с помощью правильной последовательности уборки — без необоснованных медицинских обещаний.',
                    'en' => 'How the right cleaning order can cut down dust and pollen at home — no unfounded medical promises.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Տան մաքրումը չի բուժում ալերգիան, բայց ճիշտ կազմակերպված խնամքը կարող է նվազեցնել ներսում կուտակված փոշու, ծաղկափոշու և կենդանու մազի քանակը։ Կարևոր է աշխատել վերևից ներքև, որպեսզի արդեն մաքրված հատակի վրա նոր փոշի չնստի։</p>

<h2>Որտեղից սկսել</h2>
<p>Սկսեք բարձր մակերեսներից, դարակներից և լուսամուտագոգերից, ապա անցեք տեքստիլին ու հատակին։ Փոշին ավելի լավ է վերցնել թեթև խոնավ միկրոֆիբրով, ոչ թե տարածել չոր փետրախոզանակով։ Հատուկ ուշադրություն են պահանջում <a href="/{locale}/services/mattress-cleaning">ներքնակը</a>, փափուկ կահույքը, <a href="/{locale}/services/carpet-textile-cleaning">գորգը</a>, վարագույրները և մահճակալի տակ եղած հատվածը։</p>

<h2>Օդափոխություն և խորը մաքրում</h2>
<p>Օդափոխության ժամանակը նպատակահարմար է ընտրել ըստ արտաքին պայմանների։ <a href="/{locale}/services/general-cleaning">Խորը մաքրումից</a> հետո ևս անհրաժեշտ է վերահսկել խոնավությունը և ամբողջությամբ չորացնել տեքստիլը։ Եթե ընտանիքի անդամն ունի ծանր ախտանիշներ, մաքրման ծրագիրը պետք է քննարկել բժշկի հետ և չփոխարինել բուժումը կենցաղային ծառայությամբ։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Уборка дома не лечит аллергию, но правильно организованный уход может снизить количество накопившейся внутри пыли, пыльцы и шерсти животных. Важно работать сверху вниз, чтобы новая пыль не оседала на уже убранный пол.</p>

<h2>С чего начать</h2>
<p>Начните с высоких поверхностей, полок и подоконников, затем переходите к текстилю и полу. Пыль лучше собирать слегка влажной микрофиброй, а не разносить сухой метёлкой из перьев. Особого внимания требуют <a href="/{locale}/services/mattress-cleaning">матрас</a>, мягкая мебель, <a href="/{locale}/services/carpet-textile-cleaning">ковёр</a>, шторы и пространство под кроватью.</p>

<h2>Проветривание и глубокая уборка</h2>
<p>Время проветривания стоит выбирать исходя из погодных условий на улице. После <a href="/{locale}/services/general-cleaning">генеральной уборки</a> также важно контролировать влажность и полностью просушивать текстиль. Если у кого-то из членов семьи серьёзные симптомы, план уборки нужно обсудить с врачом и не заменять лечение бытовой услугой.</p>
HTML,
                    'en' => <<<'HTML'
<p>Cleaning a home doesn't cure an allergy, but organizing it the right way can reduce the dust, pollen and pet hair that build up indoors. It's worth working top to bottom, so fresh dust doesn't resettle on a floor that's already been cleaned.</p>

<h2>Where to start</h2>
<p>Start with high surfaces, shelves and windowsills, then move on to textiles and the floor. Dust lifts better with a lightly damp microfiber cloth than a dry feather duster, which just spreads it around. The <a href="/{locale}/services/mattress-cleaning">mattress</a>, upholstered furniture, <a href="/{locale}/services/carpet-textile-cleaning">carpet</a>, curtains and the space under the bed all need extra attention.</p>

<h2>Ventilation and deep cleaning</h2>
<p>It's worth timing ventilation around outdoor conditions. After a <a href="/{locale}/services/general-cleaning">deep clean</a>, it also matters to keep an eye on humidity and dry textiles completely. If someone in the household has severe symptoms, the cleaning plan should be discussed with a doctor — a household service is not a substitute for treatment.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Տան մաքրում ալերգիայի սեզոնին․ գործնական քայլեր — Servio',
                    'ru' => 'Уборка дома в сезон аллергии: практические советы — Servio',
                    'en' => 'Home Cleaning During Allergy Season — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես նվազեցնել տան փոշին ու ծաղկափոշին ճիշտ մաքրման հերթականությամբ՝ առանց բժշկական անհիմն խոստումների։',
                    'ru' => 'Как снизить количество пыли и пыльцы в доме с помощью правильной последовательности уборки — без необоснованных медицинских обещаний.',
                    'en' => 'How the right cleaning order can cut down dust and pollen at home — no unfounded medical promises.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Քիմմաքրումը բուժո՞ւմ է ալերգիան', 'answer' => 'Ոչ, այն մաքրման ծառայություն է և բժշկական բուժում չէ։'],
                        ['question' => 'Ո՞ր հատվածներն են արագ կուտակում փոշի', 'answer' => 'Տեքստիլը, գորգերը, ներքնակը, փափուկ կահույքը և դժվարամատչելի անկյունները։'],
                        ['question' => 'Որքա՞ն հաճախ մաքրել', 'answer' => 'Հաճախականությունը կախված է բնակիչներից, կենդանիներից և արտաքին փոշուց։'],
                    ],
                    'ru' => [
                        ['question' => 'Химчистка лечит аллергию', 'answer' => 'Нет, это клининговая услуга, а не медицинское лечение.'],
                        ['question' => 'Какие места быстрее всего накапливают пыль', 'answer' => 'Текстиль, ковры, матрас, мягкая мебель и труднодоступные углы.'],
                        ['question' => 'Как часто убирать', 'answer' => 'Периодичность зависит от жильцов, животных и уличной пыли.'],
                    ],
                    'en' => [
                        ['question' => 'Does dry cleaning treat allergies', 'answer' => 'No — it\'s a cleaning service, not a medical treatment.'],
                        ['question' => 'Which spots build up dust fastest', 'answer' => 'Textiles, carpets, the mattress, upholstered furniture and hard-to-reach corners.'],
                        ['question' => 'How often should I clean', 'answer' => 'It depends on the household, pets, and outdoor dust levels.'],
                    ],
                ],
                'related_services' => ['general-cleaning', 'mattress-cleaning', 'carpet-textile-cleaning'],
                'related_post_slugs' => ['nerqnaki-qimmaqrum-gin-yerevan', 'gorgeri-qimmaqrum-gin-tany-yerevan', 'garnanayin-maqrum-yerevan'],
            ],
            [
                'slug' => 'bazmoci-qimmaqrum-kendanu-mazi-ev-hoti-dem',
                'category' => 'upholstery-care',
                'tags' => ['բազմոցից կենդանու հոտի հեռացում', 'запах животных с дивана', 'pet odor sofa cleaning'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-22'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/pet-hair-odor-cleaning.png',
                'featured_image_alt' => [
                    'hy' => 'Կենդանու մազից և հոտից բազմոցի մասնագիտական քիմմաքրում',
                    'ru' => 'Профессиональная химчистка дивана от шерсти и запаха животных',
                    'en' => 'Professional sofa cleaning to remove pet hair and odor',
                ],
                'title' => [
                    'hy' => 'Ինչպես հեռացնել կենդանու մազն ու հոտը բազմոցից',
                    'ru' => 'Как удалить шерсть и запах животных с дивана',
                    'en' => 'How to Remove Pet Hair and Odor from a Sofa',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է կենդանու հոտը վերադառնում, ինչպես է կատարվում բազմոցի խորը մաքրումը և երբ է անհրաժեշտ մասնագետ։',
                    'ru' => 'Почему запах животных возвращается, как проходит глубокая чистка дивана и когда нужен специалист.',
                    'en' => 'Why pet odor keeps coming back, how deep sofa cleaning actually works, and when it\'s time to call a professional.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Կենդանու մազը հաճախ մնում է գործվածքի հյուսվածքում, իսկ հոտի աղբյուրը կարող է ներթափանցել նստատեղի խորքային շերտեր։ Մակերեսային բուրավետիչը միայն ժամանակավորապես ծածկում է խնդիրը և երբեմն խառնվում է հոտին։</p>

<h2>Ինչպես է ընթանում մաքրումը</h2>
<p>Աշխատանքը սկսվում է մանրակրկիտ չոր մաքրումից՝ մազն ու փոշին հնարավորինս հեռացնելու համար։ Հետո որոշվում է հոտի կամ բծի աղբյուրը և կատարվում է տեղային փորձարկում։ Լուծույթը պետք է համապատասխանի գործվածքին, իսկ խոնավությունը՝ վերահսկվի, որպեսզի լցանյութը չափազանց չթրջվի։ <a href="/{locale}/services/sofa-cleaning">Էքստրակցիոն մաքրումը</a> հեռացնում է լուծված աղտոտման զգալի մասը, բայց շատ հին կամ խոր ներծծված հոտի դեպքում արդյունքը կախված է կահույքի կառուցվածքից։</p>

<h2>Հետո և հետագա խնամք</h2>
<p>Մաքրումից հետո բազմոցը պետք է լիովին չորանա և լավ օդափոխվի։ Հետագայում օգտակար են լվացվող ծածկոցները, կանոնավոր փոշեկուլումը և նոր բծի արագ, անվտանգ մշակումը՝ առանց ուժեղ քսելու։ Աթոռների և բազկաթոռների համար կիրառվում է <a href="/{locale}/services/armchair-chair-cleaning">նույն սկզբունքով մաքրում</a>։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Шерсть животных часто остаётся в структуре ткани, а источник запаха может проникать в глубокие слои сиденья. Поверхностный ароматизатор лишь временно маскирует проблему и иногда смешивается с запахом.</p>

<h2>Как проходит чистка</h2>
<p>Работа начинается с тщательной сухой чистки — нужно максимально убрать шерсть и пыль. Затем определяется источник запаха или пятна и проводится локальный тест. Раствор должен подходить ткани, а влажность — контролироваться, чтобы наполнитель не переувлажнялся. <a href="/{locale}/services/sofa-cleaning">Экстракционная чистка</a> удаляет значительную часть растворённого загрязнения, но при очень старом или глубоко въевшемся запахе результат зависит от конструкции мебели.</p>

<h2>После чистки и дальнейший уход</h2>
<p>После чистки диван должен полностью высохнуть и хорошо проветриться. В дальнейшем полезны съёмные стирающиеся чехлы, регулярный пылесос и быстрая, аккуратная обработка нового пятна без сильного трения. Для стульев и кресел применяется <a href="/{locale}/services/armchair-chair-cleaning">такой же принцип чистки</a>.</p>
HTML,
                    'en' => <<<'HTML'
<p>Pet hair often works its way into the fabric's weave, and the source of an odor can sink into the deeper layers of the seat cushion. A surface spray only masks the problem temporarily and sometimes just blends with the smell.</p>

<h2>How the cleaning works</h2>
<p>Work starts with a thorough dry pass — pulling out as much hair and dust as possible. Then the source of the odor or stain is identified and a spot test is run. The solution has to match the fabric, and moisture needs to be controlled so the filling doesn't get oversaturated. <a href="/{locale}/services/sofa-cleaning">Extraction cleaning</a> removes a good share of the dissolved grime, but for a very old or deeply set odor, the outcome depends on how the piece of furniture is built.</p>

<h2>Afterward and ongoing care</h2>
<p>After cleaning, the sofa needs to dry completely with good airflow. Going forward, washable removable covers, regular vacuuming, and treating a fresh stain quickly and gently — without hard scrubbing — all help. Chairs and armchairs get <a href="/{locale}/services/armchair-chair-cleaning">the same treatment approach</a>.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Ինչպես հեռացնել կենդանու մազն ու հոտը բազմոցից — Servio',
                    'ru' => 'Как удалить шерсть и запах животных с дивана — Servio',
                    'en' => 'How to Remove Pet Hair and Odor from a Sofa — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է կենդանու հոտը վերադառնում, ինչպես է կատարվում բազմոցի խորը մաքրումը և երբ է անհրաժեշտ մասնագետ։',
                    'ru' => 'Почему запах животных возвращается, как проходит глубокая чистка дивана и когда нужен специалист.',
                    'en' => 'Why pet odor keeps coming back, how deep sofa cleaning actually works, and when it\'s time to call a professional.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Հոտը 100% կվերանա՞', 'answer' => 'Դա կարելի է գնահատել միայն աղբյուրը և ներծծման խորությունը տեսնելուց հետո։'],
                        ['question' => 'Կարելի՞ է անմիջապես նստել', 'answer' => 'Ոչ, սպասեք մինչև լիարժեք չորացումը։'],
                        ['question' => 'Բոլոր գործվածքներն են նույն ձևով մաքրվո՞ւմ', 'answer' => 'Ոչ, նախ անհրաժեշտ է նյութի ստուգում։'],
                    ],
                    'ru' => [
                        ['question' => 'Запах уйдёт на 100%', 'answer' => 'Это можно оценить только после осмотра источника и глубины впитывания.'],
                        ['question' => 'Можно сразу сесть на диван', 'answer' => 'Нет, подождите полного высыхания.'],
                        ['question' => 'Все ткани чистятся одинаково', 'answer' => 'Нет, сначала нужна проверка материала.'],
                    ],
                    'en' => [
                        ['question' => 'Will the odor be gone 100%', 'answer' => 'That can only be assessed after seeing the source and how deep it\'s soaked in.'],
                        ['question' => 'Can I sit on it right away', 'answer' => 'No — wait until it\'s completely dry.'],
                        ['question' => 'Is every fabric cleaned the same way', 'answer' => 'No, the material needs to be checked first.'],
                    ],
                ],
                'related_services' => ['sofa-cleaning', 'armchair-chair-cleaning'],
                'related_post_slugs' => ['gorgeri-qimmaqrum-gin-tany-yerevan', 'nerqnaki-qimmaqrum-gin-yerevan'],
            ],
            [
                'slug' => 'garnanayin-maqrum-yerevan',
                'category' => 'cleaning-tips',
                'tags' => ['գարնանային մաքրում', 'генеральная весенняя уборка', 'spring deep cleaning'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-25'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/spring-cleaning-yerevan.png',
                'featured_image_alt' => [
                    'hy' => 'Լուսավոր տան գարնանային ընդհանուր մաքրում Երևանում',
                    'ru' => 'Весенняя генеральная уборка светлого дома в Ереване',
                    'en' => 'Bright home getting a spring deep clean in Yerevan',
                ],
                'title' => [
                    'hy' => 'Գարնանային ընդհանուր մաքրում. ամբողջական ցանկ',
                    'ru' => 'Генеральная весенняя уборка: полный чек-лист',
                    'en' => 'Spring Deep Cleaning: Complete Checklist',
                ],
                'excerpt' => [
                    'hy' => 'Գարնանային մաքրման հերթականությունը՝ պատուհաններից ու բարձր մակերեսներից մինչև խոհանոց, սանհանգույց և տեքստիլ։',
                    'ru' => 'Порядок весенней уборки: от окон и высоких поверхностей до кухни, ванной и текстиля.',
                    'en' => 'The right order for spring cleaning: from windows and high surfaces to the kitchen, bathroom and textiles.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Գարունը հարմար ժամանակ է ձմռանից հետո կուտակված փոշին հեռացնելու, <a href="/{locale}/blog/patuhanneri-lvacum-gin-yerevan">պատուհանները լվանալու</a> և այն հատվածներին հասնելու համար, որոնք ամենօրյա մաքրման ժամանակ բաց են մնում։ Լավ պլանը խնայում է ժամանակ և թույլ չի տալիս նույն տեղը երկու անգամ մաքրել։</p>

<h2>Ինչ հերթականությամբ աշխատել</h2>
<p>Սկզբում դասավորեք իրերը և ազատեք մակերեսները։ Այնուհետև աշխատեք վերևից ներքև՝ պահարանների գագաթներ, լուսամփոփներ, դարակներ, դռներ ու պատուհաններ։ Դրանից հետո անցեք խոհանոցի ճարպային հետքերին, սանհանգույցի նստվածքին, կահույքի արտաքին մասերին և վերջում՝ հատակին։ <a href="/{locale}/services/carpet-textile-cleaning">Տեքստիլի քիմմաքրումը</a> պլանավորեք այնպես, որ այն հասցնի ամբողջությամբ չորանալ։</p>

<h2>Ինչի ուշադիր լինել</h2>
<p>Մի խառնեք տարբեր քիմիական միջոցներ։ Բնական քարը, փայտը, փայլուն ճակատը և նուրբ գործվածքը պահանջում են տարբեր մոտեցումներ։ Եթե աշխատանքների ցանկը մեծ է, մասնագիտական <a href="/{locale}/services/general-cleaning">ընդհանուր մաքրումը</a> թույլ է տալիս մեկ այցով կազմակերպել հիմնական խորքային աշխատանքը, իսկ հետո պահպանել արդյունքը կանոնավոր մաքրմամբ։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Весна — удачное время, чтобы убрать пыль, накопившуюся за зиму, <a href="/{locale}/blog/patuhanneri-lvacum-gin-yerevan">помыть окна</a> и добраться до участков, которые остаются вне ежедневной уборки. Хороший план экономит время и не даёт убирать одно и то же место дважды.</p>

<h2>В каком порядке действовать</h2>
<p>Сначала разберите вещи и освободите поверхности. Затем работайте сверху вниз: верх шкафов, светильники, полки, двери и окна. После этого переходите к жирным следам на кухне, налёту в ванной, внешним частям мебели и в последнюю очередь — к полу. <a href="/{locale}/services/carpet-textile-cleaning">Химчистку текстиля</a> планируйте так, чтобы он успел полностью высохнуть.</p>

<h2>На что обратить внимание</h2>
<p>Не смешивайте разные химические средства. Натуральный камень, дерево, глянцевый фасад и деликатная ткань требуют разного подхода. Если список работ большой, профессиональная <a href="/{locale}/services/general-cleaning">генеральная уборка</a> позволяет за один визит выполнить основную глубокую работу, а затем поддерживать результат регулярной уборкой.</p>
HTML,
                    'en' => <<<'HTML'
<p>Spring is a good time to clear out dust that built up over winter, <a href="/{locale}/blog/patuhanneri-lvacum-gin-yerevan">wash the windows</a>, and get to the spots everyday cleaning skips. A good plan saves time and stops you from cleaning the same spot twice.</p>

<h2>What order to work in</h2>
<p>Start by tidying belongings and clearing surfaces. Then work top to bottom — the top of cabinets, light fixtures, shelves, doors and windows. After that, move on to kitchen grease, bathroom limescale, the outside of furniture, and finish with the floor. Plan <a href="/{locale}/services/carpet-textile-cleaning">textile dry cleaning</a> so it has time to dry completely.</p>

<h2>What to watch for</h2>
<p>Don't mix different chemical products. Natural stone, wood, glossy fronts and delicate fabric all need a different approach. If the task list is long, a professional <a href="/{locale}/services/general-cleaning">general clean</a> handles the core deep work in one visit, and regular cleaning maintains the result afterward.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Գարնանային ընդհանուր մաքրում. ամբողջական ցանկ — Servio',
                    'ru' => 'Генеральная весенняя уборка: полный чек-лист — Servio',
                    'en' => 'Spring Deep Cleaning: Complete Checklist — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Գարնանային մաքրման հերթականությունը՝ պատուհաններից ու բարձր մակերեսներից մինչև խոհանոց, սանհանգույց և տեքստիլ։',
                    'ru' => 'Порядок весенней уборки: от окон и высоких поверхностей до кухни, ванной и текстиля.',
                    'en' => 'The right order for spring cleaning: from windows and high surfaces to the kitchen, bathroom and textiles.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Որտեղի՞ց սկսել', 'answer' => 'Իրերի դասավորումից և բարձր մակերեսներից։'],
                        ['question' => 'Պատուհանները ե՞րբ լվանալ', 'answer' => 'Փոշոտ բարձր հատվածներից հետո, բայց հատակի վերջնական լվացումից առաջ։'],
                        ['question' => 'Կարելի՞ է միաժամանակ պատվիրել քիմմաքրում', 'answer' => 'Այո, եթե ժամանակացույցում նախատեսվում է բավարար չորացում։'],
                    ],
                    'ru' => [
                        ['question' => 'С чего начать', 'answer' => 'С разбора вещей и высоких поверхностей.'],
                        ['question' => 'Когда мыть окна', 'answer' => 'После пыльных высоких участков, но до финальной мойки пола.'],
                        ['question' => 'Можно ли заказать химчистку одновременно', 'answer' => 'Да, если в графике заложено достаточное время на сушку.'],
                    ],
                    'en' => [
                        ['question' => 'Where should I start', 'answer' => 'With tidying belongings and the high surfaces.'],
                        ['question' => 'When should windows be washed', 'answer' => 'After the dusty high spots, but before the final floor wash.'],
                        ['question' => 'Can dry cleaning be booked at the same time', 'answer' => 'Yes, as long as the schedule allows enough time to dry.'],
                    ],
                ],
                'related_services' => ['general-cleaning', 'carpet-textile-cleaning'],
                'related_post_slugs' => ['patuhanneri-lvacum-gin-yerevan', 'xohanoci-xorqyayin-maqrum-yerevan', 'sanhanguyci-xorqyayin-maqrum-yerevan'],
            ],
            [
                'slug' => 'xohanoci-xorqyayin-maqrum-yerevan',
                'category' => 'cleaning-tips',
                'tags' => ['խոհանոցի խորը մաքրում', 'глубокая уборка кухни', 'kitchen deep cleaning'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-19'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/kitchen-deep-cleaning.png',
                'featured_image_alt' => [
                    'hy' => 'Խոհանոցի գազօջախի և մետաղական մակերեսի խորը մաքրում',
                    'ru' => 'Глубокая чистка кухонной плиты и металлической поверхности',
                    'en' => 'Deep cleaning a kitchen stove and metal surface',
                ],
                'title' => [
                    'hy' => 'Խոհանոցի խորը մաքրում. ճարպ, տեխնիկա և մակերեսներ',
                    'ru' => 'Глубокая уборка кухни: жир, техника и поверхности',
                    'en' => 'Kitchen Deep Cleaning Guide: Grease, Appliances and Surfaces',
                ],
                'excerpt' => [
                    'hy' => 'Ինչպես անվտանգ մաքրել խոհանոցի ճարպը, գազօջախը, աշխատանքային մակերեսները և պահարանները՝ առանց վնասելու նյութերը։',
                    'ru' => 'Как безопасно очистить кухонный жир, плиту, рабочие поверхности и шкафы, не повредив материалы.',
                    'en' => 'How to safely clean kitchen grease, the stove, worktops and cabinets without damaging the materials.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Խոհանոցում աղտոտումը տարբեր բնույթ ունի՝ ճարպ, սննդի մնացորդ, կրային նստվածք և սովորական փոշի։ Դրա համար մեկ ուժեղ միջոցը բոլոր մակերեսների վրա օգտագործելը սխալ է։ Չժանգոտվող պողպատը, բնական քարը, ներկված ճակատը և ապակին տարբեր խնամք են պահանջում։</p>

<h2>Ինչ է ներառում խորը մաքրումը</h2>
<p><a href="/{locale}/services/general-cleaning">Խորը մաքրման</a> ժամանակ սովորաբար մշակվում են աշխատանքային մակերեսները, գազօջախը, արտաքին տեխնիկան, լվացարանը, backsplash-ը, պահարանների արտաքին մասերը և հասանելի դժվարամատչելի հատվածները։ Ջեռոցի, սառնարանի կամ պահարանների ներսը պետք է առանձին նշել, քանի որ դրանք ժամանակատար աշխատանքներ են և պահանջում են դատարկում։</p>

<h2>Ինչպես աշխատել ճարպի հետ</h2>
<p>Ճարպային միջոցին պետք է տալ աշխատելու ժամանակ, այլ ոչ թե անմիջապես ուժով քսել։ Նախ կատարվում է փոքր հատվածի փորձարկում, հետո մակերեսը մշակվում և մանրակրկիտ հեռացվում են նյութի մնացորդները։ Սննդի հետ շփվող հատվածները վերջում պետք է մնան մաքուր և առանց ավելորդ միջոցի։ Խոհանոցի ամբողջական խնամքի համար այս ծառայությունը հաճախ պատվիրվում է <a href="/{locale}/services/apartment-cleaning">բնակարանի ընդհանուր մաքրման</a> հետ մեկտեղ։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Загрязнения на кухне бывают разного характера: жир, остатки еды, известковый налёт и обычная пыль. Поэтому использовать одно сильное средство на всех поверхностях — ошибка. Нержавеющая сталь, натуральный камень, окрашенный фасад и стекло требуют разного ухода.</p>

<h2>Что входит в глубокую уборку</h2>
<p>При <a href="/{locale}/services/general-cleaning">глубокой уборке</a> обычно обрабатываются рабочие поверхности, плита, внешняя часть техники, раковина, фартук, фасады шкафов и доступные труднодоступные участки. Духовку, холодильник или внутреннюю часть шкафов нужно указывать отдельно — это трудоёмкая работа, требующая освобождения пространства.</p>

<h2>Как работать с жиром</h2>
<p>Обезжиривающему средству нужно дать время подействовать, а не сразу тереть с силой. Сначала проводится тест на небольшом участке, затем обрабатывается поверхность и тщательно удаляются остатки средства. Участки, контактирующие с едой, в конце должны остаться чистыми и без лишнего средства. Для комплексного ухода за кухней эту услугу часто заказывают вместе с <a href="/{locale}/services/apartment-cleaning">генеральной уборкой квартиры</a>.</p>
HTML,
                    'en' => <<<'HTML'
<p>Kitchen grime comes in different forms — grease, food residue, limescale and ordinary dust. That's why using one strong product on every surface is a mistake. Stainless steel, natural stone, painted fronts and glass all need different care.</p>

<h2>What deep cleaning covers</h2>
<p>A <a href="/{locale}/services/general-cleaning">deep clean</a> typically covers worktops, the stove, the outside of appliances, the sink, the backsplash, cabinet fronts, and any accessible hard-to-reach spots. The oven, fridge, or the inside of cabinets need to be flagged separately — they're time-consuming and require clearing the space out first.</p>

<h2>How to handle grease</h2>
<p>A degreasing product needs time to work — not an immediate hard scrub. A small test spot comes first, then the surface is treated and any product residue is thoroughly removed. Surfaces that touch food need to end up clean with no leftover product on them. For a fuller kitchen refresh, this service is often booked alongside a full <a href="/{locale}/services/apartment-cleaning">apartment clean</a>.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Խոհանոցի խորը մաքրում. ճարպ, տեխնիկա և մակերեսներ — Servio',
                    'ru' => 'Глубокая уборка кухни: жир, техника и поверхности — Servio',
                    'en' => 'Kitchen Deep Cleaning Guide — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչպես անվտանգ մաքրել խոհանոցի ճարպը, գազօջախը, աշխատանքային մակերեսները և պահարանները՝ առանց վնասելու նյութերը։',
                    'ru' => 'Как безопасно очистить кухонный жир, плиту, рабочие поверхности и шкафы, не повредив материалы.',
                    'en' => 'How to safely clean kitchen grease, the stove, worktops and cabinets without damaging the materials.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Ջեռոցի ներսը ներառվա՞ծ է', 'answer' => 'Միայն եթե այն նշված է պատվերի ծավալում։'],
                        ['question' => 'Պահարանները ներսից մաքրվո՞ւմ են', 'answer' => 'Այո, երբ դրանք դատարկ են և ծառայությունը նախապես պատվիրված է։'],
                        ['question' => 'Կարելի՞ է հեռացնել հին ճարպը', 'answer' => 'Շատ դեպքերում՝ զգալիորեն, սակայն արդյունքը կախված է մակերեսից և աղտոտման հնությունից։'],
                    ],
                    'ru' => [
                        ['question' => 'Внутренняя часть духовки входит в услугу', 'answer' => 'Только если это указано в объёме заказа.'],
                        ['question' => 'Шкафы чистятся изнутри', 'answer' => 'Да, когда они пусты и услуга заказана заранее.'],
                        ['question' => 'Можно ли убрать старый жир', 'answer' => 'В большинстве случаев — значительно, но результат зависит от поверхности и «возраста» загрязнения.'],
                    ],
                    'en' => [
                        ['question' => 'Is the inside of the oven included', 'answer' => 'Only if it\'s specified in the order\'s scope.'],
                        ['question' => 'Are cabinets cleaned inside', 'answer' => 'Yes, when they\'re empty and it\'s booked in advance.'],
                        ['question' => 'Can old grease be removed', 'answer' => 'In most cases, significantly — but the result depends on the surface and how long it\'s been there.'],
                    ],
                ],
                'related_services' => ['general-cleaning', 'apartment-cleaning'],
                'related_post_slugs' => ['sanhanguyci-xorqyayin-maqrum-yerevan', 'bnakarani-maqrum-gin-inch-e-nerarvum-yerevan'],
            ],
            [
                'slug' => 'sanhanguyci-xorqyayin-maqrum-yerevan',
                'category' => 'cleaning-tips',
                'tags' => ['սանհանգույցի խորը մաքրում', 'глубокая уборка ванной', 'bathroom deep cleaning'],
                'status' => 'published',
                'published_at' => Carbon::parse('2026-08-21'),
                'author_name' => 'Servio',
                'featured_image_path' => '/images/blog/bathroom-deep-cleaning.png',
                'featured_image_alt' => [
                    'hy' => 'Ժամանակակից սանհանգույցի գոլորշային խորը մաքրում',
                    'ru' => 'Глубокая уборка современной ванной с использованием пара',
                    'en' => 'Steam-assisted deep cleaning of a modern bathroom',
                ],
                'title' => [
                    'hy' => 'Սանհանգույցի խորը մաքրում. նստվածք և սալիկներ',
                    'ru' => 'Глубокая уборка ванной: налёт, плитка и стекло',
                    'en' => 'Bathroom Deep Cleaning Guide: Limescale, Tile and Glass',
                ],
                'excerpt' => [
                    'hy' => 'Սանհանգույցի անվտանգ խորքային մաքրում՝ ապակի, սալիկ, կարեր, ծորակներ և կրային նստվածք։',
                    'ru' => 'Безопасная глубокая уборка ванной: стекло, плитка, швы, смесители и известковый налёт.',
                    'en' => 'Safe deep bathroom cleaning: glass, tile, grout, faucets and limescale.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Սանհանգույցում հաճախ հանդիպում են կրային նստվածք, օճառի հետքեր, խոնավության բծեր և կեղտ սալիկների կարերում։ Դրանցից յուրաքանչյուրը պահանջում է համապատասխան միջոց և ճիշտ ազդեցության ժամանակ։ Շատ թթվային նյութը կարող է վնասել բնական քարը, մետաղական ծածկույթը կամ կարերը։</p>

<h2>Ինչպես է ընթանում մաքրումը</h2>
<p>Աշխատանքը սկսվում է մակերեսների գնահատումից և չոր փոշու հեռացումից։ Այնուհետև առանձին մշակվում են ծորակները, ապակին, սալիկը, լոգարանը կամ ցնցուղը և հասանելի կարերը։ Միջոցները խառնել չի կարելի, հատկապես քլոր պարունակող և թթվային նյութերը։ Անհրաժեշտ է օդափոխություն, ձեռնոցներ և մակերեսի մանրակրկիտ ողողում։</p>

<h2>Երբ է օգտակար գոլորշին</h2>
<p>Գոլորշին կարող է օգտակար լինել որոշ ջերմակայուն մակերեսների և դժվարամատչելի հատվածների համար, բայց այն նույնպես համընդհանուր լուծում չէ։ Servio-ի <a href="/{locale}/services/steam-cleaning-disinfection">գոլորշային ախտահանման</a> ծառայությունը կիրառվում է միայն մակերեսի ջերմակայունությունը ստուգելուց հետո։ Վնասված սիլիկոնը կամ խոր բորբոսը երբեմն պահանջում են ոչ թե մաքրում, այլ նյութի փոխարինում և խոնավության պատճառի վերացում։</p>
HTML,
                    'ru' => <<<'HTML'
<p>В ванной часто встречаются известковый налёт, следы мыла, пятна от влажности и загрязнения в швах между плиткой. Каждое из них требует своего средства и правильного времени воздействия. Слишком агрессивное кислотное средство может повредить натуральный камень, металлическое покрытие или швы.</p>

<h2>Как проходит уборка</h2>
<p>Работа начинается с оценки поверхностей и удаления сухой пыли. Затем отдельно обрабатываются смесители, стекло, плитка, ванна или душ и доступные швы. Средства нельзя смешивать, особенно хлорсодержащие и кислотные. Нужны проветривание, перчатки и тщательное ополаскивание поверхности.</p>

<h2>Когда полезен пар</h2>
<p>Пар может быть полезен для некоторых термостойких поверхностей и труднодоступных участков, но и он не универсальное решение. Услуга <a href="/{locale}/services/steam-cleaning-disinfection">паровой дезинфекции</a> от Servio применяется только после проверки термостойкости поверхности. Повреждённый силикон или глубокая плесень иногда требуют не чистки, а замены материала и устранения причины влажности.</p>
HTML,
                    'en' => <<<'HTML'
<p>Bathrooms commonly deal with limescale, soap residue, moisture stains, and grime in the grout between tiles. Each needs its own product and the right amount of contact time. A product that's too acidic can damage natural stone, metal fittings, or the grout itself.</p>

<h2>How the cleaning works</h2>
<p>Work starts with assessing the surfaces and clearing away dry dust. Faucets, glass, tile, the tub or shower, and any accessible grout are then treated separately. Products should never be mixed, especially chlorine-based and acidic ones. Ventilation, gloves, and a thorough rinse of the surface are all needed.</p>

<h2>When steam helps</h2>
<p>Steam can help on some heat-resistant surfaces and hard-to-reach spots, but it isn't a universal fix either. Servio's <a href="/{locale}/services/steam-cleaning-disinfection">steam disinfection</a> service is only used after checking a surface's heat resistance. Damaged silicone or deep-set mold sometimes calls for replacing the material and fixing the moisture source, not more cleaning.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Սանհանգույցի խորը մաքրում. նստվածք և սալիկներ — Servio',
                    'ru' => 'Глубокая уборка ванной: налёт, плитка и стекло — Servio',
                    'en' => 'Bathroom Deep Cleaning Guide — Servio',
                ],
                'seo_description' => [
                    'hy' => 'Սանհանգույցի անվտանգ խորքային մաքրում՝ ապակի, սալիկ, կարեր, ծորակներ և կրային նստվածք։',
                    'ru' => 'Безопасная глубокая уборка ванной: стекло, плитка, швы, смесители и известковый налёт.',
                    'en' => 'Safe deep bathroom cleaning: glass, tile, grout, faucets and limescale.',
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Բորբոսը միշտ հեռացվո՞ւմ է', 'answer' => 'Մակերեսային հետքը կարող է մաքրվել, բայց խոր վնասման դեպքում պետք է վերացնել խոնավության պատճառը կամ փոխել նյութը։'],
                        ['question' => 'Գոլորշին հարմար է ամեն ինչի՞ համար', 'answer' => 'Ոչ, նախ ստուգվում է նյութի ջերմակայունությունը։'],
                        ['question' => 'Ինչպե՞ս նվազեցնել նստվածքը', 'answer' => 'Ջուրը պարբերաբար հեռացնել ապակուց և մակերեսները պահել չոր ու օդափոխված։'],
                    ],
                    'ru' => [
                        ['question' => 'Плесень удаляется всегда', 'answer' => 'Поверхностный след можно очистить, но при глубоком повреждении нужно устранить причину влажности или заменить материал.'],
                        ['question' => 'Пар подходит для всего', 'answer' => 'Нет, сначала проверяется термостойкость материала.'],
                        ['question' => 'Как снизить налёт', 'answer' => 'Регулярно вытирать воду со стекла и держать поверхности сухими и проветриваемыми.'],
                    ],
                    'en' => [
                        ['question' => 'Can mold always be removed', 'answer' => 'A surface trace can be cleaned, but deep damage means fixing the moisture source or replacing the material.'],
                        ['question' => 'Is steam suitable for everything', 'answer' => 'No — the material\'s heat resistance is checked first.'],
                        ['question' => 'How can I reduce limescale buildup', 'answer' => 'Wipe water off the glass regularly and keep surfaces dry and ventilated.'],
                    ],
                ],
                'related_services' => ['general-cleaning', 'steam-cleaning-disinfection'],
                'related_post_slugs' => ['xohanoci-xorqyayin-maqrum-yerevan', 'garnanayin-maqrum-yerevan'],
            ],
        ];

        foreach ($posts as $post) {
            $serviceSlugs = $post['related_services'];
            unset($post['related_services']);

            $record = Post::query()->updateOrCreate(['slug' => $post['slug']], $post);

            $serviceIds = Service::query()->whereIn('slug', $serviceSlugs)->pluck('id');
            $record->services()->sync($serviceIds);
        }

        foreach ($preservedUpdatedAt as $slug => $updatedAt) {
            Post::query()->where('slug', $slug)->toBase()->update(['updated_at' => $updatedAt]);
        }
    }
}

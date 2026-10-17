<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $cleaning = ServiceCategory::query()->where('slug', 'cleaning')->firstOrFail();
        $upholstery = ServiceCategory::query()->where('slug', 'upholstery-care')->firstOrFail();

        $services = [
            [
                'category_id' => $cleaning->id,
                'slug' => 'apartment-cleaning',
                'icon' => 'home',
                'sort_order' => 1,
                'name' => [
                    'hy' => 'Բնակարանների մաքրում Երևանում',
                    'ru' => 'Профессиональная уборка квартир в Ереване',
                    'en' => 'Professional Apartment Cleaning in Yerevan',
                ],
                'short_description' => [
                    'hy' => 'Կանոնավոր և խորը մաքրում ձեր բնակարանի համար՝ խոհանոցից մինչև լոգարան։',
                    'ru' => 'Регулярная и генеральная уборка квартиры — от кухни до ванной.',
                    'en' => 'Regular and deep cleaning for your apartment — from kitchen to bathroom.',
                ],
                'description' => [
                    'hy' => 'Մենք մաքրում ենք ձեր բնակարանը՝ ուշադրություն դարձնելով ամեն անկյունի։ Օգտագործում ենք մասնագիտական սարքավորումներ և անվտանգ մաքրող միջոցներ՝ ապահովելով մաքրություն և հարմարավետություն ձեր տանը։',
                    'ru' => 'Мы убираем вашу квартиру, уделяя внимание каждому уголку. Используем профессиональное оборудование и безопасные чистящие средства, чтобы в доме было чисто и комфортно.',
                    'en' => 'We clean your apartment with attention to every corner, using professional equipment and safe cleaning products so your home stays fresh and comfortable.',
                ],
                'seo_title' => [
                    'hy' => 'Բնակարանների մաքրում Երևանում՝ գներ',
                    'ru' => 'Уборка квартир в Ереване: цены',
                    'en' => 'Apartment Cleaning in Yerevan: Prices',
                ],
                'seo_description' => [
                    'hy' => 'Բնակարանների կանոնավոր, ընդհանուր և հետվերանորոգման մաքրում Երևանում։ Մասնագիտական սարքավորումներ, հստակ գներ և որակի վերահսկում։ Պատվիրեք Servio-ից։',
                    'ru' => 'Регулярная, генеральная и послеремонтная уборка квартир в Ереване. Профессиональное оборудование, понятные цены и контроль качества Servio.',
                    'en' => 'Regular, general and post-renovation apartment cleaning in Yerevan. Professional equipment, clear pricing and quality control from Servio.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է բնակարանի բնակիչների համար, ովքեր ցանկանում են կանոնավոր կամ մեկանգամյա մաքրում՝ ընտրելով պարբերական կամ ընդհանուր մաքրման ձևաչափը հայտ թողնելիս։',
                    'ru' => 'Подходит жильцам квартиры, которым нужна регулярная или разовая уборка — формат (регулярная или генеральная) выбирается при оформлении заявки.',
                    'en' => 'Suited to apartment residents who want either an ongoing or a one-off clean — you choose the regular or general format when booking.',
                ],
                'included_items' => [
                    'hy' => [
                        'Փոշու հեռացում և մակերեսների սրբում բոլոր սենյակներում',
                        'Հատակների փոշեկուլում և խոնավ մաքրում',
                        'Խոհանոցի աշխատանքային մակերեսի, լվացարանի և սալօջախի մաքրում',
                        'Լոգարանի և զուգարանի մաքրում ու ախտահանում',
                        'Հայելիների և ապակյա մակերեսների մաքրում',
                        'Կենցաղային աղբի հավաքում',
                    ],
                    'ru' => [
                        'Удаление пыли и протирание поверхностей во всех комнатах',
                        'Пылесос и влажная уборка полов',
                        'Чистка рабочей поверхности кухни, раковины и плиты',
                        'Уборка и дезинфекция ванной и туалета',
                        'Мытьё зеркал и стеклянных поверхностей',
                        'Сбор бытового мусора',
                    ],
                    'en' => [
                        'Dusting and wiping surfaces in every room',
                        'Vacuuming and mopping floors',
                        'Cleaning the kitchen worktop, sink and stovetop',
                        'Cleaning and disinfecting the bathroom and toilet',
                        'Cleaning mirrors and glass surfaces',
                        'Collecting household waste',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Փափուկ կահույքի և գորգերի քիմմաքրում (պատվիրվում է առանձին)',
                        'Ծանր կահույքի կամ տեխնիկայի տեղափոխում',
                        'Մեծ քանակությամբ սպասքի լվացում, եթե նախապես չի պայմանավորվել',
                        'Պատուհանների արտաքին լվացում առանց անվտանգ հասանելիության',
                    ],
                    'ru' => [
                        'Химчистка мягкой мебели и ковров (заказывается отдельно)',
                        'Перемещение тяжёлой мебели или техники',
                        'Мытьё большого количества посуды, если не оговорено заранее',
                        'Мытьё окон снаружи без безопасного доступа',
                    ],
                    'en' => [
                        'Upholstery and carpet dry cleaning (booked separately)',
                        'Moving heavy furniture or appliances',
                        'Washing a large quantity of dishes unless agreed in advance',
                        'Exterior window washing without safe access',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Հայտում նշվում է տարածքի մակերեսը, սենյակների քանակը և մաքրման տեսակը (պարբերական կամ ընդհանուր)',
                        'Թիմը ժամանում է սեփական սարքավորումներով և մաքրող միջոցներով',
                        'Մաքրումն իրականացվում է սենյակ առ սենյակ՝ ընտրված տեսակի ցանկով',
                        'Աշխատանքի ավարտից հետո կատարվում է համատեղ ստուգում հաճախորդի հետ',
                    ],
                    'ru' => [
                        'В заявке указывается площадь, количество комнат и тип уборки (регулярная или генеральная)',
                        'Команда приезжает со своим оборудованием и чистящими средствами',
                        'Уборка выполняется комната за комнатой по чек-листу выбранного типа',
                        'После завершения — совместная проверка результата с клиентом',
                    ],
                    'en' => [
                        'The request specifies the area, number of rooms and cleaning type (regular or general)',
                        'The team arrives with its own equipment and cleaning products',
                        'Cleaning proceeds room by room following the checklist for the chosen type',
                        'A joint walkthrough with the client confirms the result',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Փոշեկուլներ, միկրոմանրաթելային շորեր և մոպեր, չեզոք pH և մասնագիտացված լոգարանի մաքրող միջոցներ՝ ընտրված մակերեսի տեսակին համապատասխան։',
                    'ru' => 'Пылесосы, микрофибра и швабры, pH-нейтральные и специализированные средства для ванной комнаты, подобранные под тип поверхности.',
                    'en' => 'Vacuum cleaners, microfiber cloths and mops, pH-neutral and bathroom-specific products chosen to match each surface.',
                ],
                'duration_note' => [
                    'hy' => 'Տևողությունը կախված է բնակարանի մակերեսից, սենյակների քանակից և ընտրված մաքրման տեսակից. ճշգրիտ ժամանակը հաստատվում է հայտը դիտարկելուց հետո։',
                    'ru' => 'Продолжительность зависит от площади квартиры, числа комнат и выбранного типа уборки — точное время подтверждается после рассмотрения заявки.',
                    'en' => 'Duration depends on the apartment\'s size, number of rooms and the chosen cleaning type — the exact time is confirmed after reviewing the request.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Տարածքի մակերեսը (քմ)',
                        'Մաքրման տեսակը՝ պարբերական կամ ընդհանուր',
                        'Սանհանգույցների քանակը',
                        'Պատուհանների քանակը',
                        'Ընթացիկ վիճակը և իրերի քանակը',
                    ],
                    'ru' => [
                        'Площадь помещения (м²)',
                        'Тип уборки — регулярная или генеральная',
                        'Количество санузлов',
                        'Количество окон',
                        'Текущее состояние и количество вещей',
                    ],
                    'en' => [
                        'The property\'s area (m²)',
                        'Cleaning type — regular or general',
                        'Number of bathrooms',
                        'Number of windows',
                        'Current condition and amount of clutter',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եմ ընտրել պարբերական կամ ընդհանուր մաքրում նույն բնակարանի համար', 'answer' => 'Այո, ձևաչափն ընտրվում է հայտ թողնելիս, և կարող եք փոխել այն հաջորդ պատվերի ժամանակ։'],
                        ['question' => 'Պե՞տք է տանը լինեմ մաքրման ընթացքում', 'answer' => 'Պարտադիր չէ, եթե հնարավոր է կազմակերպել մուտք դեպի բնակարան, թեև առաջին այցելության համար հարմար է լինել տեղում կարճ ծանոթացման համար։'],
                    ],
                    'ru' => [
                        ['question' => 'Могу ли я выбрать регулярную или генеральную уборку для одной и той же квартиры', 'answer' => 'Да, формат выбирается при оформлении заявки, и вы можете менять его от заказа к заказу.'],
                        ['question' => 'Нужно ли мне быть дома во время уборки', 'answer' => 'Не обязательно, если можно организовать доступ в квартиру, хотя для первого визита полезно быть на месте для короткого знакомства с командой.'],
                    ],
                    'en' => [
                        ['question' => 'Can I choose regular or general cleaning for the same apartment', 'answer' => 'Yes — the format is chosen when booking, and you can switch between them from one order to the next.'],
                        ['question' => 'Do I need to be home during the cleaning', 'answer' => 'Not necessarily, as long as access to the apartment can be arranged, though being there for a short walkthrough helps on the first visit.'],
                    ],
                ],
            ],
            [
                'category_id' => $cleaning->id,
                'slug' => 'house-cleaning',
                'icon' => 'building-2',
                'sort_order' => 2,
                'name' => [
                    'hy' => 'Տների և առանձնատների մաքրում Երևանում',
                    'ru' => 'Профессиональная уборка домов в Ереване',
                    'en' => 'Professional House Cleaning in Yerevan',
                ],
                'short_description' => [
                    'hy' => 'Մասնագիտական մաքրում մեծ տարածքով առանձնատների համար։',
                    'ru' => 'Профессиональная уборка больших площадей частных домов.',
                    'en' => 'Professional cleaning for the larger footprint of a private house.',
                ],
                'description' => [
                    'hy' => 'Առանձնատների մաքրումը պահանջում է ավելի շատ ժամանակ և ռեսուրս, քանի որ տարածքը մեծ է։ Servio-ի թիմը կազմակերպում է աշխատանքը այնպես, որ մաքրվի ողջ տունը՝ ներառյալ բոլոր հարկերն ու օժանդակ տարածքները։',
                    'ru' => 'Уборка частного дома требует больше времени и ресурсов из-за площади помещения. Команда Servio организует работу так, чтобы был убран весь дом, включая все этажи и вспомогательные помещения.',
                    'en' => 'Cleaning a house takes more time and resources because of its size. The Servio team organizes the work so the entire house is cleaned, including every floor and utility area.',
                ],
                'seo_title' => [
                    'hy' => 'Տների և առանձնատների մաքրում Երևանում',
                    'ru' => 'Уборка домов в Ереване: цены',
                    'en' => 'House Cleaning in Yerevan: Prices',
                ],
                'seo_description' => [
                    'hy' => 'Տների և առանձնատների կանոնավոր, ընդհանուր և հետվերանորոգման մաքրում Երևանում։ Փորձառու թիմ, մասնագիտական միջոցներ և նախապես համաձայնեցված արժեք։',
                    'ru' => 'Регулярная, генеральная и послеремонтная уборка частных домов в Ереване. Профессиональная команда, оборудование и согласованная заранее цена.',
                    'en' => 'Regular, general and post-renovation cleaning for private houses in Yerevan. Experienced team, professional equipment and a price agreed in advance.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է մի քանի հարկից բաղկացած առանձնատների համար, որտեղ մակերեսը և սենյակների քանակը զգալիորեն մեծ են բնակարանի համեմատ։',
                    'ru' => 'Подходит для многоэтажных частных домов, где площадь и число комнат заметно больше, чем в квартире.',
                    'en' => 'Suited to multi-floor private houses, where the footprint and number of rooms are noticeably larger than an apartment.',
                ],
                'included_items' => [
                    'hy' => [
                        'Մաքրում բոլոր հարկերում և սենյակներում',
                        'Խոհանոցի և լոգարանների մաքրում յուրաքանչյուր հարկում',
                        'Աստիճանավանդակի և միջանցքների մաքրում',
                        'Օժանդակ և պահեստային տարածքների կոկիկացում',
                        'Հատակի խնամք՝ ըստ ծածկույթի (սալիկ, լամինատ, պարկետ)',
                        'Աղբի հավաքում յուրաքանչյուր հարկից',
                    ],
                    'ru' => [
                        'Уборка на всех этажах и во всех комнатах',
                        'Уборка кухни и санузлов на каждом этаже',
                        'Уборка лестницы и коридоров',
                        'Наведение порядка в подсобных и складских помещениях',
                        'Уход за полом в зависимости от покрытия (плитка, ламинат, паркет)',
                        'Сбор мусора с каждого этажа',
                    ],
                    'en' => [
                        'Cleaning across every floor and room',
                        'Kitchen and bathroom cleaning on each floor',
                        'Staircase and hallway cleaning',
                        'Tidying utility and storage rooms',
                        'Floor care matched to the surface (tile, laminate, hardwood)',
                        'Trash collection from every floor',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Բակի, այգու և ֆասադի մաքրում',
                        'Լողավազանի սպասարկում',
                        'Փափուկ կահույքի և գորգերի քիմմաքրում (առանձին ծառայություն)',
                        'Ծանր կահույքի կամ տեխնիկայի տեղափոխում',
                    ],
                    'ru' => [
                        'Уборка двора, сада и фасада',
                        'Обслуживание бассейна',
                        'Химчистка мягкой мебели и ковров (отдельная услуга)',
                        'Перемещение тяжёлой мебели или техники',
                    ],
                    'en' => [
                        'Yard, garden or façade cleaning',
                        'Swimming pool maintenance',
                        'Upholstery and carpet dry cleaning (a separate service)',
                        'Moving heavy furniture or appliances',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Հայտի ընդունման ժամանակ ճշտվում է հարկերի քանակը և ընդհանուր մակերեսը',
                        'Թիմի կազմը հարմարեցվում է տան չափին',
                        'Մաքրումն իրականացվում է հարկ առ հարկ',
                        'Ավարտին կատարվում է ընդհանուր ստուգում',
                    ],
                    'ru' => [
                        'При приёме заявки уточняется количество этажей и общая площадь',
                        'Состав команды подбирается под размер дома',
                        'Уборка проводится этаж за этажом',
                        'В конце — общая проверка результата',
                    ],
                    'en' => [
                        'The number of floors and total area are confirmed when booking',
                        'Team size is scaled to the house\'s size',
                        'Cleaning proceeds floor by floor',
                        'A final walkthrough checks the result',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Նույն սարքավորումները, ինչ բնակարանների մաքրման համար, գումարած լրացուցիչ փոշեկուլային հզորություն և երկարացնող գործիքներ՝ աստիճանների ու բարձր շրիշակների համար։',
                    'ru' => 'То же оборудование, что и для уборки квартир, плюс дополнительная мощность пылесосов и удлинительные насадки для лестниц и высоких плинтусов.',
                    'en' => 'The same equipment used for apartment cleaning, plus extra vacuum capacity and extension tools for stairs and high skirting boards.',
                ],
                'duration_note' => [
                    'hy' => 'Առանձնատան մաքրումն ավելի երկար է տևում, քան նույն մակերեսով բնակարանինը՝ հարկերի ու օժանդակ տարածքների պատճառով. ճշգրիտ ժամանակը տրվում է մակերեսն ու հարկերի քանակը գնահատելուց հետո։',
                    'ru' => 'Уборка дома занимает больше времени, чем квартиры такой же площади, из-за нескольких этажей и подсобных помещений — точное время даётся после оценки площади и числа этажей.',
                    'en' => 'Cleaning a house takes longer than an apartment of similar area because of multiple floors and utility rooms — an exact time is given after assessing area and floor count.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Ընդհանուր մակերես',
                        'Հարկերի քանակ',
                        'Սանհանգույցների քանակ',
                        'Ընդհանուր վիճակ',
                        'Պատվիրված լրացուցիչ ծառայություններ',
                    ],
                    'ru' => [
                        'Общая площадь',
                        'Количество этажей',
                        'Количество санузлов',
                        'Общее состояние',
                        'Заказанные дополнительные услуги',
                    ],
                    'en' => [
                        'Total area',
                        'Number of floors',
                        'Number of bathrooms',
                        'General condition',
                        'Any additional services requested',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եք մաքրել մի քանի հարկից բաղկացած տունը մեկ այցով', 'answer' => 'Այո, թիմի կազմը հարմարեցվում է տան չափին և հարկերի քանակին։'],
                        ['question' => 'Կարո՞ղ եք մաքրել միայն տան մի մասը', 'answer' => 'Այո, հայտում նշեք, թե որ սենյակները կամ հարկերն են անհրաժեշտ մաքրել։'],
                    ],
                    'ru' => [
                        ['question' => 'Можно ли убрать многоэтажный дом за один визит', 'answer' => 'Да, состав команды подбирается под размер дома и количество этажей.'],
                        ['question' => 'Можно ли убрать только часть дома', 'answer' => 'Да, укажите в заявке, какие комнаты или этажи нужно убрать.'],
                    ],
                    'en' => [
                        ['question' => 'Can you clean a multi-floor house in one visit', 'answer' => 'Yes — team size is scaled to the house\'s size and number of floors.'],
                        ['question' => 'Can you clean only part of the house', 'answer' => 'Yes, just specify which rooms or floors you need cleaned when booking.'],
                    ],
                ],
            ],
            [
                'category_id' => $cleaning->id,
                'slug' => 'office-cleaning',
                'icon' => 'building',
                'sort_order' => 3,
                'name' => ['hy' => 'Գրասենյակների մաքրում', 'ru' => 'Уборка офисов и коммерческих помещений', 'en' => 'Office & Commercial Cleaning'],
                'short_description' => [
                    'hy' => 'Մաքուր և կոկիկ աշխատանքային միջավայր՝ գրասենյակների և կոմերցիոն տարածքների համար։',
                    'ru' => 'Чистая и опрятная рабочая среда для офисов и коммерческих помещений.',
                    'en' => 'A clean, tidy working environment for offices and commercial spaces.',
                ],
                'description' => [
                    'hy' => 'Մենք առաջարկում ենք գրասենյակների և կոմերցիոն տարածքների մաքրում՝ հարմարեցված ձեր աշխատանքային գրաֆիկին։ Մաքրումն իրականացվում է այնպես, որ նվազագույնի հասցնի անհարմարությունը աշխատակիցների համար։',
                    'ru' => 'Мы предлагаем уборку офисов и коммерческих помещений, подстроенную под ваш рабочий график. Уборка организована так, чтобы минимально мешать сотрудникам.',
                    'en' => 'We offer office and commercial space cleaning scheduled around your working hours, organized to minimize disruption for your staff.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է գրասենյակների, խանութների և կոմերցիոն այլ տարածքների համար, որոնց մաքրումը պետք է հարմարվի աշխատանքային գրաֆիկին։',
                    'ru' => 'Подходит офисам, магазинам и другим коммерческим помещениям, уборку которых нужно вписать в рабочий график.',
                    'en' => 'Suited to offices, shops and other commercial spaces where cleaning needs to fit around a working schedule.',
                ],
                'included_items' => [
                    'hy' => [
                        'Աշխատասեղանների և ընդհանուր մակերեսների սրբում',
                        'Հատակի փոշեկուլում և խոնավ մաքրում',
                        'Խոհանոցի/հանգստի գոտու մաքրում',
                        'Զուգարանների մաքրում և ախտահանում',
                        'Աղբահանություն և տոպրակների փոխարինում',
                        'Ապակե միջնորմների և մուտքի դռների մաքրում',
                    ],
                    'ru' => [
                        'Протирание рабочих столов и общих поверхностей',
                        'Пылесос и влажная уборка пола',
                        'Уборка кухни/зоны отдыха',
                        'Уборка и дезинфекция санузлов',
                        'Вынос мусора и замена пакетов',
                        'Мытьё стеклянных перегородок и входных дверей',
                    ],
                    'en' => [
                        'Wiping desks and common surfaces',
                        'Vacuuming and mopping floors',
                        'Cleaning the kitchen/break area',
                        'Cleaning and disinfecting restrooms',
                        'Trash collection and liner replacement',
                        'Cleaning glass partitions and entrance doors',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Մասնագիտացված սարքավորումների մաքրում (սերվերային, բժշկական սարքեր)',
                        'Հատուկ հեռացում պահանջող թափոնների կառավարում',
                        'Գրասենյակային կահույքի քիմմաքրում (առանձին ծառայություն)',
                        'Վերանորոգման կամ տեխնիկական աշխատանքներ',
                    ],
                    'ru' => [
                        'Уборка специализированного оборудования (серверные, медтехника)',
                        'Утилизация отходов, требующих особого обращения',
                        'Химчистка офисной мебели (отдельная услуга)',
                        'Ремонтные или технические работы',
                    ],
                    'en' => [
                        'Cleaning specialized equipment (server rooms, medical devices)',
                        'Disposal of waste requiring special handling',
                        'Upholstery dry cleaning of office furniture (a separate service)',
                        'Repairs or technical maintenance work',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Համաձայնեցվում է գրաֆիկը՝ աշխատանքային ժամերից առաջ, հետո կամ հանգստյան օրերին',
                        'Համաձայնեցվում է տարածք մուտք գործելու եղանակը',
                        'Մաքրումն իրականացվում է գոտի առ գոտի ստուգաթերթով',
                        'Կանոնավոր պայմանագրերի դեպքում սահմանվում է հաստատուն գրաֆիկ',
                    ],
                    'ru' => [
                        'Согласовывается график — до начала рабочего дня, после него или в выходные',
                        'Согласовывается способ доступа в помещение',
                        'Уборка проводится по зонам согласно чек-листу',
                        'При регулярном контракте устанавливается фиксированный график',
                    ],
                    'en' => [
                        'A schedule is agreed — before or after working hours, or on weekends',
                        'Access arrangements for the space are confirmed',
                        'Cleaning proceeds zone by zone following a checklist',
                        'Recurring contracts get a fixed, repeating schedule',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Կոմերցիոն հզորության փոշեկուլներ, միկրոմանրաթելային համակարգեր և մակերեսին համապատասխան ախտահանիչներ։',
                    'ru' => 'Пылесосы коммерческого класса, системы на основе микрофибры и дезинфицирующие средства, подобранные под тип поверхности.',
                    'en' => 'Commercial-grade vacuum cleaners, microfiber systems and disinfectants matched to each surface.',
                ],
                'duration_note' => [
                    'hy' => 'Կախված է գրասենյակի մակերեսից, աշխատատեղերի քանակից և ցանկալի հաճախականությունից. հաստատուն գրաֆիկ է սահմանվում առաջին գնահատումից հետո։',
                    'ru' => 'Зависит от площади офиса, числа рабочих мест и желаемой частоты — фиксированный график устанавливается после первой оценки.',
                    'en' => 'Depends on the office\'s size, number of workstations and desired frequency — a fixed schedule is set after the first assessment.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Ընդհանուր մակերես',
                        'Հաճախականություն՝ միանգամյա կամ կանոնավոր պայմանագիր',
                        'Սանհանգույցների քանակ',
                        'Աշխատատեղերի քանակ',
                        'Մուտքի ժամանակացույց (աշխատանքային ժամեր կամ ոչ)',
                    ],
                    'ru' => [
                        'Общая площадь',
                        'Частота — разовая уборка или регулярный контракт',
                        'Количество санузлов',
                        'Количество рабочих мест',
                        'График доступа (в рабочее время или нет)',
                    ],
                    'en' => [
                        'Total area',
                        'Frequency — one-time or a recurring contract',
                        'Number of restrooms',
                        'Number of workstations',
                        'Access schedule (during or outside working hours)',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եք մաքրել աշխատանքային ժամերից հետո', 'answer' => 'Այո, գրաֆիկը հարմարեցվում է ձեր աշխատանքային ժամերին։'],
                        ['question' => 'Առաջարկու՞մ եք կանոնավոր պայմանագրեր', 'answer' => 'Այո, հաճախականությունը համաձայնեցվում է անհատապես՝ ըստ կարիքի։'],
                    ],
                    'ru' => [
                        ['question' => 'Можно ли убирать после рабочего дня', 'answer' => 'Да, график подстраивается под ваши рабочие часы.'],
                        ['question' => 'Предлагаете ли вы регулярные контракты', 'answer' => 'Да, частота согласовывается индивидуально исходя из потребностей.'],
                    ],
                    'en' => [
                        ['question' => 'Can cleaning happen after working hours', 'answer' => 'Yes, the schedule is arranged around your working hours.'],
                        ['question' => 'Do you offer recurring contracts', 'answer' => 'Yes, frequency is agreed individually based on your needs.'],
                    ],
                ],
            ],
            [
                'category_id' => $cleaning->id,
                'slug' => 'general-cleaning',
                'icon' => 'sparkles',
                'sort_order' => 4,
                'name' => ['hy' => 'Ընդհանուր մաքրում', 'ru' => 'Генеральная уборка', 'en' => 'General Cleaning'],
                'short_description' => [
                    'hy' => 'Խորը, մանրակրկիտ մաքրում՝ ներառյալ դժվարամատչելի հատվածները։',
                    'ru' => 'Глубокая, тщательная уборка с проработкой труднодоступных мест.',
                    'en' => 'Deep, thorough cleaning that reaches hard-to-access areas.',
                ],
                'description' => [
                    'hy' => 'Ընդհանուր մաքրումն ընդգրկում է առավել մանրակրկիտ աշխատանք՝ պատուհանների, դռների, կահույքի հետնամասերի և այլ դժվարամատչելի հատվածների մաքրում։ Հարմար է տան կամ գրասենյակի համապարփակ թարմացման համար։',
                    'ru' => 'Генеральная уборка включает более тщательную работу — окна, двери, места за мебелью и другие труднодоступные участки. Подходит для комплексного обновления квартиры или офиса.',
                    'en' => 'General cleaning covers more detailed work — windows, doors, the space behind furniture and other hard-to-reach spots. Suited for a thorough refresh of a home or office.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է տեղափոխվելիս, սեզոնային թարմացման, երկար ժամանակ չմաքրված բնակարանի կամ հատուկ միջոցառումից առաջ պատրաստվելու համար։',
                    'ru' => 'Подходит при переезде, сезонном обновлении, если квартиру давно не убирали генерально, или перед особым событием.',
                    'en' => 'Suited to moving in or out, a seasonal refresh, an apartment that hasn\'t had a deep clean in a while, or preparing for a specific event.',
                ],
                'included_items' => [
                    'hy' => [
                        'Փոշու հեռացում պատերից և առաստաղներից՝ անվտանգ հասանելի բարձրության վրա',
                        'Պատուհանների և շրջանակների լվացում՝ ներսից և անվտանգ հասանելի արտաքինից',
                        'Փոշու հեռացում կահույքի հետևից և տակից',
                        'Խոհանոցային տեխնիկայի ներսի մաքրում (փուռ, միկրոալիքային) նախնական համաձայնությամբ',
                        'Լոգարանի կրաքարային նստվածքի հեռացում սալիկներից և սանտեխնիկայից',
                        'Անջատիչների և շրիշակների մաքրում',
                        'Հատակների մանրակրկիտ մշակում',
                    ],
                    'ru' => [
                        'Удаление пыли со стен и потолков на безопасно доступной высоте',
                        'Мытьё окон и рам изнутри и безопасно доступной части снаружи',
                        'Удаление пыли за и под мебелью',
                        'Чистка кухонной техники изнутри (духовка, микроволновка) по предварительному согласованию',
                        'Удаление известкового налёта с плитки и сантехники в ванной',
                        'Чистка выключателей и плинтусов',
                        'Тщательная обработка полов',
                    ],
                    'en' => [
                        'Removing dust from walls and ceilings at a safely accessible height',
                        'Washing windows and frames inside and the safely accessible outside',
                        'Clearing dust behind and under furniture',
                        'Cleaning kitchen appliance interiors (oven, microwave) by prior agreement',
                        'Removing limescale from bathroom tiles and fixtures',
                        'Cleaning light switches and skirting boards',
                        'A thorough floor treatment',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Փափուկ կահույքի և գորգերի քիմմաքրում (առանձին ծառայություն)',
                        'Բորբոսի կամ հին, խորը ներծծված բծերի երաշխավորված վերացում',
                        'Բազմահարկ շենքերում պատուհանների արտաքին լվացում առանց անվտանգ հասանելիության',
                        'Ծանր կամ մեծածավալ կահույքի տեղափոխում',
                    ],
                    'ru' => [
                        'Химчистка мягкой мебели и ковров (отдельная услуга)',
                        'Гарантированное удаление плесени или старых, глубоко въевшихся пятен',
                        'Мытьё окон снаружи в многоэтажных домах без безопасного доступа',
                        'Перемещение тяжёлой или крупногабаритной мебели',
                    ],
                    'en' => [
                        'Upholstery and carpet dry cleaning (a separate service)',
                        'Guaranteed removal of mould or old, deeply set stains',
                        'Exterior window washing in multi-story buildings without safe access',
                        'Moving heavy or bulky furniture',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Հայտում նշվում են սենյակները և հատուկ խնդրանքները (օր.՝ սառնարան, պահարաններ)',
                        'Թիմն աշխատում է վերևից ներքև և հետևից առաջ՝ ըստ սենյակի',
                        'Խոհանոցում և լոգարանում կատարվում է կենտրոնացված ճարպի ու կրաքարի հեռացում',
                        'Հատակների մշակումը կատարվում է վերջում',
                        'Աշխատանքի ավարտից հետո՝ համատեղ ստուգում',
                    ],
                    'ru' => [
                        'В заявке указываются комнаты и особые пожелания (например, холодильник, шкафы)',
                        'Команда работает сверху вниз и от дальней стены к выходу в каждой комнате',
                        'На кухне и в ванной — целенаправленное удаление жира и известкового налёта',
                        'Обработка полов выполняется в последнюю очередь',
                        'После завершения — совместная проверка результата',
                    ],
                    'en' => [
                        'The request specifies rooms and any special requests (e.g. fridge, cabinets)',
                        'The team works top-to-bottom and back-to-front within each room',
                        'Kitchen and bathroom get focused degreasing and limescale removal',
                        'Floor treatment is finished last',
                        'A joint walkthrough follows completion',
                    ],
                ],
                'equipment' => [
                    'hy' => 'HEPA ֆիլտրով փոշեկուլներ անհրաժեշտության դեպքում, ճարպահեռացնող և հականեխիչ միջոցներ, ապակու մաքրիչներ և միկրոմանրաթելային համակարգեր։',
                    'ru' => 'При необходимости — пылесосы с HEPA-фильтром, обезжириватели и средства от накипи, стеклоочистители, системы на основе микрофибры.',
                    'en' => 'HEPA-filter vacuums where useful, degreasers and limescale removers, glass cleaners and microfiber systems.',
                ],
                'duration_note' => [
                    'hy' => 'Սովորաբար տևում է մի քանի ժամ մեկ կամ երկու հոգանոց թիմով՝ կախված մակերեսից և վիճակից. ճշգրիտ ժամանակը հաստատվում է հայտը դիտարկելուց հետո։',
                    'ru' => 'Обычно занимает несколько часов работы бригады из одного-двух человек в зависимости от площади и состояния — точное время подтверждается после рассмотрения заявки.',
                    'en' => 'Typically takes several hours with a one- or two-person team, depending on area and condition — the exact time is confirmed after reviewing the request.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Տարածքի մակերես',
                        'Ընթացիկ վիճակ',
                        'Սանհանգույցների քանակ',
                        'Պատուհանների քանակ',
                        'Կահույքի ծավալ',
                        'Լրացուցիչ խնդրանքներ (սառնարան, պահարաններ)',
                    ],
                    'ru' => [
                        'Площадь помещения',
                        'Текущее состояние',
                        'Количество санузлов',
                        'Количество окон',
                        'Объём мебели',
                        'Дополнительные пожелания (холодильник, шкафы)',
                    ],
                    'en' => [
                        'Area of the property',
                        'Current condition',
                        'Number of bathrooms',
                        'Number of windows',
                        'Amount of furniture',
                        'Extra requests (fridge, cabinet interiors)',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Որքա՞ն հաճախ է պետք ընդհանուր մաքրում', 'answer' => 'Միջին բնակարանի համար՝ տարեկան 2-4 անգամ, կախված ընտանիքի կազմից և կենդանիների առկայությունից։'],
                        ['question' => 'Բոլո՞ր բծերը կհեռացվեն', 'answer' => 'Մեծ մասամբ մակերեսային կեղտը, ճարպն ու կրաքարը հեռացվում են, սակայն շատ հին կամ խորը ներծծված բծերը միշտ չէ, որ հնարավոր է ամբողջությամբ վերացնել։'],
                    ],
                    'ru' => [
                        ['question' => 'Как часто нужна генеральная уборка', 'answer' => 'В среднем 2-4 раза в год для типичной квартиры, в зависимости от состава семьи и наличия животных.'],
                        ['question' => 'Все ли пятна будут удалены', 'answer' => 'Большая часть поверхностных загрязнений, жира и налёта удаляется, но очень старые или глубоко въевшиеся пятна не всегда получается убрать полностью.'],
                    ],
                    'en' => [
                        ['question' => 'How often is general cleaning needed', 'answer' => 'On average 2-4 times a year for a typical apartment, depending on household size and pets.'],
                        ['question' => 'Will every stain be removed', 'answer' => 'Most surface dirt, grease and limescale comes out, but very old or deeply set stains aren\'t always fully removable.'],
                    ],
                ],
            ],
            [
                'category_id' => $cleaning->id,
                'slug' => 'regular-cleaning',
                'icon' => 'repeat',
                'sort_order' => 5,
                'name' => ['hy' => 'Պարբերական մաքրում', 'ru' => 'Регулярная уборка', 'en' => 'Regular Cleaning'],
                'short_description' => [
                    'hy' => 'Շաբաթական կամ ամսական հիմունքներով մաքրում՝ մշտական մաքրության համար։',
                    'ru' => 'Уборка на еженедельной или ежемесячной основе для поддержания порядка.',
                    'en' => 'Weekly or monthly cleaning to keep your space consistently tidy.',
                ],
                'description' => [
                    'hy' => 'Պարբերական մաքրումը թույլ է տալիս պահպանել կարգուկանոնը՝ առանց ամեն անգամ նոր պատվեր կատարելու։ Ընտրեք ձեզ հարմար հաճախականությունը, իսկ մնացածը մենք կկազմակերպենք։',
                    'ru' => 'Регулярная уборка помогает поддерживать порядок без необходимости каждый раз оформлять новую заявку. Выберите удобную периодичность — остальное организуем мы.',
                    'en' => 'Regular cleaning helps maintain order without placing a new request each time. Choose a frequency that works for you, and we take care of the rest.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է ընտանիքների և բնակիչների համար, ովքեր ցանկանում են պահպանել արդեն մաքուր տան կարգուկանոնը՝ կանոնավոր գրաֆիկով։',
                    'ru' => 'Подходит семьям и жильцам, которые хотят поддерживать порядок в уже чистой квартире по регулярному графику.',
                    'en' => 'Suited to households that want to maintain an already-clean home on a recurring schedule.',
                ],
                'included_items' => [
                    'hy' => [
                        'Հատակների փոշեկուլում և խոնավ մաքրում',
                        'Մակերեսների փոշեհեռացում և սրբում',
                        'Խոհանոցի աշխատանքային մակերեսի, լվացարանի և սալօջախի սրբում',
                        'Լոգարանի և զուգարանի մաքրում',
                        'Աղբահանություն',
                        'Հայելիների և ապակու սրբում',
                    ],
                    'ru' => [
                        'Пылесос и влажная уборка полов',
                        'Удаление пыли и протирание поверхностей',
                        'Протирание рабочей поверхности кухни, раковины и плиты',
                        'Уборка ванной и туалета',
                        'Вынос мусора',
                        'Протирание зеркал и стекла',
                    ],
                    'en' => [
                        'Vacuuming and mopping floors',
                        'Dusting and wiping surfaces',
                        'Wiping the kitchen worktop, sink and stovetop',
                        'Cleaning the bathroom and toilet',
                        'Taking out the trash',
                        'Wiping mirrors and glass',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Խորը մաքրման աշխատանքներ (կահույքի հետևը, տեխնիկայի ներսը) — պատվիրվում է որպես ընդհանուր մաքրում',
                        'Փափուկ կահույքի քիմմաքրում',
                        'Պատուհանների լվացում, բացի տեսանելի ապակու սրբումից',
                        'Հետշինարարական մաքրում',
                    ],
                    'ru' => [
                        'Глубокая уборка (за мебелью, внутри техники) — заказывается как генеральная уборка',
                        'Химчистка мягкой мебели',
                        'Мытьё окон, кроме протирания видимого стекла',
                        'Уборка после ремонта',
                    ],
                    'en' => [
                        'Deep-cleaning tasks (behind furniture, appliance interiors) — booked as general cleaning',
                        'Upholstery dry cleaning',
                        'Window washing beyond wiping the visible glass',
                        'Post-construction cleaning',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Հայտ թողնելիս ընտրվում է հաճախականությունը՝ շաբաթական, երկշաբաթյա կամ ամսական',
                        'Հնարավորության դեպքում նշանակվում է նույն կամ հաստատուն թիմ',
                        'Յուրաքանչյուր այցելություն հետևում է նույն ստուգաթերթին',
                        'Հաճախականությունը կարող է փոփոխվել ցանկացած պահի',
                    ],
                    'ru' => [
                        'При оформлении заявки выбирается частота — еженедельно, раз в две недели или ежемесячно',
                        'По возможности назначается одна и та же или постоянная команда',
                        'Каждый визит проходит по одному и тому же чек-листу',
                        'Частоту можно изменить в любой момент',
                    ],
                    'en' => [
                        'A frequency is chosen at booking — weekly, biweekly or monthly',
                        'The same or a consistent team is assigned where possible',
                        'Each visit follows the same maintenance checklist',
                        'Frequency can be adjusted at any time',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Փոշեկուլ, մոպ, միկրոմանրաթելային շորեր և ունիվերսալ ու լոգարանի մաքրող միջոցներ։',
                    'ru' => 'Пылесос, швабра, микрофибра, универсальные и специализированные средства для ванной комнаты.',
                    'en' => 'A vacuum cleaner, mop, microfiber cloths, and all-purpose and bathroom-specific cleaning products.',
                ],
                'duration_note' => [
                    'hy' => 'Յուրաքանչյուր այցելություն ավելի կարճ է, քան ընդհանուր մաքրումը, քանի որ պահպանում է արդեն մաքուր տունը. ճշգրիտ ժամանակը հաստատվում է հայտ թողնելիս՝ ըստ մակերեսի։',
                    'ru' => 'Каждый визит короче генеральной уборки, так как поддерживает уже чистую квартиру — точное время подтверждается при оформлении заявки исходя из площади.',
                    'en' => 'Each visit is shorter than a general clean, since it maintains an already-clean home — the typical time is confirmed at booking based on area.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Տարածքի մակերես',
                        'Ընտրված հաճախականություն',
                        'Սանհանգույցների քանակ',
                        'Սենյակների քանակ',
                        'Յուրաքանչյուր այցելության հավելյալ խնդրանքներ',
                    ],
                    'ru' => [
                        'Площадь помещения',
                        'Выбранная частота',
                        'Количество санузлов',
                        'Количество комнат',
                        'Дополнительные пожелания к каждому визиту',
                    ],
                    'en' => [
                        'Area of the property',
                        'Chosen frequency',
                        'Number of bathrooms',
                        'Number of rooms',
                        'Any add-ons requested for a given visit',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եմ հետագայում փոխել հաճախականությունը', 'answer' => 'Այո, ցանկացած պահի։'],
                        ['question' => 'Իսկ եթե մեկ անգամ ինձ պետք է ավելի խորը մաքրում', 'answer' => 'Կարող եք առանձին պատվիրել ընդհանուր մաքրում՝ պարբերական գրաֆիկին զուգահեռ։'],
                    ],
                    'ru' => [
                        ['question' => 'Можно ли изменить частоту в будущем', 'answer' => 'Да, в любой момент.'],
                        ['question' => 'А если один раз нужна более глубокая уборка', 'answer' => 'Можно отдельно заказать генеральную уборку в дополнение к регулярному графику.'],
                    ],
                    'en' => [
                        ['question' => 'Can I change the frequency later', 'answer' => 'Yes, at any time.'],
                        ['question' => 'What if I need a deeper clean just once', 'answer' => 'You can book a one-off general cleaning alongside your regular schedule.'],
                    ],
                ],
            ],
            [
                'category_id' => $cleaning->id,
                'slug' => 'post-construction-cleaning',
                'icon' => 'hammer',
                'sort_order' => 6,
                'name' => ['hy' => 'Հետշինարարական մաքրում', 'ru' => 'Послестроительная уборка', 'en' => 'Post-Construction Cleaning'],
                'short_description' => [
                    'hy' => 'Շինարարական փոշու և մնացորդների հեռացում վերանորոգումից հետո։',
                    'ru' => 'Удаление строительной пыли и остатков после ремонта.',
                    'en' => 'Removing construction dust and debris after renovation work.',
                ],
                'description' => [
                    'hy' => 'Վերանորոգումից հետո տարածքը կարիք ունի մասնագիտական մաքրման՝ շինարարական փոշուց, մնացորդներից և բծերից ազատվելու համար։ Մենք օգտագործում ենք համապատասխան սարքավորումներ նման աշխատանքի համար։',
                    'ru' => 'После ремонта помещению требуется профессиональная уборка, чтобы избавиться от строительной пыли, остатков материалов и пятен. Мы используем оборудование, подходящее именно для такой задачи.',
                    'en' => 'After a renovation, a space needs professional cleaning to clear construction dust, leftover materials and stains. We use equipment suited to exactly this kind of job.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է վերանորոգումից կամ շինարարությունից անմիջապես հետո, բնակարան մտնելուց կամ տարածքը հանձնելուց առաջ։',
                    'ru' => 'Подходит сразу после ремонта или строительных работ, перед заселением или сдачей помещения.',
                    'en' => 'Suited to right after a renovation or construction project, before moving in or handing the space over.',
                ],
                'included_items' => [
                    'hy' => [
                        'Մանր շինարարական աղբի և փաթեթավորման նյութերի հեռացում',
                        'Փոշու հեռացում պատերից, առաստաղներից և օդափոխիչներից',
                        'Պատուհանների և շրջանակների մաքրում ներկի ու սոսնձի հետքերից',
                        'Հատակների մանրակրկիտ մաքրում՝ ըստ ծածկույթի տեսակի',
                        'Անջատիչների, բռնակների և վահանակների մաքրում',
                        'Խոհանոցի և լոգարանի սանտեխնիկայի մաքրում շինարարական մնացորդներից',
                        'Հատակների բազմափուլ փոշեկուլում և լվացում մինչև փոշու ամբողջական հեռացումը',
                    ],
                    'ru' => [
                        'Удаление мелкого строительного мусора и упаковочных материалов',
                        'Удаление пыли со стен, потолков и вентиляционных решёток',
                        'Очистка окон и рам от следов краски и клея',
                        'Тщательная уборка полов в зависимости от типа покрытия',
                        'Чистка выключателей, ручек и панелей',
                        'Очистка сантехники кухни и ванной от строительных остатков',
                        'Многоэтапный пылесос и мытьё полов до полного удаления пыли',
                    ],
                    'en' => [
                        'Removing small construction debris and packaging materials',
                        'Clearing dust from walls, ceilings and vent grilles',
                        'Cleaning windows and frames of paint and adhesive residue',
                        'Thorough floor cleaning matched to the surface type',
                        'Cleaning light switches, handles and panels',
                        'Clearing kitchen and bathroom fixtures of construction residue',
                        'Multi-pass vacuuming and mopping until dust is fully cleared',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Մեծածավալ շինարարական աղբի հավաքում և տեղափոխում (պայմանավորվում է առանձին)',
                        'Բարձրության վրա վտանգավոր աշխատանքներ և արդյունաբերական ալպինիզմ',
                        'Ամրացած ներկի կամ սոսնձի հեռացման երաշխիք, եթե առկա է մակերեսի վնասման վտանգ',
                        'Կահույքի հավաքում կամ վերանորոգման աշխատանքներ',
                    ],
                    'ru' => [
                        'Сбор и вывоз крупного строительного мусора (согласовывается отдельно)',
                        'Опасные высотные работы и промышленный альпинизм',
                        'Гарантия удаления засохшей краски или клея при риске повреждения поверхности',
                        'Сборка мебели или ремонтные работы',
                    ],
                    'en' => [
                        'Collection and removal of large construction waste (arranged separately)',
                        'Dangerous work at height or industrial rope access',
                        'A guarantee of removing set paint or adhesive where the surface could be damaged',
                        'Furniture assembly or repair work',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Գնահատվում է աղտոտվածության աստիճանը և հարդարման տեսակները',
                        'Առաջին փուլում կատարվում է չոր փոշու հեռացում',
                        'Այնուհետև՝ մակերեսների և սանտեխնիկայի թաց մաքրում',
                        'Հատակների բազմափուլ մաքրում, մինչև փոշին այլևս չհայտնվի',
                        'Ավարտական ստուգում հաճախորդի հետ',
                    ],
                    'ru' => [
                        'Оценивается степень загрязнения и типы отделки',
                        'Первый этап — сухое удаление пыли',
                        'Затем — влажная уборка поверхностей и сантехники',
                        'Многоэтапная уборка полов, пока пыль не перестанет появляться',
                        'Финальная проверка с клиентом',
                    ],
                    'en' => [
                        'Assessing the contamination level and finish types',
                        'A first pass of dry dust removal',
                        'Then wet cleaning of surfaces and fixtures',
                        'Multi-pass floor cleaning until dust stops reappearing',
                        'A final walkthrough with the client',
                    ],
                ],
                'equipment' => [
                    'hy' => 'HEPA ֆիլտրով մասնագիտական փոշեկուլներ, մակերեսին համապատասխան ներկի/սոսնձի հետքեր հեռացնող միջոցներ և հաճախակի փոխվող մաքրող պարագաներ՝ փոշին կրկին չտարածելու համար։',
                    'ru' => 'Профессиональные пылесосы с HEPA-фильтром, средства для удаления следов краски/клея, подобранные под поверхность, и часто сменяемый инвентарь, чтобы не разносить пыль повторно.',
                    'en' => 'Professional HEPA-filter vacuums, paint/adhesive residue removers matched to each surface, and frequently changed cleaning cloths to avoid redistributing dust.',
                ],
                'duration_note' => [
                    'hy' => 'Տևում է ավելի երկար, քան ընդհանուր մաքրումը՝ բազմափուլ մշակման պատճառով. ճշգրիտ ժամանակը կախված է մակերեսից, աղտոտվածությունից և հարդարման տեսակներից, հաստատվում է տեղում կամ լուսանկարներով գնահատումից հետո։',
                    'ru' => 'Занимает больше времени, чем генеральная уборка, из-за многоэтапной обработки — точное время зависит от площади, степени загрязнения и типов отделки и подтверждается после осмотра на месте или по фото.',
                    'en' => 'Takes longer than a general clean because of the multiple passes required — the exact time depends on area, contamination level and finish types, confirmed after an on-site or photo assessment.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Տարածքի մակերես',
                        'Աղտոտվածության աստիճան',
                        'Սանհանգույցների և պատուհանների քանակ',
                        'Հատակի և մակերեսների տեսակ',
                        'Մեծածավալ աղբահանության անհրաժեշտություն (պայմանավորվում է առանձին)',
                        'Հանձնման ժամկետի հրատապություն',
                    ],
                    'ru' => [
                        'Площадь помещения',
                        'Степень загрязнения',
                        'Количество санузлов и окон',
                        'Тип пола и поверхностей',
                        'Необходимость вывоза крупного мусора (согласовывается отдельно)',
                        'Срочность сдачи объекта',
                    ],
                    'en' => [
                        'Area of the property',
                        'Level of contamination',
                        'Number of bathrooms and windows',
                        'Floor and surface types',
                        'Whether large debris removal is needed (arranged separately)',
                        'How urgent the handover timeline is',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Հեռացնու՞մ եք մեծածավալ շինարարական աղբը', 'answer' => 'Դա ստանդարտ գնի մեջ չի մտնում, բայց կարող է կազմակերպվել առանձին պայմանավորվածությամբ։'],
                        ['question' => 'Կարո՞ղ եք երաշխավորել ներկի բոլոր հետքերի հեռացումը հատակից', 'answer' => 'Թարմ ներկի հետքերի մեծ մասը հեռացվում է, սակայն արդյունքը կախված է հատակի տեսակից և բծի «տարիքից». ճշգրիտ գնահատականը տրվում է տեղում։'],
                    ],
                    'ru' => [
                        ['question' => 'Вывозите ли вы крупный строительный мусор', 'answer' => 'Это не входит в стандартную стоимость, но может быть организовано по отдельной договорённости.'],
                        ['question' => 'Можете ли вы гарантировать удаление всех следов краски с пола', 'answer' => 'Большинство свежих пятен краски удаляется, но результат зависит от типа напольного покрытия и «возраста» пятна — точная оценка даётся на месте.'],
                    ],
                    'en' => [
                        ['question' => 'Do you remove large construction waste', 'answer' => 'That\'s not part of the standard price, but it can be arranged separately by agreement.'],
                        ['question' => 'Can you guarantee removing every paint mark from the floor', 'answer' => 'Most fresh paint marks can be removed, but the result depends on the flooring type and how long the stain has set — we give an accurate assessment on site.'],
                    ],
                ],
            ],
            [
                'category_id' => $upholstery->id,
                'slug' => 'sofa-cleaning',
                'icon' => 'sofa',
                'sort_order' => 1,
                'name' => ['hy' => 'Բազմոցների քիմմաքրում', 'ru' => 'Химчистка диванов', 'en' => 'Sofa Dry Cleaning'],
                'short_description' => [
                    'hy' => 'Բազմոցների խորը քիմմաքրում՝ բծերի և հոտերի վերացմամբ։',
                    'ru' => 'Глубокая химчистка диванов с удалением пятен и запахов.',
                    'en' => 'Deep upholstery cleaning for sofas, removing stains and odors.',
                ],
                'description' => [
                    'hy' => 'Ժամանակի ընթացքում բազմոցի փափուկ երեսպատումը կուտակում է փոշի, բծեր ու հոտեր։ Մասնագիտական քիմմաքրումը վերականգնում է գործվածքի մաքրությունն ու թարմությունը՝ առանց վնասելու նյութը։',
                    'ru' => 'Со временем обивка дивана накапливает пыль, пятна и запахи. Профессиональная химчистка восстанавливает чистоту и свежесть ткани, не повреждая материал.',
                    'en' => "Over time a sofa's upholstery collects dust, stains and odors. Professional dry cleaning restores the fabric's cleanliness and freshness without damaging the material.",
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է գործվածքային կամ կաշվե բազմոցների համար, որոնց վրա կան տեսանելի բծեր, հոտեր, կամ որպես պարբերական խնամք։',
                    'ru' => 'Подходит для тканевых или кожаных диванов с видимыми пятнами, запахами, а также для периодического ухода.',
                    'en' => 'Suited to fabric or leather sofas with visible stains or odors, or as periodic maintenance.',
                ],
                'included_items' => [
                    'hy' => [
                        'Գործվածքի տեսակի որոշում և փորձարկում աննկատ հատվածում',
                        'Չոր փոշեկուլում՝ մակերեսային փոշին և մազերը հեռացնելու համար',
                        'Տեսանելի բծերի նախնական մշակում',
                        'Խորը մաքրում մասնագիտական էքստրակցիոն սարքով',
                        'Չորացման ժամանակի և օդափոխության վերաբերյալ երաշխավորություններ',
                        'Արդյունքի վերջնական ստուգում',
                    ],
                    'ru' => [
                        'Определение типа ткани и тест на незаметном участке',
                        'Сухая чистка пылесосом для удаления поверхностной пыли и шерсти',
                        'Предварительная обработка видимых пятен',
                        'Глубокая чистка профессиональным экстракционным оборудованием',
                        'Рекомендации по времени сушки и проветриванию',
                        'Финальная проверка результата',
                    ],
                    'en' => [
                        'Identifying the fabric type and testing an inconspicuous spot',
                        'Dry vacuuming to remove surface dust and hair',
                        'Pre-treating visible stains',
                        'Deep cleaning with professional extraction equipment',
                        'Guidance on drying time and ventilation',
                        'A final check of the result',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Հին, խորը ներծծված կամ գունանյութային բծերի երաշխավորված հեռացում',
                        'Կմախքի, զսպանակների կամ շարժակների վերանորոգում',
                        'Կաշվի խնամք և վերականգնում (առանձին ծառայություն)',
                        'Ոչ գործվածքային դեկորատիվ տարրերի մաքրում՝ առանց նախնական համաձայնության',
                    ],
                    'ru' => [
                        'Гарантированное удаление старых, глубоко въевшихся или красящих пятен',
                        'Ремонт каркаса, пружин или механизмов',
                        'Уход за кожей и её восстановление (отдельная услуга)',
                        'Чистка нетканевых декоративных элементов без предварительного согласования',
                    ],
                    'en' => [
                        'Guaranteed removal of old, deeply set or dye-transfer stains',
                        'Repairs to the frame, springs or mechanisms',
                        'Leather conditioning and restoration (a separate service)',
                        'Cleaning non-fabric decorative elements without prior agreement',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Գործվածքի/կաշվի տեսակի ստուգում և փորձարկում աննկատ հատվածում',
                        'Չոր փոշեկուլում',
                        'Բծերի նախնական մշակում',
                        'Խորը մաքրում էքստրակցիոն մեթոդով',
                        'Չորացում՝ լավ օդափոխության պայմաններում',
                    ],
                    'ru' => [
                        'Проверка типа ткани/кожи и тест на незаметном участке',
                        'Сухая чистка пылесосом',
                        'Предварительная обработка пятен',
                        'Глубокая чистка методом экстракции',
                        'Сушка при хорошей вентиляции',
                    ],
                    'en' => [
                        'Checking the fabric/leather type and testing an inconspicuous spot',
                        'Dry vacuuming',
                        'Pre-treating stains',
                        'Deep cleaning via extraction',
                        'Drying with good ventilation',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Մասնագիտական էքստրակցիոն սարքավորում, գործվածքին համապատասխան շամպուններ և բծերի նախնական մշակման միջոցներ, որոնք ընտրվում են փորձարկումից հետո։',
                    'ru' => 'Профессиональное экстракционное оборудование, шампуни под тип ткани и средства для предварительной обработки пятен, подбираемые после теста.',
                    'en' => 'Professional extraction equipment, fabric-specific shampoos and stain pre-treatment products chosen after the spot test.',
                ],
                'duration_note' => [
                    'hy' => 'Ինքնին մաքրումը սովորաբար տևում է մեկ ժամից պակաս մեկ բազմոցի համար. ամբողջական չորացումը կարող է տևել մի քանի ժամ՝ կախված գործվածքից, խոնավությունից և օդափոխությունից։',
                    'ru' => 'Сама чистка обычно занимает меньше часа на один диван; полное высыхание может занять несколько часов в зависимости от ткани, влажности и вентиляции.',
                    'en' => 'Cleaning itself usually takes under an hour per sofa; full drying can take several hours depending on the fabric, humidity and ventilation.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Բազմոցի չափը/նստատեղերի քանակը',
                        'Գործվածքի տեսակը',
                        'Կեղտոտվածության աստիճանը',
                        'Բծերի նախնական մշակման անհրաժեշտությունը',
                        'Հասանելիությունը (հարկ, հեռավորություն կայանատեղիից)',
                    ],
                    'ru' => [
                        'Размер дивана / количество посадочных мест',
                        'Тип ткани',
                        'Степень загрязнения',
                        'Необходимость предварительной обработки пятен',
                        'Доступность (этаж, расстояние от парковки)',
                    ],
                    'en' => [
                        'Sofa size / number of seats',
                        'Fabric type',
                        'Level of soiling',
                        'Whether stain pre-treatment is needed',
                        'Access (floor, distance from parking)',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Որքա՞ն հաճախ է պետք քիմմաքրել բազմոցը', 'answer' => 'Սովորական օգտագործման դեպքում՝ տարին մեկ-երկու անգամ բավական է, կենդանիների կամ երեխաների առկայության դեպքում՝ ավելի հաճախ։'],
                        ['question' => 'Բոլո՞ր տեսակի գործվածքները կարելի է մաքրել նույն մեթոդով', 'answer' => 'Ոչ, որոշ նուրբ կամ բնական գործվածքներ պահանջում են մեղմ մոտեցում, ինչը որոշվում է նախնական ստուգմամբ։'],
                        ['question' => 'Բոլո՞ր հոտերը կվերանան', 'answer' => 'Հոտերի մեծ մասը զգալիորեն նվազում է, սակայն շատ հին կամ խորը ներծծված հոտերը միշտ չէ, որ հնարավոր է ամբողջությամբ վերացնել։'],
                    ],
                    'ru' => [
                        ['question' => 'Как часто нужно чистить диван', 'answer' => 'При обычном использовании достаточно одного-двух раз в год, при наличии животных или детей — чаще.'],
                        ['question' => 'Можно ли чистить любые ткани одним методом', 'answer' => 'Нет, некоторые деликатные или натуральные ткани требуют более мягкого подхода, который определяется при первичном осмотре.'],
                        ['question' => 'Все ли запахи исчезнут', 'answer' => 'Большинство запахов значительно уменьшается, но очень старые или глубоко впитавшиеся запахи не всегда получается устранить полностью.'],
                    ],
                    'en' => [
                        ['question' => 'How often should a sofa be professionally cleaned', 'answer' => 'Once or twice a year for typical use is enough; more often with pets or kids.'],
                        ['question' => 'Can every fabric be cleaned the same way', 'answer' => 'No — some delicate or natural fabrics need a gentler approach, determined during the initial inspection.'],
                        ['question' => 'Will all odors disappear', 'answer' => 'Most odors are significantly reduced, but very old or deeply absorbed odors may not be fully eliminated.'],
                    ],
                ],
            ],
            [
                'category_id' => $upholstery->id,
                'slug' => 'armchair-chair-cleaning',
                'icon' => 'armchair',
                'sort_order' => 2,
                'name' => ['hy' => 'Աթոռների և բազկաթոռների քիմմաքրում', 'ru' => 'Химчистка кресел и стульев', 'en' => 'Armchair & Chair Dry Cleaning'],
                'short_description' => [
                    'hy' => 'Աթոռների և բազկաթոռների փափուկ մասերի մասնագիտական մաքրում։',
                    'ru' => 'Профессиональная чистка мягких элементов стульев и кресел.',
                    'en' => 'Professional cleaning for the upholstered parts of chairs and armchairs.',
                ],
                'description' => [
                    'hy' => 'Աթոռների ու բազկաթոռների երեսպատումը նույնպես կարիք ունի պարբերական խնամքի։ Մենք մաքրում ենք գործվածքն ու թավշյա մակերեսները՝ վերադարձնելով դրանց սկզբնական տեսքը։',
                    'ru' => 'Обивка стульев и кресел тоже нуждается в регулярном уходе. Мы очищаем ткань и велюровые поверхности, возвращая им первоначальный вид.',
                    'en' => 'Chair and armchair upholstery also needs regular care. We clean fabric and velvet surfaces, restoring their original look.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է փափուկ երեսպատում ունեցող բազկաթոռների, ճաշասենյակի աթոռների և գրասենյակային աթոռների համար, որոնց վրա կան տեսանելի կեղտ կամ մաշվածություն։',
                    'ru' => 'Подходит для мягких кресел, обеденных стульев и офисных кресел с видимыми загрязнениями или следами износа.',
                    'en' => 'Suited to upholstered armchairs, dining chairs and office chairs with visible dirt or wear.',
                ],
                'included_items' => [
                    'hy' => [
                        'Երեսպատման տեսակի ստուգում և փորձարկում աննկատ հատվածում',
                        'Չոր փոշեկուլում',
                        'Բծերի նախնական մշակում',
                        'Փափուկ մակերեսի էքստրակցիոն մաքրում',
                        'Չորացման ժամանակի ապահովում',
                    ],
                    'ru' => [
                        'Проверка типа обивки и тест на незаметном участке',
                        'Сухая чистка пылесосом',
                        'Предварительная обработка пятен',
                        'Экстракционная чистка мягкой поверхности',
                        'Время на просушку',
                    ],
                    'en' => [
                        'Checking the upholstery type and testing an inconspicuous spot',
                        'Dry vacuuming',
                        'Pre-treating stains',
                        'Extraction cleaning of the upholstered surface',
                        'Allowing time to dry',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Կմախքի և միացումների վերանորոգում',
                        'Կաշվի խնամք',
                        'Շատ հին բծերի երաշխավորված հեռացում',
                    ],
                    'ru' => [
                        'Ремонт каркаса и соединений',
                        'Уход за кожей',
                        'Гарантированное удаление очень старых пятен',
                    ],
                    'en' => [
                        'Frame and joint repairs',
                        'Leather conditioning',
                        'Guaranteed removal of very old stains',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Զննում և փորձարկում',
                        'Չոր փոշեկուլում',
                        'Էքստրակցիոն մաքրում',
                        'Չորացում',
                    ],
                    'ru' => [
                        'Осмотр и тест',
                        'Сухая чистка пылесосом',
                        'Экстракционная чистка',
                        'Сушка',
                    ],
                    'en' => [
                        'Inspection and spot test',
                        'Dry vacuuming',
                        'Extraction cleaning',
                        'Drying',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Նույն էքստրակցիոն սարքավորումը, ինչ բազմոցների համար, հարմարեցված ավելի փոքր փափուկ մակերեսներին։',
                    'ru' => 'То же экстракционное оборудование, что и для диванов, адаптированное под меньшую площадь обивки.',
                    'en' => 'The same extraction equipment used for sofas, scaled to smaller upholstered surfaces.',
                ],
                'duration_note' => [
                    'hy' => 'Մեկ աթոռի կամ բազկաթոռի մաքրումը արագ է՝ սովորաբար մեկ ժամից պակաս. չորացումը տևում է մի քանի ժամ։',
                    'ru' => 'Чистка одного стула или кресла проходит быстро — обычно меньше часа; сушка занимает несколько часов.',
                    'en' => 'Cleaning a single chair or armchair is quick — typically under an hour; drying takes a few hours.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Միավորների քանակ',
                        'Գործվածքի տեսակ',
                        'Կեղտոտվածության աստիճան',
                        'Նախնական մշակման անհրաժեշտություն',
                    ],
                    'ru' => [
                        'Количество единиц',
                        'Тип ткани',
                        'Степень загрязнения',
                        'Необходимость предварительной обработки',
                    ],
                    'en' => [
                        'Number of pieces',
                        'Fabric type',
                        'Level of soiling',
                        'Whether pre-treatment is needed',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եմ պատվիրել միայն մեկ աթոռի մաքրում', 'answer' => 'Այո, քիմմաքրման համար նվազագույն պատվերի սահմանափակում չկա, ի տարբերություն ամբողջական բնակարանի մաքրման։'],
                        ['question' => 'Ե՞րբ կարող եմ նորից օգտագործել աթոռը', 'answer' => 'Ամբողջական չորանալուց հետո, սովորաբար նույն օրը։'],
                    ],
                    'ru' => [
                        ['question' => 'Можно ли заказать чистку только одного стула', 'answer' => 'Да, для химчистки нет минимального объёма заказа, в отличие от уборки всей квартиры.'],
                        ['question' => 'Когда можно снова пользоваться креслом', 'answer' => 'После полного высыхания, обычно в тот же день.'],
                    ],
                    'en' => [
                        ['question' => 'Can I book just one chair', 'answer' => 'Yes — there\'s no minimum order for dry cleaning, unlike whole-apartment cleaning.'],
                        ['question' => 'How soon can I use the chair again', 'answer' => 'Once it\'s fully dry, usually the same day.'],
                    ],
                ],
            ],
            [
                'category_id' => $upholstery->id,
                'slug' => 'mattress-cleaning',
                'icon' => 'bed',
                'sort_order' => 3,
                'name' => ['hy' => 'Ներքնակների քիմմաքրում', 'ru' => 'Химчистка матрасов', 'en' => 'Mattress Dry Cleaning'],
                'short_description' => [
                    'hy' => 'Ներքնակների խորը մաքրում և ախտահանում՝ առողջ քնի համար։',
                    'ru' => 'Глубокая чистка и дезинфекция матрасов для здорового сна.',
                    'en' => 'Deep cleaning and disinfection of mattresses for healthier sleep.',
                ],
                'description' => [
                    'hy' => 'Ներքնակը ուղղակիորեն շփվում է մաշկի հետ, ուստի կարևոր է այն պահել մաքուր։ Մեր քիմմաքրումը հեռացնում է փոշին, բծերն ու փոշեմատղերին՝ նպաստելով ավելի առողջ քնի միջավայրի։',
                    'ru' => 'Матрас находится в постоянном контакте с кожей, поэтому важно поддерживать его чистоту. Наша химчистка удаляет пыль, пятна и пылевых клещей, способствуя более здоровой среде для сна.',
                    'en' => 'A mattress is in constant contact with skin, so keeping it clean matters. Our dry cleaning removes dust, stains and dust mites, contributing to a healthier sleep environment.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է ներքնակների համար, որոնք երկար ժամանակ մասնագիտորեն չեն մաքրվել, հիվանդությունից կամ ալերգիայից հետո, կամ որպես կանոնավոր հիգիենիկ խնամք։',
                    'ru' => 'Подходит для матрасов, которые давно не проходили профессиональную чистку, после болезни или аллергии, а также в рамках регулярной гигиены.',
                    'en' => 'Suited to mattresses that haven\'t had a professional clean in a while, after illness or allergies, or as routine hygiene upkeep.',
                ],
                'included_items' => [
                    'hy' => [
                        'Գործվածքի տեսակի ստուգում',
                        'Երկու կողմերի չոր փոշեկուլում',
                        'Բծերի նախնական մշակում',
                        'Էքստրակցիոն մաքրում',
                        'Ախտահանման փուլ',
                        'Չորացում օդափոխությամբ',
                    ],
                    'ru' => [
                        'Проверка типа ткани',
                        'Сухая чистка пылесосом с обеих сторон',
                        'Предварительная обработка пятен',
                        'Экстракционная чистка',
                        'Этап дезинфекции',
                        'Сушка с проветриванием',
                    ],
                    'en' => [
                        'Checking the fabric type',
                        'Dry vacuuming both sides',
                        'Pre-treating stains',
                        'Extraction cleaning',
                        'A disinfection pass',
                        'Drying with airflow',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Ներքնակի կմախքի կամ զսպանակների վերանորոգում',
                        'Փոշեմատղերի և ալերգենների ամբողջական վերացման երաշխիք',
                        'Շատ հին ներքնակների հոտի ամբողջական վերացման երաշխիք',
                        'Memory foam նյութի մաքրում առանց արտադրողի հրահանգների նախնական ստուգման',
                    ],
                    'ru' => [
                        'Ремонт каркаса или пружинного блока матраса',
                        'Гарантия полного устранения пылевых клещей и аллергенов',
                        'Гарантия полного устранения запаха у очень старых матрасов',
                        'Чистка материала memory foam без предварительной проверки рекомендаций производителя',
                    ],
                    'en' => [
                        'Repairs to the mattress frame or spring unit',
                        'A guarantee of completely eliminating dust mites and allergens',
                        'A guarantee of fully removing odor from very old mattresses',
                        'Cleaning memory foam without first checking manufacturer guidance',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Գործվածքի/պատյանի ստուգում',
                        'Երկու կողմերի չոր փոշեկուլում',
                        'Էքստրակցիոն մաքրում և ախտահանում',
                        'Չորացում՝ մինչև ամբողջական օգտագործման պատրաստ լինելը',
                    ],
                    'ru' => [
                        'Проверка ткани/чехла',
                        'Сухая чистка пылесосом с обеих сторон',
                        'Экстракционная чистка и дезинфекция',
                        'Сушка до полной готовности к использованию',
                    ],
                    'en' => [
                        'Checking the fabric/cover',
                        'Dry vacuuming both sides',
                        'Extraction cleaning and disinfection',
                        'Drying until fully ready for use',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Էքստրակցիոն սարքավորում, ներքնակի գործվածքի համար անվտանգ ախտահանիչ լուծույթներ և չորացնող օդափոխիչներ։',
                    'ru' => 'Экстракционное оборудование, безопасные для ткани матраса дезинфицирующие растворы и вентиляторы для сушки.',
                    'en' => 'Extraction equipment, disinfecting solutions safe for mattress fabric, and drying fans.',
                ],
                'duration_note' => [
                    'hy' => 'Մաքրումն ինքնին տևում է մոտ մեկ ժամ մեկ ներքնակի համար. ամբողջական չորացումը, մինչև հնարավոր կլինի օգտագործել, կարող է տևել մի քանի ժամ, ուստի արժե նախապես նախատեսել այլընտրանքային քնելու տեղ մեկ գիշերվա համար։',
                    'ru' => 'Сама чистка занимает около часа на матрас; полное высыхание перед использованием может занять несколько часов, поэтому стоит заранее продумать альтернативное место для сна на одну ночь.',
                    'en' => 'Cleaning itself takes about an hour per mattress; full drying before use can take several hours, so it\'s worth planning an alternative place to sleep for one night.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Ներքնակի չափը (մեկանձնյա/երկանձնյա)',
                        'Կեղտոտվածության աստիճանը',
                        'Երկու՞ կողմն է մաքրվում, թե՞ մեկը',
                        'Ախտահանման լրացուցիչ ծառայությունը',
                    ],
                    'ru' => [
                        'Размер матраса (одно- или двуспальный)',
                        'Степень загрязнения',
                        'Чистка одной или обеих сторон',
                        'Дополнительная услуга дезинфекции',
                    ],
                    'en' => [
                        'Mattress size (single or double)',
                        'Level of soiling',
                        'Whether one or both sides are cleaned',
                        'The disinfection add-on',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Ամբողջությա՞մբ կվերացվեն փոշեմատղերը', 'answer' => 'Ոչ. մեթոդը զգալիորեն նվազեցնում է փոշեմատղերի, փոշու և բծերի քանակը, բայց չի երաշխավորում դրանց ամբողջական վերացումը։'],
                        ['question' => 'Որքա՞ն հաճախ է պետք մաքրել ներքնակը', 'answer' => 'Մեծամասնության համար բավական է տարեկան մեկ անգամ, ալերգիայի ենթակա մարդկանց համար՝ ավելի հաճախ։'],
                        ['question' => 'Կարո՞ղ եմ անմիջապես քնել դրա վրա', 'answer' => 'Ոչ, պետք է սպասել ամբողջական չորացմանը, ինչը կարող է տևել մի քանի ժամ։'],
                    ],
                    'ru' => [
                        ['question' => 'Полностью ли удаляются пылевые клещи', 'answer' => 'Нет — метод значительно снижает количество пылевых клещей, пыли и пятен, но не гарантирует их полное устранение.'],
                        ['question' => 'Как часто нужно чистить матрас', 'answer' => 'Большинству достаточно раз в год, людям с аллергией — чаще.'],
                        ['question' => 'Можно ли сразу спать на нём', 'answer' => 'Нет, нужно дождаться полного высыхания, что может занять несколько часов.'],
                    ],
                    'en' => [
                        ['question' => 'Does this completely remove dust mites', 'answer' => 'No — the method significantly reduces dust mites, dust and stains, but doesn\'t guarantee their complete elimination.'],
                        ['question' => 'How often should a mattress be cleaned', 'answer' => 'Most households do well with once a year; more often for allergy sufferers.'],
                        ['question' => 'Can I sleep on it right away', 'answer' => 'No, you\'ll need to wait until it\'s fully dry, which can take several hours.'],
                    ],
                ],
            ],
            [
                'category_id' => $upholstery->id,
                'slug' => 'carpet-textile-cleaning',
                'icon' => 'layers',
                'sort_order' => 4,
                'name' => ['hy' => 'Գորգերի և տեքստիլի քիմմաքրում', 'ru' => 'Химчистка ковров и другого текстиля', 'en' => 'Carpet & Textile Dry Cleaning'],
                'short_description' => [
                    'hy' => 'Գորգերի և այլ տեքստիլի մասնագիտական քիմմաքրում։',
                    'ru' => 'Профессиональная химчистка ковров и другого домашнего текстиля.',
                    'en' => 'Professional dry cleaning for carpets and other home textiles.',
                ],
                'description' => [
                    'hy' => 'Գորգերը և տան տեքստիլը կուտակում են փոշի և բծեր, որոնք դժվար է հեռացնել սովորական մաքրությամբ։ Մենք օգտագործում ենք համապատասխան մեթոդներ՝ հարմարեցված տարբեր տեսակի գործվածքներին։',
                    'ru' => 'Ковры и домашний текстиль накапливают пыль и пятна, которые сложно удалить обычной уборкой. Мы используем методы, подходящие для разных типов тканей.',
                    'en' => 'Carpets and home textiles accumulate dust and stains that are hard to remove with regular cleaning. We use methods suited to different fabric types.',
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է գորգերի, ներքիների և տան այլ տեքստիլի համար, որոնց վրա կան տեսանելի կեղտ, կամ որոնք երկար ժամանակ մասնագիտորեն չեն մաքրվել։',
                    'ru' => 'Подходит для ковров, паласов и другого домашнего текстиля с видимыми загрязнениями или давно не чистившихся профессионально.',
                    'en' => 'Suited to carpets, rugs and other home textiles with visible dirt, or that haven\'t been professionally cleaned in a long time.',
                ],
                'included_items' => [
                    'hy' => [
                        'Նյութի և ձևավորման տեսակի ստուգում',
                        'Չոր փոշեկուլում',
                        'Բծերի նախնական մշակում',
                        'Էքստրակցիոն կամ շամպունային մաքրում՝ ըստ նյութի',
                        'Չորացում',
                    ],
                    'ru' => [
                        'Проверка типа материала и плетения',
                        'Сухая чистка пылесосом',
                        'Предварительная обработка пятен',
                        'Экстракционная или шампуневая чистка в зависимости от материала',
                        'Сушка',
                    ],
                    'en' => [
                        'Checking the material and pile type',
                        'Dry vacuuming',
                        'Pre-treating stains',
                        'Extraction or shampoo cleaning matched to the material',
                        'Drying',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Հնաոճ կամ նուրբ ձեռագործ գորգերի մասնագիտացված վերականգնում',
                        'Հին ներկանյութի կամ թանաքի բծերի երաշխավորված հեռացում',
                        'Ծոպերի, եզրերի կամ հիմքի վերանորոգում',
                        'Վերցնում/առաքում, եթե նախապես չի պայմանավորվել',
                    ],
                    'ru' => [
                        'Специализированная реставрация антикварных или деликатных ручных ковров',
                        'Гарантированное удаление старых красящих пятен или пятен от чернил',
                        'Ремонт бахромы, кромок или основы',
                        'Забор/доставка, если не согласовано заранее',
                    ],
                    'en' => [
                        'Specialist restoration of antique or delicate hand-woven rugs',
                        'Guaranteed removal of old dye or ink stains',
                        'Repairs to fringes, edges or backing',
                        'Pickup/delivery unless agreed in advance',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Նյութի և գույնի կայունության ստուգում',
                        'Չոր փոշեկուլում',
                        'Այդ գորգի տեսակին հարմար մեթոդի ընտրություն',
                        'Չորացում',
                    ],
                    'ru' => [
                        'Проверка материала и устойчивости окраски',
                        'Сухая чистка пылесосом',
                        'Выбор метода, подходящего для этого типа ковра',
                        'Сушка',
                    ],
                    'en' => [
                        'Checking the material and color-fastness',
                        'Dry vacuuming',
                        'Choosing the method suited to that carpet type',
                        'Drying',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Գորգի հյուսվածքին ու հիմքին համապատասխան էքստրակցիոն սարքավորում կամ մեթոդ, և pH-ին համապատասխան շամպուններ։',
                    'ru' => 'Экстракционное оборудование или метод, подобранные под структуру и основу ковра, и шампуни с подходящим pH.',
                    'en' => 'Extraction equipment or a method matched to the carpet\'s pile and backing, with pH-appropriate shampoos.',
                ],
                'duration_note' => [
                    'hy' => 'Մաքրման արժեքն ու ժամանակը հաշվարկվում են ըստ քառակուսի մետրի. չորացումը կարող է տևել մինչև մեկ օր՝ կախված հյուսվածքի խտությունից և օդափոխությունից։',
                    'ru' => 'Чистка оценивается и планируется по времени из расчёта за квадратный метр; сушка может занять до суток в зависимости от плотности ворса и вентиляции.',
                    'en' => 'Cleaning is priced and timed per square meter; drying can take up to a day depending on pile thickness and ventilation.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Մակերես (քմ)',
                        'Գորգի տեսակ/հյուսվածք',
                        'Կեղտոտվածության աստիճան',
                        'Տեղում կամ հատուկ պայմաններում մաքրման անհրաժեշտություն',
                    ],
                    'ru' => [
                        'Площадь (м²)',
                        'Тип ковра / плетение',
                        'Степень загрязнения',
                        'Чистка на месте или в особых условиях',
                    ],
                    'en' => [
                        'Area (m²)',
                        'Carpet type / pile',
                        'Level of soiling',
                        'Whether on-site or specialist cleaning is needed',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Կարո՞ղ եմ քայլել գորգի վրայով, մինչև այն ամբողջությամբ չորանա', 'answer' => 'Ավելի լավ է սպասել ամբողջական չորացմանը, որպեսզի նոր կեղտ չկպչի թաց մանրաթելերին։'],
                        ['question' => 'Մաքրու՞մ եք նուրբ ձեռագործ գորգերը', 'answer' => 'Յուրաքանչյուր դեպք գնահատվում է առանձին. որոշ գորգեր պահանջում են ստանդարտից տարբեր, մասնագիտացված խնամք։'],
                    ],
                    'ru' => [
                        ['question' => 'Можно ли ходить по ковру до полного высыхания', 'answer' => 'Лучше дождаться полного высыхания, чтобы новая грязь не прилипла к влажным волокнам.'],
                        ['question' => 'Чистите ли вы деликатные ручные ковры', 'answer' => 'Каждый случай оценивается индивидуально — некоторым коврам требуется уход, выходящий за рамки стандартной чистки.'],
                    ],
                    'en' => [
                        ['question' => 'Can I walk on the carpet before it\'s dry', 'answer' => 'It\'s best to wait until it\'s fully dry, so new dirt doesn\'t stick to the damp fibers.'],
                        ['question' => 'Do you clean delicate hand-made rugs', 'answer' => 'Each case is assessed individually — some rugs need care beyond our standard cleaning.'],
                    ],
                ],
            ],
            [
                'category_id' => $upholstery->id,
                'slug' => 'steam-cleaning-disinfection',
                'icon' => 'waves',
                'sort_order' => 5,
                'name' => ['hy' => 'Գոլորշիով մաքրում և ախտահանում', 'ru' => 'Паровая очистка и дезинфекция', 'en' => 'Steam Cleaning & Disinfection'],
                'short_description' => [
                    'hy' => 'Բարձր ջերմաստիճանի գոլորշիով մաքրում և ախտահանում՝ առանց քիմիայի։',
                    'ru' => 'Дезинфекция и очистка горячим паром без агрессивной химии.',
                    'en' => 'High-temperature steam cleaning and disinfection without harsh chemicals.',
                ],
                'description' => [
                    'hy' => 'Գոլորշիով մաքրումը թույլ է տալիս ախտահանել մակերեսները՝ առանց ագրեսիվ քիմիական միջոցների կիրառման։ Այն հատկապես արդյունավետ է կահույքի, ներքնակների և տեքստիլի համար։',
                    'ru' => 'Паровая очистка позволяет дезинфицировать поверхности без использования агрессивных химических средств. Особенно эффективна для мебели, матрасов и текстиля.',
                    'en' => "Steam cleaning disinfects surfaces without aggressive chemical agents. It's especially effective for furniture, mattresses and textiles.",
                ],
                'who_its_for' => [
                    'hy' => 'Հարմար է ընտանիքների համար, ովքեր ցանկանում են ախտահանում առանց ագրեսիվ քիմիայի, կամ որպես լրացուցիչ քայլ կահույքի, ներքնակի կամ սալիկների մաքրմանը։',
                    'ru' => 'Подходит семьям, которым нужна дезинфекция без агрессивной химии, а также как дополнение к чистке мебели, матраса или плитки.',
                    'en' => 'Suited to households wanting disinfection without harsh chemicals, or as an add-on to furniture, mattress or tile cleaning.',
                ],
                'included_items' => [
                    'hy' => [
                        'Բարձր ջերմաստիճանի գոլորշու կիրառում համաձայնեցված մակերեսների վրա',
                        'Ընտրված կահույքի, ներքնակի կամ սալիկների/կարերի մշակում',
                        'Անհրաժեշտության դեպքում ավարտական սրբում',
                        'Ջերմազգայուն նյութերի անվտանգության նախնական ստուգում',
                    ],
                    'ru' => [
                        'Обработка горячим паром согласованных поверхностей',
                        'Обработка выбранной мебели, матраса или плитки/швов',
                        'Финальное протирание при необходимости',
                        'Предварительная проверка термочувствительных материалов на безопасность',
                    ],
                    'en' => [
                        'Applying high-temperature steam to the agreed surfaces',
                        'Treating the selected furniture, mattress, or tile and grout',
                        'A final wipe-down where needed',
                        'A safety check of heat-sensitive materials beforehand',
                    ],
                ],
                'excluded_items' => [
                    'hy' => [
                        'Ջերմությանը/խոնավությանը դիմակայել չկարողացող նյութերի մշակում',
                        'Ինքնուրույն բծերի հեռացում (գոլորշին ախտահանում է, բայց տեսանելի բծերը հաճախ դեռ պահանջում են նախնական մշակում)',
                        'Ամբողջ բնակարանի մեծածավալ գոլորշիով մաքրում մեկ գնով (հաշվարկվում է ըստ սենյակի/գոտու)',
                    ],
                    'ru' => [
                        'Обработка материалов, не переносящих тепло или влагу',
                        'Самостоятельное удаление пятен (пар дезинфицирует, но видимые пятна часто требуют отдельной предварительной обработки)',
                        'Масштабная обработка всей квартиры паром по единой цене (рассчитывается по комнатам/зонам)',
                    ],
                    'en' => [
                        'Treating materials that can\'t safely tolerate heat or moisture',
                        'Stain removal on its own (steam disinfects, but visible stains often still need separate pre-treatment)',
                        'Whole-apartment steam cleaning at a single flat price (priced per room/zone)',
                    ],
                ],
                'process_steps' => [
                    'hy' => [
                        'Նյութի ջերմադիմացկունության ստուգում',
                        'Գոլորշու կիրառում համաձայնեցված գոտիներում',
                        'Ավարտական սրբում և չորացում, անհրաժեշտության դեպքում',
                    ],
                    'ru' => [
                        'Проверка термостойкости материала',
                        'Обработка паром согласованных зон',
                        'Финальное протирание и просушивание при необходимости',
                    ],
                    'en' => [
                        'Checking the material\'s heat tolerance',
                        'Applying steam to the agreed zones',
                        'A final wipe-down and drying where relevant',
                    ],
                ],
                'equipment' => [
                    'hy' => 'Մասնագիտական գոլորշու գեներատորներ, որոնք հասնում են ախտահանման համար անհրաժեշտ ջերմաստիճանի՝ առանց լրացուցիչ քիմիայի։',
                    'ru' => 'Профессиональные парогенераторы, достигающие температуры, необходимой для дезинфекции, без добавления химии.',
                    'en' => 'Professional steam generators that reach disinfecting temperatures without added chemicals.',
                ],
                'duration_note' => [
                    'hy' => 'Սովորաբար արագ լրացուցիչ ծառայություն է՝ մեկ ժամից պակաս, կախված մշակվող գոտիների քանակից։',
                    'ru' => 'Обычно это быстрая дополнительная услуга — менее часа, в зависимости от количества обрабатываемых зон.',
                    'en' => 'Usually a fast add-on service — under an hour, depending on the number of zones treated.',
                ],
                'price_factors' => [
                    'hy' => [
                        'Մշակվող սենյակների/գոտիների կամ իրերի քանակը',
                        'Նյութի տեսակը',
                        'Համակցվու՞մ է այլ ծառայության հետ, թե ոչ',
                    ],
                    'ru' => [
                        'Количество обрабатываемых комнат/зон или предметов',
                        'Тип материала',
                        'Совмещается ли услуга с другой чисткой',
                    ],
                    'en' => [
                        'Number of rooms/zones or items treated',
                        'Material type',
                        'Whether it\'s combined with another cleaning service',
                    ],
                ],
                'faq' => [
                    'hy' => [
                        ['question' => 'Գոլորշիով մաքրումը փոխարինու՞մ է սովորական մաքրմանը', 'answer' => 'Ոչ, դա ախտահանման քայլ է, որը սովորաբար համակցվում է մաքրման հետ, այլ ոչ թե փոխարինում այն։'],
                        ['question' => 'Անվտա՞նգ է բոլոր գործվածքների համար', 'answer' => 'Ոչ, որոշ նյութեր զգայուն են ջերմության նկատմամբ, ինչը ստուգվում է աշխատանքը սկսելուց առաջ։'],
                    ],
                    'ru' => [
                        ['question' => 'Заменяет ли паровая обработка обычную уборку', 'answer' => 'Нет, это этап дезинфекции, который обычно совмещается с уборкой, а не заменяет её.'],
                        ['question' => 'Безопасно ли это для всех тканей', 'answer' => 'Нет, некоторые материалы чувствительны к теплу — это проверяется перед началом работы.'],
                    ],
                    'en' => [
                        ['question' => 'Does steam cleaning replace regular cleaning', 'answer' => 'No, it\'s a disinfection step usually combined with cleaning rather than replacing it.'],
                        ['question' => 'Is it safe for all fabrics', 'answer' => 'No, some materials are heat-sensitive — this is checked before starting.'],
                    ],
                ],
            ],
        ];

        foreach ($services as $service) {
            Service::query()->updateOrCreate(
                ['slug' => $service['slug']],
                $service
            );
        }
    }
}

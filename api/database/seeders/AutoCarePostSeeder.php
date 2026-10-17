<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Auto Care article stubs — seeded as Draft. They're real, substantive
 * outlines (not placeholder text) but deliberately not published, since
 * publishing content that references prices/technology Servio hasn't
 * confirmed yet would violate the no-fabricated-promises rule. The owner
 * reviews and flips each to Published once the referenced services are
 * Active. Same "{locale}" token convention as PostSeeder for internal links.
 */
class AutoCarePostSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'slug' => 'full-interior-cleaning-what-is-included',
                'category' => 'auto-care',
                'tags' => ['ավտոմեքենայի քիմմաքրում', 'химчистка салона автомобиля', 'car interior cleaning'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Ինչ է ներառում ավտոմեքենայի սրահի ամբողջական քիմմաքրումը',
                    'ru' => 'Что входит в полную химчистку салона автомобиля',
                    'en' => 'What\'s Included in a Full Car Interior Cleaning',
                ],
                'excerpt' => [
                    'hy' => 'Ամբողջական քիմմաքրման փուլերը՝ նստատեղերից մինչև բեռնախցիկ, և ինչից է կախված արդյունքը։',
                    'ru' => 'Этапы полной химчистки — от сидений до багажника — и от чего зависит результат.',
                    'en' => 'The stages of a full interior clean, from seats to trunk, and what determines the result.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p><a href="/{locale}/services/auto-care/car-interior-cleaning">Ամբողջական քիմմաքրումը</a> ներառում է սրահի բոլոր հիմնական տարրերի մշակում։ Յուրաքանչյուր մակերես պահանջում է առանձին մոտեցում, ուստի մեթոդը որոշվում է նյութը որոշելուց հետո։</p>
<h2>Ինչ է մշակվում</h2>
<p>Աշխատանքը ներառում է նստատեղերը, հատակի ծածկույթը, դռների պանելները, պլաստիկ մակերեսները և բեռնախցիկը։ Բծերը մշակվում են տեղային, նախքան ընդհանուր մաքրումը։</p>
<h2>Ինչից է կախված արդյունքը</h2>
<p>Նյութի տեսակը, աղտոտվածության աստիճանը և բծի ծագումը որոշիչ են։ Ուստի հստակ գնահատականը տրվում է լուսանկարներ ուղարկելուց կամ զննումից հետո։</p>
HTML,
                    'ru' => <<<'HTML'
<p><a href="/{locale}/services/auto-care/car-interior-cleaning">Полная химчистка</a> охватывает все основные элементы салона. Каждая поверхность требует своего подхода, поэтому метод определяется после выявления материала.</p>
<h2>Что обрабатывается</h2>
<p>В работу входят сиденья, напольное покрытие, дверные карты, пластиковые поверхности и багажник. Пятна обрабатываются точечно перед общей чисткой.</p>
<h2>От чего зависит результат</h2>
<p>Тип материала, степень загрязнения и происхождение пятна имеют решающее значение. Поэтому точная оценка даётся после присланных фото или осмотра.</p>
HTML,
                    'en' => <<<'HTML'
<p>A <a href="/{locale}/services/auto-care/car-interior-cleaning">full interior clean</a> covers every major surface in the car. Each material needs its own approach, so the method is chosen after identifying it.</p>
<h2>What gets treated</h2>
<p>The job covers seats, floor covering, door panels, plastic trim and the trunk. Stains are spot-treated before the general clean.</p>
<h2>What determines the result</h2>
<p>Material type, soiling level and what caused a stain all matter. That's why an accurate estimate is given after photos or an inspection.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Ինչ է ներառում սրահի քիմմաքրումը | Servio',
                    'ru' => 'Что входит в химчистку салона авто | Servio',
                    'en' => 'What\'s Included in Car Interior Cleaning | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի սրահի ամբողջական քիմմաքրման փուլերը և ինչից է կախված գինը։',
                    'ru' => 'Этапы полной химчистки салона автомобиля и от чего зависит цена.',
                    'en' => 'The stages of a full car interior clean and what drives the price.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եմ պատվիրել միայն սիրված մասերը', 'answer' => 'Այո, հասանելի է նաև տեղային մաքրում առանձին տարրերի համար։']],
                    'ru' => [['question' => 'Можно ли заказать только отдельные части', 'answer' => 'Да, доступна и локальная чистка отдельных элементов.']],
                    'en' => [['question' => 'Can I book just specific parts', 'answer' => 'Yes, local cleaning of individual elements is also available.']],
                ],
                'related_services' => ['car-interior-cleaning', 'stain-removal'],
            ],
            [
                'slug' => 'how-long-does-a-car-take-to-dry-after-cleaning',
                'category' => 'auto-care',
                'tags' => ['ավտոմեքենայի չորացում', 'сколько сохнет машина после химчистки', 'car drying time'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Որքա՞ն է չորանում ավտոմեքենան քիմմաքրումից հետո',
                    'ru' => 'Сколько сохнет автомобиль после химчистки',
                    'en' => 'How Long Does a Car Take to Dry After Cleaning',
                ],
                'excerpt' => [
                    'hy' => 'Չորացման ժամանակի վրա ազդող գործոնները և ինչպես արագացնել գործընթացը։',
                    'ru' => 'Факторы, влияющие на время высыхания, и как ускорить процесс.',
                    'en' => 'What affects drying time, and how to speed it up.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Չորացման ժամանակը հաստատուն թիվ չէ. այն կախված է մի քանի գործոնից։</p>
<h2>Ինչից է կախված</h2>
<p>Նյութի տեսակը (տեքստիլ, կաշի, ալկանտարա), եղանակը, սրահի օդափոխությունը և մաքրման խորությունը որոշում են ընդհանուր ժամանակը։</p>
<h2>Ինչպես արագացնել</h2>
<p>Օդափոխությունը և չբարձրացնել խոնավությունը փակ տարածքում օգնում են։ <a href="/{locale}/services/auto-care">Servio-ի թիմը</a> խորհուրդ է տալիս անհատական ցուցումներ՝ կախված կատարված աշխատանքից։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Время высыхания — не фиксированная цифра, оно зависит от нескольких факторов.</p>
<h2>От чего зависит</h2>
<p>Тип материала (ткань, кожа, алькантара), погода, вентиляция салона и глубина очистки определяют общее время.</p>
<h2>Как ускорить</h2>
<p>Проветривание и отсутствие лишней влаги в закрытом помещении помогают. <a href="/{locale}/services/auto-care">Команда Servio</a> даёт индивидуальные рекомендации в зависимости от выполненной работы.</p>
HTML,
                    'en' => <<<'HTML'
<p>Drying time isn't a fixed number — it depends on several factors.</p>
<h2>What it depends on</h2>
<p>The material (fabric, leather, alcantara), weather, interior ventilation and how deep the clean was all affect the total time.</p>
<h2>How to speed it up</h2>
<p>Airing the car out and avoiding extra moisture in an enclosed space both help. The <a href="/{locale}/services/auto-care">Servio team</a> gives specific guidance based on the work done.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Որքա՞ն է չորանում մեքենան քիմմաքրումից հետո | Servio',
                    'ru' => 'Сколько сохнет машина после химчистки | Servio',
                    'en' => 'How Long a Car Takes to Dry After Cleaning | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ավտոմեքենայի չորացման ժամանակի վրա ազդող գործոնները քիմմաքրումից հետո։',
                    'ru' => 'Что влияет на время высыхания автомобиля после химчистки.',
                    'en' => 'What affects how long a car takes to dry after a chemical clean.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եմ անմիջապես օգտագործել մեքենան', 'answer' => 'Խորհուրդ է տրվում սպասել առաջարկված ժամանակահատվածը, որը կախված է նյութից և եղանակից։']],
                    'ru' => [['question' => 'Можно ли сразу пользоваться машиной', 'answer' => 'Рекомендуется выждать указанное время, которое зависит от материала и погоды.']],
                    'en' => [['question' => 'Can I use the car right away', 'answer' => 'It\'s best to wait the recommended time, which depends on material and weather.']],
                ],
                'related_services' => ['car-interior-cleaning', 'floor-carpet-cleaning'],
            ],
            [
                'slug' => 'how-to-remove-cigarette-smell-from-car',
                'category' => 'auto-care',
                'tags' => ['ծխախոտի հոտի հեռացում մեքենայից', 'удалить запах табака из машины', 'remove cigarette smell from car'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Ինչպես հեռացնել ծխախոտի հոտը ավտոմեքենայից',
                    'ru' => 'Как удалить запах сигарет из автомобиля',
                    'en' => 'How to Remove Cigarette Smell from a Car',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է ծխախոտի հոտը դժվար հեռացվող և ինչ մոտեցում է անհրաժեշտ։',
                    'ru' => 'Почему запах табака трудно вывести и какой подход нужен.',
                    'en' => 'Why cigarette smell is hard to shift, and what approach it needs.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Ծխախոտի հոտը ներծծվում է ոչ միայն տեքստիլի, այլ նաև պլաստիկի ու օդափոխման համակարգի մեջ, ինչի պատճառով սովորական օդաբուրավորումը երկար չի աշխատում։</p>
<h2>Ինչու է դժվար</h2>
<p>Հոտի մասնիկները խորն են թափանցում ծակոտկեն մակերեսների մեջ։ Արդյունավետ մշակման համար անհրաժեշտ է հասնել հենց աղբյուրին, ոչ միայն մակերեսին։</p>
<h2>Ինչ մոտեցում ենք առաջարկում</h2>
<p><a href="/{locale}/services/auto-care/odor-removal">Հոտերի վերացման ծառայության</a> շրջանակում մեզ մոտ սկսվում է աղբյուրի հայտնաբերումից, ապա ընտրվում է համապատասխան մեթոդ։ Լրիվ երաշխիք չենք տալիս խորը ներծծված դեպքերի համար։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Запах табака впитывается не только в ткань, но и в пластик и систему вентиляции, поэтому обычные ароматизаторы не работают долго.</p>
<h2>Почему это сложно</h2>
<p>Частицы запаха глубоко проникают в пористые поверхности. Для эффективной обработки нужно добраться до источника, а не только до поверхности.</p>
<h2>Какой подход мы предлагаем</h2>
<p>В рамках <a href="/{locale}/services/auto-care/odor-removal">услуги по удалению запахов</a> мы начинаем с поиска источника, затем подбираем подходящий метод. Полную гарантию для глубоко въевшихся случаев мы не даём.</p>
HTML,
                    'en' => <<<'HTML'
<p>Cigarette smell soaks into more than fabric — plastic and the ventilation system pick it up too, which is why air fresheners don't last.</p>
<h2>Why it's hard to remove</h2>
<p>Odor particles work deep into porous surfaces. Effective treatment means reaching the source, not just the surface.</p>
<h2>Our approach</h2>
<p>Our <a href="/{locale}/services/auto-care/odor-removal">odor removal service</a> starts by locating the source, then applies a matching method. We don't give a full guarantee for deeply embedded cases.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Ծխախոտի հոտի հեռացում մեքենայից | Servio',
                    'ru' => 'Удалить запах табака из машины | Servio',
                    'en' => 'Remove Cigarette Smell from a Car | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է ծխախոտի հոտը դժվար հեռացվող մեքենայից, և ինչ մոտեցում ենք առաջարկում։',
                    'ru' => 'Почему запах табака сложно вывести из машины и какой подход мы предлагаем.',
                    'en' => 'Why tobacco odor is hard to remove from a car and the approach we take.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Ամբողջովի՞ն կվերանա հոտը', 'answer' => 'Մեծ մասամբ նվազում է զգալիորեն, սակայն ամբողջական վերացումը կախված է հոտի խորությունից և տարիքից։']],
                    'ru' => [['question' => 'Уйдёт ли запах полностью', 'answer' => 'Обычно он значительно уменьшается, но полное устранение зависит от глубины и возраста запаха.']],
                    'en' => [['question' => 'Will the smell go away completely', 'answer' => 'It usually reduces significantly, but full removal depends on how deep and old the odor is.']],
                ],
                'related_services' => ['odor-removal', 'ceiling-cleaning'],
            ],
            [
                'slug' => 'how-to-remove-urine-smell-from-car-seat',
                'category' => 'auto-care',
                'tags' => ['մեզի հոտի հեռացում', 'удалить запах мочи из машины', 'urine smell car seat'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Ինչպես հեռացնել մեզի հոտը ավտոմեքենայի նստատեղից',
                    'ru' => 'Как удалить запах мочи из автомобильного сиденья',
                    'en' => 'How to Remove Urine Smell from a Car Seat',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է մեզի հոտը հատուկ դժվար և ինչ քայլեր են անհրաժեշտ։',
                    'ru' => 'Почему запах мочи особенно трудно устранить и какие шаги нужны.',
                    'en' => 'Why urine odor is especially stubborn, and what steps it takes.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Մեզը պարունակում է բյուրեղացող բաղադրիչներ, որոնք խորապես ամրանում են տեքստիլի կամ փրփուրի մեջ, ինչի պատճառով մակերեսային մաքրումը հաճախ բավարար չէ։</p>
<h2>Ինչու է հատուկ բարդ</h2>
<p>Եթե բիծը հին է, այն կարող է հասնել մինչև նստատեղի փրփուրին, ինչը պահանջում է ավելի խորը մշակում։</p>
<h2>Ինչ ենք առաջարկում</h2>
<p>Աշխատում ենք աղբյուրին հարմարեցված մեթոդով <a href="/{locale}/services/auto-care/odor-removal">հոտերի վերացման ծառայության</a> շրջանակում, սակայն շատ հին կամ խորը դեպքերում ամբողջական երաշխիք չենք տալիս։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Моча содержит кристаллизующиеся компоненты, которые глубоко закрепляются в ткани или поролоне, поэтому поверхностной чистки часто недостаточно.</p>
<h2>Почему это особенно сложно</h2>
<p>Если пятно старое, оно может достигать поролона сиденья, что требует более глубокой обработки.</p>
<h2>Что мы предлагаем</h2>
<p>Работаем методом, подобранным под источник, в рамках <a href="/{locale}/services/auto-care/odor-removal">услуги по удалению запахов</a>, но для очень старых или глубоких случаев полную гарантию не даём.</p>
HTML,
                    'en' => <<<'HTML'
<p>Urine contains crystallizing compounds that bond deep into fabric or foam, which is why surface cleaning often isn't enough.</p>
<h2>Why it's especially tough</h2>
<p>If the stain is old, it may have reached the seat foam, which needs deeper treatment.</p>
<h2>Our approach</h2>
<p>We work with a method matched to the source as part of our <a href="/{locale}/services/auto-care/odor-removal">odor removal service</a>, but we don't give a full guarantee for very old or deep cases.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Մեզի հոտի հեռացում ավտոնստատեղից | Servio',
                    'ru' => 'Удалить запах мочи из автомобильного сиденья | Servio',
                    'en' => 'Remove Urine Smell from a Car Seat | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է մեզի հոտը դժվար հեռացվող ավտոմեքենայի նստատեղից, և ինչ մոտեցում ենք առաջարկում։',
                    'ru' => 'Почему запах мочи сложно вывести из сиденья автомобиля и какой подход мы предлагаем.',
                    'en' => 'Why urine smell is hard to remove from a car seat and the approach we take.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Ազդու՞մ է բծի տարիքը արդյունքի վրա', 'answer' => 'Այո, ինչքան հին է բիծը, այնքան ավելի դժվար է ամբողջական վերացումը։']],
                    'ru' => [['question' => 'Влияет ли возраст пятна на результат', 'answer' => 'Да, чем старше пятно, тем сложнее добиться полного устранения.']],
                    'en' => [['question' => 'Does the stain\'s age affect the result', 'answer' => 'Yes — the older the stain, the harder full removal becomes.']],
                ],
                'related_services' => ['odor-removal', 'fabric-seat-cleaning'],
            ],
            [
                'slug' => 'how-to-clean-pet-hair-from-car-interior',
                'category' => 'auto-care',
                'tags' => ['կենդանու մազի հեռացում մեքենայից', 'убрать шерсть животных из машины', 'remove pet hair from car'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Ինչպես մաքրել ավտոմեքենայի սրահը կենդանիների մազից',
                    'ru' => 'Как очистить салон автомобиля от шерсти животных',
                    'en' => 'How to Clean Pet Hair from a Car Interior',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է կենդանու մազը դժվար հեռացվող, և ինչ գործիքներ են արդյունավետ։',
                    'ru' => 'Почему шерсть животных трудно удалить и какие инструменты эффективны.',
                    'en' => 'Why pet hair is stubborn, and which tools actually work.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Կենդանու մազը մեխանիկորեն «կպչում» է տեքստիլի մանրաթելերին, ինչի պատճառով սովորական փոշեկուլը հաճախ բավարար չէ։</p>
<h2>Ինչու է դժվար</h2>
<p>Մազիկները ներթափանցում են գործվածքի հյուսվածքի մեջ և ստատիկ լիցքի պատճառով պահվում մակերեսին։</p>
<h2>Ինչ է աշխատում</h2>
<p><a href="/{locale}/services/auto-care/pet-hair-removal">Մասնագիտացված գործիքները</a> մեխանիկորեն անջատում են մազիկները մակերեսից, որից հետո կատարվում է հզոր փոշեկուլում։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Шерсть животных механически «цепляется» за волокна ткани, поэтому обычный пылесос часто не справляется.</p>
<h2>Почему это сложно</h2>
<p>Волоски проникают в структуру ткани и удерживаются на поверхности за счёт статического заряда.</p>
<h2>Что работает</h2>
<p><a href="/{locale}/services/auto-care/pet-hair-removal">Специализированные инструменты</a> механически отделяют шерсть от поверхности, после чего проводится мощный пылесос.</p>
HTML,
                    'en' => <<<'HTML'
<p>Pet hair mechanically hooks onto fabric fibers, which is why a regular vacuum often isn't enough.</p>
<h2>Why it's stubborn</h2>
<p>Strands work into the fabric's weave and cling to the surface through static charge.</p>
<h2>What actually works</h2>
<p><a href="/{locale}/services/auto-care/pet-hair-removal">Specialized tools</a> mechanically lift hair from the surface, followed by a high-power vacuum pass.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Կենդանու մազի հեռացում մեքենայից | Servio',
                    'ru' => 'Убрать шерсть животных из машины | Servio',
                    'en' => 'Remove Pet Hair from a Car | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է կենդանու մազը դժվար հեռացվող ավտոմեքենայից, և ինչ գործիքներ են արդյունավետ։',
                    'ru' => 'Почему шерсть животных сложно убрать из машины и какие инструменты эффективны.',
                    'en' => 'Why pet hair is hard to remove from a car and which tools work.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Կվերանա՞ ամբողջ մազը', 'answer' => 'Մեծ մասը հեռացվում է, բայց շատ խորը ներծծված դեպքում կարող են մնալ առանձին մազիկներ։']],
                    'ru' => [['question' => 'Уйдёт ли вся шерсть', 'answer' => 'Большая часть удаляется, но при сильном въедании отдельные волоски могут остаться.']],
                    'en' => [['question' => 'Will all the hair be removed', 'answer' => 'Most of it comes out, though a few strands may remain in heavily embedded cases.']],
                ],
                'related_services' => ['pet-hair-removal', 'fabric-seat-cleaning'],
            ],
            [
                'slug' => 'fabric-vs-leather-cleaning-difference',
                'category' => 'auto-care',
                'tags' => ['տեքստիլի և կաշվի մաքրման տարբերություն', 'разница между чисткой ткани и кожи', 'fabric vs leather cleaning'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Տեքստիլի և կաշվի քիմմաքրում. ո՞րն է տարբերությունը',
                    'ru' => 'Химчистка ткани и кожи: в чём разница',
                    'en' => 'Fabric vs. Leather Cleaning: What\'s the Difference',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու են տեքստիլն ու կաշին պահանջում տարբեր մեթոդներ և միջոցներ։',
                    'ru' => 'Почему ткань и кожа требуют разных методов и средств.',
                    'en' => 'Why fabric and leather each need a different method and products.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Տեքստիլն ու կաշին այնքան տարբեր կառուցվածք ունեն, որ նույն մեթոդը երկուսի համար հազվադեպ է հարմար։</p>
<h2>Տեքստիլ</h2>
<p><a href="/{locale}/services/auto-care/fabric-seat-cleaning">Տեքստիլ նստատեղերը</a> սովորաբար մշակվում են էքստրակցիայի եղանակով, որը թույլ է տալիս խորը մաքրել մանրաթելերը։</p>
<h2>Կաշի</h2>
<p><a href="/{locale}/services/auto-care/leather-seat-cleaning">Կաշին</a> պահանջում է ավելի մեղմ մոտեցում և կերակրող միջոց, հակառակ դեպքում կարող է չորանալ ու ճաքճքել։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Ткань и кожа настолько различаются по структуре, что один и тот же метод редко подходит обеим.</p>
<h2>Ткань</h2>
<p><a href="/{locale}/services/auto-care/fabric-seat-cleaning">Тканевые сиденья</a> обычно обрабатываются методом экстракции, который позволяет глубоко очистить волокна.</p>
<h2>Кожа</h2>
<p><a href="/{locale}/services/auto-care/leather-seat-cleaning">Кожа</a> требует более бережного подхода и кондиционера, иначе может пересохнуть и потрескаться.</p>
HTML,
                    'en' => <<<'HTML'
<p>Fabric and leather differ so much in structure that the same method rarely suits both.</p>
<h2>Fabric</h2>
<p><a href="/{locale}/services/auto-care/fabric-seat-cleaning">Fabric seats</a> are usually cleaned by extraction, which reaches deep into the fibers.</p>
<h2>Leather</h2>
<p><a href="/{locale}/services/auto-care/leather-seat-cleaning">Leather</a> needs a gentler approach plus a conditioner, or it can dry out and crack.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Տեքստիլի և կաշվի մաքրման տարբերությունը | Servio',
                    'ru' => 'Разница между чисткой ткани и кожи | Servio',
                    'en' => 'Fabric vs. Leather Cleaning Difference | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու են ավտոմեքենայի տեքստիլն ու կաշին պահանջում տարբեր մաքրման մեթոդներ։',
                    'ru' => 'Почему ткань и кожа в автомобиле требуют разных методов чистки.',
                    'en' => 'Why car fabric and leather need different cleaning methods.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եմ նույն միջոցն օգտագործել երկուսի համար', 'answer' => 'Ոչ խորհուրդ ենք տալիս, քանի որ նյութերի կառուցվածքը տարբեր է և կարող է վնասվել։']],
                    'ru' => [['question' => 'Можно ли использовать одно средство для обоих', 'answer' => 'Не рекомендуем — структура материалов разная, и можно повредить поверхность.']],
                    'en' => [['question' => 'Can I use the same product for both', 'answer' => 'We don\'t recommend it — the materials differ in structure and one could damage the other.']],
                ],
                'related_services' => ['fabric-seat-cleaning', 'leather-seat-cleaning'],
            ],
            [
                'slug' => 'is-it-safe-to-clean-car-ceiling',
                'category' => 'auto-care',
                'tags' => ['ավտոմեքենայի առաստաղի մաքրում', 'чистка потолка автомобиля', 'clean car ceiling'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Կարելի՞ է անվտանգ մաքրել ավտոմեքենայի առաստաղը',
                    'ru' => 'Можно ли безопасно чистить потолок автомобиля',
                    'en' => 'Is It Safe to Clean a Car\'s Ceiling',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է առաստաղը զգայուն և ինչ ռիսկեր կան սխալ մաքրման դեպքում։',
                    'ru' => 'Почему потолок чувствителен и какие риски есть при неправильной чистке.',
                    'en' => 'Why the ceiling is sensitive, and the risks of cleaning it the wrong way.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Առաստաղի ծածկույթը սոսնձված է հիմքին, և ավելորդ խոնավությունը կարող է թուլացնել սոսինձը՝ հանգեցնելով կախվելու։</p>
<h2>Ինչու է ռիսկային</h2>
<p>Ինտենսիվ շփումը կամ մեծ քանակությամբ ջուրը կարող են վնասել հին կամ արդեն թուլացած ծածկույթը։</p>
<h2>Մեր մոտեցումը</h2>
<p><a href="/{locale}/services/auto-care/interior-detailing">Դետեյլինգի ծառայության</a> շրջանակում օգտագործում ենք նվազագույն խոնավությամբ մեթոդներ և նախապես գնահատում ենք ծածկույթի վիճակը։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Потолочная обивка приклеена к основе, и избыточная влага может ослабить клей, из-за чего обивка провиснет.</p>
<h2>Почему это рискованно</h2>
<p>Интенсивное трение или большое количество воды могут повредить старую или уже ослабленную обивку.</p>
<h2>Наш подход</h2>
<p>В рамках <a href="/{locale}/services/auto-care/interior-detailing">услуги детейлинга</a> мы используем методы с минимальным увлажнением и заранее оцениваем состояние обивки.</p>
HTML,
                    'en' => <<<'HTML'
<p>Headliner fabric is glued to a backing, and too much moisture can weaken that adhesive, causing sagging.</p>
<h2>Why it's risky</h2>
<p>Heavy scrubbing or excess water can damage an old or already-loose headliner.</p>
<h2>Our approach</h2>
<p>As part of our <a href="/{locale}/services/auto-care/interior-detailing">detailing service</a>, we use low-moisture methods and assess the headliner's condition beforehand.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Ավտոմեքենայի առաստաղի անվտանգ մաքրում | Servio',
                    'ru' => 'Безопасная чистка потолка автомобиля | Servio',
                    'en' => 'Safely Cleaning a Car Ceiling | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է ավտոմեքենայի առաստաղի մաքրումը ռիսկային և ինչպես ենք մոտենում դրան անվտանգ կերպով։',
                    'ru' => 'Почему чистка потолка автомобиля рискованна и как мы подходим к этому безопасно.',
                    'en' => 'Why cleaning a car ceiling carries risk, and how we approach it safely.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ է առաստաղը կախվել մաքրումից հետո', 'answer' => 'Հին կամ արդեն թուլացած ծածկույթի դեպքում ռիսկ կա, որը քննարկվում է աշխատանքից առաջ։']],
                    'ru' => [['question' => 'Может ли потолок провиснуть после чистки', 'answer' => 'При старой или уже ослабленной обивке есть риск, который обсуждается заранее.']],
                    'en' => [['question' => 'Can the ceiling sag after cleaning', 'answer' => 'There\'s some risk with an old or already-loose headliner, which is discussed beforehand.']],
                ],
                'related_services' => ['ceiling-cleaning', 'interior-detailing'],
            ],
            [
                'slug' => 'how-to-prepare-car-for-sale',
                'category' => 'auto-care',
                'tags' => ['մեքենայի պատրաստում վաճառքի', 'подготовка авто к продаже', 'prepare car for sale'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Ինչպես նախապատրաստել ավտոմեքենան վաճառքի',
                    'ru' => 'Как подготовить автомобиль к продаже',
                    'en' => 'How to Prepare a Car for Sale',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է սրահի մաքրությունն ազդում առաջին տպավորության վրա և ինչ արժե ստուգել առաջինը։',
                    'ru' => 'Почему чистота салона влияет на первое впечатление и что стоит проверить в первую очередь.',
                    'en' => 'Why a clean interior shapes first impressions, and what to check first.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Գնորդի առաջին տպավորությունը մեծապես կախված է սրահի տեսքից և հոտից։</p>
<h2>Ինչից սկսել</h2>
<p>Ստուգեք նստատեղերը, հատակը և հոտը։ <a href="/{locale}/services/auto-care/car-interior-cleaning">Ամբողջական քիմմաքրումը</a> հաճախ բավարար է թարմ տեսք ապահովելու համար։</p>
<h2>Ինչի վրա կենտրոնանալ</h2>
<p>Հատուկ ուշադրություն դարձրեք տեսանելի բծերին և հոտի աղբյուրներին, քանի որ դրանք ամենաառաջինն են նկատվում զննման ժամանակ։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Первое впечатление покупателя во многом зависит от внешнего вида и запаха салона.</p>
<h2>С чего начать</h2>
<p>Проверьте сиденья, пол и запах. <a href="/{locale}/services/auto-care/car-interior-cleaning">Полная химчистка</a> часто даёт достаточно свежий вид.</p>
<h2>На что обратить внимание</h2>
<p>Особое внимание уделите видимым пятнам и источникам запаха — именно на них покупатель обращает внимание в первую очередь.</p>
HTML,
                    'en' => <<<'HTML'
<p>A buyer's first impression depends heavily on how the interior looks and smells.</p>
<h2>Where to start</h2>
<p>Check the seats, floor and any odor. A <a href="/{locale}/services/auto-care/car-interior-cleaning">full interior clean</a> is often enough for a fresh look.</p>
<h2>What to focus on</h2>
<p>Pay special attention to visible stains and odor sources — those are what a buyer notices first.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Մեքենայի պատրաստում վաճառքից առաջ | Servio',
                    'ru' => 'Подготовка автомобиля к продаже | Servio',
                    'en' => 'Preparing a Car for Sale | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է սրահի մաքրությունը կարևոր վաճառքից առաջ, և ինչից սկսել նախապատրաստումը։',
                    'ru' => 'Почему чистота салона важна перед продажей и с чего начать подготовку.',
                    'en' => 'Why a clean interior matters before a sale and where to start.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Ավելացնու՞մ է սա վաճառքի գինը', 'answer' => 'Ամբողջական երաշխիք չկա, սակայն մաքուր սրահը դրական է ազդում առաջին տպավորության վրա։']],
                    'ru' => [['question' => 'Повышает ли это цену продажи', 'answer' => 'Полной гарантии нет, но чистый салон положительно влияет на первое впечатление.']],
                    'en' => [['question' => 'Does this raise the sale price', 'answer' => 'There\'s no full guarantee, but a clean interior helps first impressions.']],
                ],
                'related_services' => ['pre-sale-detailing', 'car-interior-cleaning'],
            ],
            [
                'slug' => 'how-often-to-clean-car-interior',
                'category' => 'auto-care',
                'tags' => ['որքան հաճախ մաքրել մեքենան', 'как часто делать химчистку салона', 'how often clean car interior'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Որքա՞ն հաճախ է պետք սրահի քիմմաքրում',
                    'ru' => 'Как часто нужно делать химчистку салона',
                    'en' => 'How Often Should You Clean Your Car\'s Interior',
                ],
                'excerpt' => [
                    'hy' => 'Ինչ գործոններից է կախված հաճախականությունը՝ առօրյա օգտագործումից մինչև կենդանիների առկայություն։',
                    'ru' => 'От каких факторов зависит частота — от повседневного использования до наличия животных.',
                    'en' => 'What frequency depends on — from daily use to whether pets ride along.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Հստակ ժամանակացույց չկա բոլորի համար, սակայն կան ուղենիշներ։</p>
<h2>Ինչից է կախված</h2>
<p>Օգտագործման հաճախականությունը, կենդանիների առկայությունը, երեխաների ներկայությունը և սննդի օգտագործումը մեքենայում ազդում են աղտոտման արագության վրա։</p>
<h2>Ընդհանուր ուղենիշ</h2>
<p>Ակտիվ օգտագործման դեպքում <a href="/{locale}/services/auto-care/car-interior-cleaning">ամբողջական քիմմաքրում</a> հաճախ խորհուրդ է տրվում մի քանի ամիսը մեկ, իսկ տեղային մաքրում՝ ըստ անհրաժեշտության։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Единого расписания для всех нет, но есть общие ориентиры.</p>
<h2>От чего зависит</h2>
<p>Частота использования, наличие животных, присутствие детей и еда в машине влияют на скорость загрязнения.</p>
<h2>Общий ориентир</h2>
<p>При активном использовании <a href="/{locale}/services/auto-care/car-interior-cleaning">полную химчистку</a> часто рекомендуют раз в несколько месяцев, а локальную чистку — по необходимости.</p>
HTML,
                    'en' => <<<'HTML'
<p>There's no single schedule that fits everyone, but there are general guidelines.</p>
<h2>What it depends on</h2>
<p>How often the car is used, whether pets ride along, kids in the car and eating inside all affect how fast it gets dirty.</p>
<h2>A general guideline</h2>
<p>For heavy use, a <a href="/{locale}/services/auto-care/car-interior-cleaning">full interior clean</a> every few months is often suggested, with local cleaning as needed in between.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Որքա՞ն հաճախ մաքրել մեքենայի սրահը | Servio',
                    'ru' => 'Как часто делать химчистку салона | Servio',
                    'en' => 'How Often to Clean a Car Interior | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչ գործոններից է կախված սրահի քիմմաքրման հաճախականությունը։',
                    'ru' => 'От каких факторов зависит частота химчистки салона автомобиля.',
                    'en' => 'What determines how often a car interior needs cleaning.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Կենդանիների առկայությունը փոխու՞մ է հաճախականությունը', 'answer' => 'Այո, մազի և հոտի կուտակման պատճառով հաճախականությունը սովորաբար մեծանում է։']],
                    'ru' => [['question' => 'Меняется ли частота при наличии животных', 'answer' => 'Да, из-за накопления шерсти и запаха частоту обычно увеличивают.']],
                    'en' => [['question' => 'Does having pets change the frequency', 'answer' => 'Yes, hair and odor build-up usually means cleaning more often.']],
                ],
                'related_services' => ['car-interior-cleaning', 'pet-hair-removal'],
            ],
            [
                'slug' => 'detailing-vs-regular-car-wash',
                'category' => 'auto-care',
                'tags' => ['դետեյլինգ և մեքենայի լվացում', 'детейлинг и обычная мойка', 'detailing vs car wash'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Դետեյլինգ և սովորական մեքենալվացում. ո՞րն է տարբերությունը',
                    'ru' => 'Детейлинг и обычная мойка: в чём разница',
                    'en' => 'Detailing vs. a Regular Car Wash: What\'s the Difference',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է դետեյլինգը ավելի խորը գործընթաց, քան սովորական մեքենալվացումը։',
                    'ru' => 'Почему детейлинг — более глубокий процесс, чем обычная мойка.',
                    'en' => 'Why detailing goes much deeper than a standard car wash.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Սովորական մեքենալվացումը կենտրոնանում է արագ, մակերեսային մաքրման վրա, մինչդեռ դետեյլինգը շատ ավելի մանրակրկիտ մոտեցում է։</p>
<h2>Ինչ է տարբերում</h2>
<p><a href="/{locale}/services/auto-care/interior-detailing">Դետեյլինգը</a> ներառում է դժվարամատչելի հատվածների, կարերի և նյութերի առանձին խնամք, մինչդեռ սովորական լվացումը սահմանափակվում է ընդհանուր մակերեսով։</p>
<h2>Ե՞րբ է հարկավոր դետեյլինգ</h2>
<p>Եթե սրահն ունի կուտակված կեղտ, հոտ կամ պահանջում է ավելի բարձր մակարդակի արդյունք, դետեյլինգը հարմար ընտրություն է։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Обычная мойка ориентирована на быструю, поверхностную чистку, тогда как детейлинг — куда более тщательный подход.</p>
<h2>В чём разница</h2>
<p><a href="/{locale}/services/auto-care/interior-detailing">Детейлинг</a> включает отдельную проработку труднодоступных мест, швов и материалов, тогда как обычная мойка ограничивается общей поверхностью.</p>
<h2>Когда нужен детейлинг</h2>
<p>Если в салоне накопилась грязь, запах или нужен более высокий уровень результата, детейлинг — подходящий выбор.</p>
HTML,
                    'en' => <<<'HTML'
<p>A regular car wash focuses on a quick, surface-level clean, while detailing is a far more thorough approach.</p>
<h2>What sets them apart</h2>
<p><a href="/{locale}/services/auto-care/interior-detailing">Detailing</a> covers separate work on hard-to-reach areas, seams and individual materials, while a regular wash stays at surface level.</p>
<h2>When you need detailing</h2>
<p>If the interior has built-up grime, odor, or you want a higher standard of result, detailing is the right choice.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Դետեյլինգ և մեքենալվացում՝ տարբերությունը | Servio',
                    'ru' => 'Детейлинг и обычная мойка: разница | Servio',
                    'en' => 'Detailing vs. Regular Car Wash | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է ավտոդետեյլինգն ավելի խորը գործընթաց, քան սովորական լվացումը։',
                    'ru' => 'Почему автодетейлинг — более глубокий процесс, чем обычная мойка.',
                    'en' => 'Why auto detailing is a deeper process than a regular car wash.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Կարո՞ղ եմ համատեղել երկուսը', 'answer' => 'Այո, սովորական լվացումն ու սրահի դետեյլինգը կարող են լրացնել միմյանց։']],
                    'ru' => [['question' => 'Можно ли совмещать оба', 'answer' => 'Да, обычная мойка и детейлинг салона могут дополнять друг друга.']],
                    'en' => [['question' => 'Can I combine both', 'answer' => 'Yes, a regular wash and interior detailing can complement each other.']],
                ],
                'related_services' => ['interior-detailing', 'car-interior-cleaning'],
            ],
            [
                'slug' => 'how-to-care-for-leather-interior',
                'category' => 'auto-care',
                'tags' => ['կաշվե սրահի խնամք', 'уход за кожаным салоном', 'leather interior care'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Ինչպես խնամել կաշվե սրահը',
                    'ru' => 'Как ухаживать за кожаным салоном',
                    'en' => 'How to Care for a Leather Interior',
                ],
                'excerpt' => [
                    'hy' => 'Հիմնական սկզբունքները, որոնք օգնում են պահպանել կաշվի տեսքը ժամանակի ընթացքում։',
                    'ru' => 'Основные принципы, которые помогают сохранить внешний вид кожи со временем.',
                    'en' => 'The basics that help leather keep its look over time.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Կաշին կենդանի նյութ է իր բնույթով և կարիք ունի կանոնավոր, բայց մեղմ խնամքի։</p>
<h2>Ինչ է կարևոր</h2>
<p>Խուսափեք ագրեսիվ քիմիական միջոցներից և ուղիղ արևի ազդեցությունից, որոնք արագացնում են չորացումն ու ճաքճքումը։</p>
<h2>Ինչպես ենք մենք օգնում</h2>
<p><a href="/{locale}/services/auto-care/leather-seat-cleaning">Կաշվե նստատեղերի մաքրման ծառայության</a> շրջանակում կիրառում ենք կերակրող միջոցներ, որոնք օգնում են պահպանել կաշվի ճկունությունը։</p>
HTML,
                    'ru' => <<<'HTML'
<p>Кожа — материал, который требует регулярного, но бережного ухода.</p>
<h2>Что важно</h2>
<p>Избегайте агрессивной химии и прямого солнца, которые ускоряют пересыхание и растрескивание.</p>
<h2>Как мы помогаем</h2>
<p>В рамках <a href="/{locale}/services/auto-care/leather-seat-cleaning">услуги по чистке кожаных сидений</a> мы применяем кондиционеры, которые помогают сохранить эластичность кожи.</p>
HTML,
                    'en' => <<<'HTML'
<p>Leather needs regular but gentle care to stay in good shape.</p>
<h2>What matters</h2>
<p>Avoid harsh chemicals and direct sun exposure, both of which speed up drying and cracking.</p>
<h2>How we help</h2>
<p>As part of our <a href="/{locale}/services/auto-care/leather-seat-cleaning">leather seat cleaning service</a>, we apply conditioners that help keep the leather supple.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Կաշվե ավտոսրահի խնամք | Servio',
                    'ru' => 'Уход за кожаным салоном автомобиля | Servio',
                    'en' => 'Caring for a Leather Car Interior | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Հիմնական սկզբունքներ՝ ավտոմեքենայի կաշվե սրահի տեսքը երկար պահպանելու համար։',
                    'ru' => 'Основные принципы ухода за кожаным салоном автомобиля.',
                    'en' => 'The basics of keeping a leather car interior looking good.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Որքա՞ն հաճախ է պետք կերակրող միջոց կիրառել', 'answer' => 'Հաճախականությունը կախված է օգտագործումից և կլիմայից, ընդհանուր խորհուրդը տրվում է զննումից հետո։']],
                    'ru' => [['question' => 'Как часто нужно наносить кондиционер', 'answer' => 'Частота зависит от использования и климата, общая рекомендация даётся после осмотра.']],
                    'en' => [['question' => 'How often should conditioner be applied', 'answer' => 'It depends on use and climate — a general recommendation is given after inspection.']],
                ],
                'related_services' => ['leather-seat-cleaning', 'interior-detailing'],
            ],
            [
                'slug' => 'why-odor-can-return-after-diy-cleaning',
                'category' => 'auto-care',
                'tags' => ['ինչու է հոտը վերադառնում ինքնուրույն մաքրումից հետո', 'почему запах возвращается после самостоятельной чистки', 'odor returns after diy cleaning'],
                'status' => 'draft',
                'author_name' => 'Servio',
                'title' => [
                    'hy' => 'Ինչու կարող է հոտը վերադառնալ ինքնուրույն մաքրումից հետո',
                    'ru' => 'Почему запах может вернуться после самостоятельной чистки',
                    'en' => 'Why Odor Can Return After DIY Cleaning',
                ],
                'excerpt' => [
                    'hy' => 'Ինչու է մակերեսային մաքրումը հաճախ ժամանակավոր լուծում, և ինչ է անհրաժեշտ ավելի կայուն արդյունքի համար։',
                    'ru' => 'Почему поверхностная чистка часто даёт временный эффект и что нужно для более стойкого результата.',
                    'en' => 'Why surface cleaning is often only a temporary fix, and what a longer-lasting result needs.',
                ],
                'content' => [
                    'hy' => <<<'HTML'
<p>Ինքնուրույն մաքրման ժամանակ հաճախ մշակվում է միայն տեսանելի մակերեսը, մինչդեռ հոտի աղբյուրը մնում է նյութի խորքում։</p>
<h2>Ինչու է հոտը վերադառնում</h2>
<p>Եթե աղբյուրը լիովին չի հեռացվել, խոնավության կամ ջերմության ազդեցության տակ հոտը կարող է կրկին երևալ։</p>
<h2>Ինչ է անհրաժեշտ</h2>
<p><a href="/{locale}/services/auto-care/odor-removal">Հոտերի վերացման մասնագիտական ծառայությունը</a> սկսվում է աղբյուրի ախտորոշումից, ոչ միայն մակերեսային բուրավորումից։</p>
HTML,
                    'ru' => <<<'HTML'
<p>При самостоятельной чистке часто обрабатывается только видимая поверхность, а источник запаха остаётся в глубине материала.</p>
<h2>Почему запах возвращается</h2>
<p>Если источник не устранён полностью, под воздействием влаги или тепла запах может проявиться снова.</p>
<h2>Что нужно на самом деле</h2>
<p><a href="/{locale}/services/auto-care/odor-removal">Профессиональная услуга по удалению запахов</a> начинается с диагностики источника, а не просто маскирует его ароматизатором.</p>
HTML,
                    'en' => <<<'HTML'
<p>DIY cleaning often treats only the visible surface, leaving the odor source deep in the material.</p>
<h2>Why the smell comes back</h2>
<p>If the source isn't fully addressed, heat or humidity can bring the odor back later.</p>
<h2>What actually helps</h2>
<p>A <a href="/{locale}/services/auto-care/odor-removal">professional odor removal service</a> starts by diagnosing the source, rather than just masking it with a fragrance.</p>
HTML,
                ],
                'seo_title' => [
                    'hy' => 'Ինչու է հոտը վերադառնում ինքնուրույն մաքրումից հետո | Servio',
                    'ru' => 'Почему запах возвращается после чистки | Servio',
                    'en' => 'Why Odor Returns After DIY Cleaning | Servio',
                ],
                'seo_description' => [
                    'hy' => 'Ինչու է մակերեսային, ինքնուրույն մաքրումը հաճախ ժամանակավոր, և ինչ է անհրաժեշտ կայուն արդյունքի համար։',
                    'ru' => 'Почему поверхностная самостоятельная чистка часто временна и что нужно для стойкого результата.',
                    'en' => 'Why surface-level DIY cleaning is often temporary and what a lasting result needs.',
                ],
                'faq' => [
                    'hy' => [['question' => 'Օդաբուրավորիչը բավարա՞ր է', 'answer' => 'Ոչ, այն ժամանակավորապես քողարկում է հոտը, բայց չի վերացնում աղբյուրը։']],
                    'ru' => [['question' => 'Достаточно ли ароматизатора', 'answer' => 'Нет, он временно маскирует запах, но не устраняет источник.']],
                    'en' => [['question' => 'Is an air freshener enough', 'answer' => 'No, it temporarily masks the smell but doesn\'t address the source.']],
                ],
                'related_services' => ['odor-removal', 'interior-detailing'],
            ],
        ];

        foreach ($posts as $post) {
            $serviceSlugs = $post['related_services'];
            unset($post['related_services']);

            $record = Post::query()->updateOrCreate(['slug' => $post['slug']], $post);

            $serviceIds = Service::query()->whereIn('slug', $serviceSlugs)->pluck('id');
            $record->services()->sync($serviceIds);
        }
    }
}

<?php

namespace App\Enums;

use App\Enums\DataTableSection as Section;

/**
 * What a column of a business's own table means, whatever it's called: the
 * analytics and the AI assistant read a row by these roles (when it
 * happened, what was sold, how many, at what price), not by column names,
 * so "Գին", "Цена", "Price" or "gin" are all the price once the role is set.
 *
 * Each role has a label in Armenian, Russian and English, the words a file
 * header may use for it (for matching and guessing), and the column types
 * it can have. A column with no header words of a role can still be
 * recognised by its values (ColumnValues): "14:30" is a time, "iPhone 15
 * Pro" a model, "Китай" a country.
 */
enum ColumnRole: string
{
    // When.
    case Date = 'date';
    /** The time of day of a row whose date has none ("14:30"), kept in its own column. */
    case Time = 'time';
    /** How long the service took, in minutes. */
    case Duration = 'duration';

    // What.
    case Item = 'item';
    /** A product's or service's group; in orders the kind of service, in expenses the kind of expense (one of the business's ExpenseCategory names). */
    case Category = 'category';
    case Model = 'model';
    case Brand = 'brand';
    case Sku = 'sku';
    case Country = 'country';
    case Material = 'material';
    case Color = 'color';
    case Size = 'size';

    // How many, for how much.
    case Quantity = 'quantity';
    case Unit = 'unit';
    case UnitPrice = 'unit_price';
    /** What the row is worth in the end: the final price, after a discount. */
    case Amount = 'amount';
    case Discount = 'discount';
    case DiscountPercent = 'discount_percent';
    case Cost = 'cost';
    /** Paid to an intermediary for the sale: comes off the profit. */
    case Commission = 'commission';
    case DeliveryFee = 'delivery_fee';
    case Tax = 'tax';

    // How, who and where.
    case Payment = 'payment';
    case Channel = 'channel';
    case Status = 'status';
    case DeliveryMethod = 'delivery_method';
    case Performer = 'performer';
    case Intermediary = 'intermediary';
    /** Who the goods come from; in expenses, who was paid. */
    case Supplier = 'supplier';
    case City = 'city';
    case Branch = 'branch';
    /** What an expense or order was for: an address being cleaned, a project. */
    case Project = 'project';
    case OrderNumber = 'order_number';

    // The buyer. Gender and age are counted only as shares (App\Audience\Demographics), never row by row.
    case Gender = 'gender';
    case Age = 'age';
    case Customer = 'customer';
    case Phone = 'phone';
    case Email = 'email';
    case Address = 'address';
    case Note = 'note';

    /** Units a quantity can be in (the options of a new unit column). */
    public const UNITS = ['հատ', 'կգ', 'գ', 'լ', 'մ', 'մ²'];

    /** @return array{hy: string, ru: string, en: string} */
    public function labels(?Section $section = null): array
    {
        return match ($this) {
            self::Date => ['hy' => 'Ամսաթիվ', 'ru' => 'Дата', 'en' => 'Date'],
            self::Time => ['hy' => 'Ժամ', 'ru' => 'Время', 'en' => 'Time'],
            self::Duration => ['hy' => 'Տևողություն (րոպե)', 'ru' => 'Длительность (мин)', 'en' => 'Duration (min)'],
            self::Item => match ($section) {
                Section::Orders => ['hy' => 'Ծառայություն', 'ru' => 'Услуга', 'en' => 'Service'],
                Section::Expenses => ['hy' => 'Ինչի համար', 'ru' => 'За что', 'en' => 'Description'],
                default => ['hy' => 'Ապրանք', 'ru' => 'Товар', 'en' => 'Product'],
            },
            self::Category => match ($section) {
                Section::Orders => ['hy' => 'Ծառայության տեսակ', 'ru' => 'Вид услуги', 'en' => 'Service type'],
                Section::Expenses => ['hy' => 'Ծախսի տեսակ', 'ru' => 'Категория расхода', 'en' => 'Expense category'],
                default => ['hy' => 'Կատեգորիա', 'ru' => 'Категория', 'en' => 'Category'],
            },
            self::Model => ['hy' => 'Մոդել', 'ru' => 'Модель', 'en' => 'Model'],
            self::Brand => ['hy' => 'Ապրանքանիշ', 'ru' => 'Бренд', 'en' => 'Brand'],
            self::Sku => ['hy' => 'Ապրանքի կոդ', 'ru' => 'Артикул', 'en' => 'SKU'],
            self::Country => ['hy' => 'Արտադրող երկիր', 'ru' => 'Страна', 'en' => 'Country of origin'],
            self::Material => ['hy' => 'Նյութ', 'ru' => 'Материал', 'en' => 'Material'],
            self::Color => ['hy' => 'Գույն', 'ru' => 'Цвет', 'en' => 'Color'],
            self::Size => ['hy' => 'Չափս', 'ru' => 'Размер', 'en' => 'Size'],
            self::Quantity => ['hy' => 'Քանակ', 'ru' => 'Количество', 'en' => 'Quantity'],
            self::Unit => ['hy' => 'Չափման միավոր', 'ru' => 'Единица', 'en' => 'Unit'],
            self::UnitPrice => ['hy' => 'Գին', 'ru' => 'Цена', 'en' => 'Price'],
            self::Amount => ['hy' => 'Գումար', 'ru' => 'Сумма', 'en' => 'Total'],
            self::Discount => ['hy' => 'Զեղչ', 'ru' => 'Скидка', 'en' => 'Discount'],
            self::DiscountPercent => ['hy' => 'Զեղչ, %', 'ru' => 'Скидка, %', 'en' => 'Discount, %'],
            self::Cost => ['hy' => 'Ինքնարժեք', 'ru' => 'Себестоимость', 'en' => 'Unit cost'],
            self::Commission => ['hy' => 'Միջնորդավճար', 'ru' => 'Комиссия', 'en' => 'Commission'],
            self::DeliveryFee => ['hy' => 'Առաքման վճար', 'ru' => 'Стоимость доставки', 'en' => 'Delivery fee'],
            self::Tax => ['hy' => 'Հարկ (ԱԱՀ)', 'ru' => 'Налог (НДС)', 'en' => 'Tax (VAT)'],
            self::Payment => ['hy' => 'Վճարման եղանակ', 'ru' => 'Способ оплаты', 'en' => 'Payment method'],
            self::Channel => ['hy' => 'Աղբյուր', 'ru' => 'Канал', 'en' => 'Channel'],
            self::Status => ['hy' => 'Կարգավիճակ', 'ru' => 'Статус', 'en' => 'Status'],
            self::DeliveryMethod => ['hy' => 'Առաքման եղանակ', 'ru' => 'Способ доставки', 'en' => 'Delivery method'],
            self::Performer => match ($section) {
                Section::Sales => ['hy' => 'Վաճառող', 'ru' => 'Продавец', 'en' => 'Seller'],
                Section::Expenses => ['hy' => 'Ով է ծախսել', 'ru' => 'Кто потратил', 'en' => 'Spent by'],
                default => ['hy' => 'Կատարող', 'ru' => 'Исполнитель', 'en' => 'Performer'],
            },
            self::Intermediary => ['hy' => 'Միջնորդ', 'ru' => 'Посредник', 'en' => 'Intermediary'],
            self::Supplier => $section === Section::Expenses
                ? ['hy' => 'Ում է վճարվել', 'ru' => 'Получатель', 'en' => 'Payee']
                : ['hy' => 'Մատակարար', 'ru' => 'Поставщик', 'en' => 'Supplier'],
            self::City => ['hy' => 'Քաղաք', 'ru' => 'Город', 'en' => 'City'],
            self::Branch => ['hy' => 'Մասնաճյուղ', 'ru' => 'Филиал', 'en' => 'Branch'],
            self::Project => ['hy' => 'Օբյեկտ', 'ru' => 'Объект', 'en' => 'Project'],
            self::OrderNumber => $section === Section::Expenses
                ? ['hy' => 'Փաստաթղթի համար', 'ru' => 'Номер документа', 'en' => 'Document No.']
                : ['hy' => 'Պատվերի համար', 'ru' => 'Номер заказа', 'en' => 'Order No.'],
            self::Gender => ['hy' => 'Սեռ', 'ru' => 'Пол', 'en' => 'Gender'],
            self::Age => ['hy' => 'Տարիք', 'ru' => 'Возраст', 'en' => 'Age'],
            self::Customer => ['hy' => 'Հաճախորդ', 'ru' => 'Клиент', 'en' => 'Customer'],
            self::Phone => ['hy' => 'Հեռախոս', 'ru' => 'Телефон', 'en' => 'Phone'],
            self::Email => ['hy' => 'Էլ. փոստ', 'ru' => 'E-mail', 'en' => 'Email'],
            self::Address => ['hy' => 'Հասցե', 'ru' => 'Адрес', 'en' => 'Address'],
            self::Note => ['hy' => 'Մեկնաբանություն', 'ru' => 'Комментарий', 'en' => 'Note'],
        };
    }

    /**
     * Header words of each role, in Armenian, Russian, English and Armenian
     * typed in Latin letters ("gin", "qanak"), checked in this order: the
     * first that matches wins. So "Итоговая цена" is the amount, not the
     * price, "Цена со скидкой" the amount, not the discount, "Delivery
     * cost" the delivery fee, "Страна производитель" the country, not the
     * brand, "Адрес доставки" the address, "Номер телефона" the phone.
     *
     * @return list<array{0: self, 1: string}>
     */
    private static function rules(): array
    {
        return [
            // The final price, before the words of a discount or a price take it.
            [self::Amount, 'վերջնական|զեղչված|ընդհանուր\s*գին|окончательн|итогов|к\s*оплате|со\s*скидк|после\s*скидк|с\s*учетом\s*скидк|final|grand\s*total|after\s*discount|with\s*discount|total\s*price|verjnakan'],
            [self::DiscountPercent, '(զեղչ|скидк|discount).*%|%.*(զեղչ|скидк|discount)|զեղչի\s*տոկոս|процент\s*скидк|discount\s*(rate|percent)'],
            [self::Discount, 'զեղչ|скидк|discount|zexch|zeghch'],
            [self::DeliveryFee, '(առաքման|առաքում)\s*(վճար|արժեք|գին)|(доставк\S*)\s*(стоим|цена|сумм|плат)|(стоимост\S*|цена|сумма|плата)\s*(за\s*)?доставк|delivery\s*(fee|cost|price|charge)|shipping\s*(fee|cost|price)|araqman\s*(gin|vchar)'],
            [self::Commission, 'միջնորդավճար|комисси|commission|агентск\S*\s*(вознагр|плат)|вознагражден|mijnordavchar'],
            [self::Tax, 'աահ|հարկ|налог|ндс|\bvat\b|\btax'],
            [self::Cost, 'ինքնարժեք|себестоим|закуп|входящ\S*\s*цен|purchase|cost\s*price|unit\s*cost|buy\s*price|inqnarjeq'],
            [self::Duration, 'տևողություն|տեւողություն|длительн|продолжит|duration|minutes|րոպե|минут'],
            // Only a header that is just "time": "Дата и время" is a date.
            [self::Time, '^(ժամ|ժամը|ժամի|время|час|time|jam|начало|սկիզբ)$|время\s*(начала|визита|записи|приема)|appointment\s*time|start\s*time|^ժամը\s'],
            [self::Date, 'ամսաթիվ|ամսաթ|օր$|ժամ|date|time|day|when|created|дата|время|день|amsativ|jam'],
            [self::Model, 'մոդել|модел|model|modeli'],
            [self::Country, 'երկիր|страна|country|origin|происхожд|импорт|import|made\s*in|erkir'],
            [self::Brand, 'ապրանքանիշ|բրենդ|марка|бренд|brand|производител|manufacturer|արտադրող|\bmake\b'],
            [self::Email, 'e-?mail|էլ\.?\s*փոստ|էլփոստ|почта|мейл|имейл'],
            [self::Phone, 'հեռախոս|phone|tel\b|mobile|телефон|моб|herakhos|heraxos'],
            [self::Address, 'հասցե|address|адрес|куда|hasce'],
            [self::OrderNumber, '^№|^#|^n[o°]\.?$|^номер|номер\s*(заказа|чека|документа|счета|накладной)|order\s*(no|number|id|#)|invoice|receipt|^чек|накладн|պատվերի\s*համար|փաստաթղթի\s*համար|^համար|^id$|կտրոն|հաշիվ'],
            [self::Sku, 'артикул|^арт\.?$|\bsku\b|vendor\s*code|article|код\s*товара|^код$|barcode|штрихкод|շտրիխ|ապրանքի\s*կոդ|^կոդ$|^code$'],
            [self::Material, 'նյութ|материал|material|состав|composition|ткань|fabric|из\s*чего|կտոր|nyut'],
            [self::Color, 'գույն|цвет|colou?r|guyn'],
            [self::Size, 'չափս|размер|\bsize|габарит|chaps'],
            [self::Amount, 'գումար|ընդամենը|հասույթ|եկամուտ|վճարված|total|amount|sum|revenue|paid|сумма|итог|выручк|оплачено|gumar|yndamen'],
            [self::UnitPrice, 'գին|արժեք|price|cost|rate|цена|стоимост|прайс|^gin|\bgin\b|gine|arjeq'],
            [self::Quantity, 'քանակ|^հատ|qty|quantity|pcs|count|кол-?во|количеств|^шт|вес|weight|քաշ|qanak|kanak|qanaky'],
            [self::Unit, 'միավոր|չափման|unit|uom|measure|единиц|ед\.?\s*изм|^ед\.?$|mia?vor'],
            [self::Payment, 'վճարման|վճարում|payment|pay\b|оплат|расчет|vcharum'],
            [self::Channel, 'աղբյուր|ալիք|կայք|source|channel|utm|канал|источник|откуда|axbyur|aghbyur'],
            [self::Status, 'կարգավիճակ|վիճակ|status|state|статус|состояние|kargavichak'],
            [self::DeliveryMethod, 'առաքում|առաքման|доставк|delivery|shipping|самовывоз|araqum'],
            [self::City, 'քաղաք|մարզ|համայնք|город|регион|област|\bcity|region|town|marz|qaxaq'],
            [self::Branch, 'մասնաճյուղ|филиал|branch|торгов\S*\s*точк|^точка|location|masnachyux'],
            [self::Supplier, 'մատակարար|поставщик|supplier|vendor|получател|payee|^кому|контрагент|ում\s*է\s*վճարվ|matakarar|provider'],
            [self::Intermediary, 'միջնորդ|մենթոր|посредник|агент|наставник|куратор|партн[её]р|реферал|рекомендовал|intermediary|agent|broker|mentor|referr|partner|curator|mijnord'],
            [self::Category, 'կատեգորիա|ծախսի\s*տեսակ|ծառայության\s*տեսակ|հոդված|category|категори|статья|вид\s*расход|вид\s*услуг|тип\s*услуг|вид\s*деятельност|вид\s*работ|тип\s*работ|service\s*type|type\s*of\s*service|^տեսակ$|^тип$|^вид$|^type$|^группа|^group|kategori'],
            [self::Performer, 'կատարող|աշխատակից|վարպետ|մասնագետ|վաճառող|исполнител|сотрудник|мастер|работник|продав|кассир|менеджер|ответствен|кто\s*потратил|ով\s*է\s*ծախսել|performer|employee|staff|worker|seller|cashier|manager|responsible|spent\s*by|katarox|katarogh|ashxatakic'],
            [self::Gender, 'սեռ|^пол$|^пол\s|gender|\bsex\b|^ser$'],
            [self::Age, 'տարիք|возраст|^age$|\bage\b|tariq'],
            [self::Project, 'օբյեկտ|նախագիծ|объект|проект|project'],
            [self::Item, 'ապրանք|ծառայություն|անվանում|product|item|service|goods|товар|услуг|наименован|продукт|apranq|tsarayutyun|carayutyun|ինչի\s*համար|նկարագրություն|за\s*что|назначени|описани|description'],
            [self::Customer, 'հաճախորդ|գնորդ|պատվիրատու|անուն|client|customer|buyer|клиент|покупател|заказчик|имя|фио|hachakhord|hachaxord'],
            [self::Note, 'մեկնաբանություն|նշում|note|comment|remark|комментар|примечан|заметк'],
        ];
    }

    /** The role a header's words suggest, or null. */
    public static function guess(string $header): ?self
    {
        $header = mb_strtolower(trim($header));

        if ($header === '') {
            return null;
        }

        foreach (self::rules() as [$role, $pattern]) {
            if (preg_match('/'.$pattern.'/iu', $header)) {
                return $role;
            }
        }

        return null;
    }

    /** @return list<DataColumnType> the column types this role can have; the first is the default */
    public function types(): array
    {
        return match ($this) {
            self::Date => [DataColumnType::DateTime, DataColumnType::Date],
            self::Quantity, self::Duration, self::DiscountPercent => [DataColumnType::Number],
            self::UnitPrice, self::Amount, self::Cost, self::Discount, self::Commission, self::DeliveryFee, self::Tax => [DataColumnType::Money, DataColumnType::Number],
            self::Unit, self::Payment, self::Channel, self::Status, self::Gender, self::DeliveryMethod => [DataColumnType::Select, DataColumnType::Text],
            // Years ("34") or a group from a file ("25-34").
            self::Age => [DataColumnType::Number, DataColumnType::Select, DataColumnType::Text],
            self::Time, self::Phone, self::Email, self::Address, self::Note, self::OrderNumber, self::Sku => [DataColumnType::Text],
            // The employee's name (the admin offers the business's employees), a
            // category's name (the business's categories), a model, a country...
            self::Item, self::Customer, self::Performer, self::Category, self::Model, self::Brand, self::Country,
            self::Material, self::Color, self::Size, self::Supplier, self::Intermediary, self::City, self::Branch,
            self::Project => [DataColumnType::Text, DataColumnType::Select],
        };
    }

    /** @return list<string> options of a new list column of this role */
    public function defaultOptions(): array
    {
        return match ($this) {
            self::Unit => self::UNITS,
            self::Payment => ['Կանխիկ', 'Քարտ', 'Փոխանցում'],
            self::Channel => ['Instagram', 'Facebook', 'Կայք', 'Հեռախոս', 'Խանութ'],
            self::Status => ['Նոր', 'Կատարված', 'Չեղարկված'],
            self::Gender => ['Արական', 'Իգական'],
            self::DeliveryMethod => ['Առաքում', 'Ինքնատար', 'Փոստ'],
            default => [],
        };
    }

    /** About the buyer: kept out of breakdowns and of anything sent to the AI. */
    public function isPersonal(): bool
    {
        return in_array($this, [self::Customer, self::Phone, self::Email, self::Address], true);
    }

    /**
     * Money a row has besides its total, summed on its own (discounts given,
     * commissions paid, delivery charged, tax).
     *
     * @return list<self>
     */
    public static function extraMoney(): array
    {
        return [self::Discount, self::Commission, self::DeliveryFee, self::Tax];
    }

    /**
     * Roles that describe what was sold, done or paid for, or by whom and
     * where: grouped by in the analytics ("by model", "by supplier").
     *
     * @return list<self>
     */
    public static function details(): array
    {
        return [self::Category, self::Model, self::Brand, self::Country, self::Material, self::Color, self::Size, self::Supplier, self::Intermediary, self::DeliveryMethod, self::City, self::Branch, self::Project, self::Performer];
    }

    /**
     * What the role means, in English, for the AI assistant.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::Date => 'when the row happened',
            self::Time => 'time of day of the row (its date column has no time)',
            self::Duration => 'how long the service took, in minutes',
            self::Item => 'what was sold or done (the product or service); in expenses, what was paid for',
            self::Category => 'group of the product; in orders the kind of service; in expenses the expense category',
            self::Model => 'model of the product (e.g. a phone model a case fits)',
            self::Brand => 'brand or manufacturer',
            self::Sku => 'product code / article number',
            self::Country => 'country the product comes from',
            self::Material => 'what the product is made of',
            self::Color => 'colour',
            self::Size => 'size',
            self::Quantity => 'how many (in the row\'s unit)',
            self::Unit => 'unit of the quantity (pieces, kg...)',
            self::UnitPrice => 'price of one unit before discount',
            self::Amount => 'what the row is worth in the end (final price after discount); the row\'s amount',
            self::Discount => 'discount given, in money',
            self::DiscountPercent => 'discount given, in percent',
            self::Cost => 'unit cost (purchase price of one unit)',
            self::Commission => 'commission paid to an intermediary for this sale (comes off the profit)',
            self::DeliveryFee => 'delivery charge',
            self::Tax => 'tax (VAT) included',
            self::Payment => 'payment method',
            self::Channel => 'where the customer came from (Instagram, site...)',
            self::Status => 'status of the order',
            self::DeliveryMethod => 'delivery or pick-up',
            self::Performer => 'employee who did it (seller, master); in expenses who spent the money',
            self::Intermediary => 'intermediary, agent, mentor or partner who brought the sale',
            self::Supplier => 'supplier of the goods; in expenses who was paid',
            self::City => 'city or region',
            self::Branch => 'branch or point of sale',
            self::Project => 'object or project the row was for',
            self::OrderNumber => 'order, receipt or document number',
            self::Gender => 'buyer\'s gender',
            self::Age => 'buyer\'s age',
            self::Customer, self::Phone, self::Email, self::Address => 'buyer\'s details (never shared)',
            self::Note => 'free note',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

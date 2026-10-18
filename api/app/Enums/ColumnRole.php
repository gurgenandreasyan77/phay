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
 * it can have.
 */
enum ColumnRole: string
{
    case Date = 'date';
    case Item = 'item';
    case Quantity = 'quantity';
    case Unit = 'unit';
    case UnitPrice = 'unit_price';
    case Amount = 'amount';
    case Cost = 'cost';
    case Payment = 'payment';
    case Channel = 'channel';
    case Status = 'status';
    case Performer = 'performer';
    case Customer = 'customer';
    case Phone = 'phone';
    case Address = 'address';
    case Note = 'note';

    /** Units a quantity can be in (the options of a new unit column). */
    public const UNITS = ['հատ', 'կգ', 'գ', 'լ', 'մ', 'մ²'];

    /** @return array{hy: string, ru: string, en: string} */
    public function labels(?Section $section = null): array
    {
        return match ($this) {
            self::Date => ['hy' => 'Ամսաթիվ', 'ru' => 'Дата', 'en' => 'Date'],
            self::Item => $section === Section::Orders
                ? ['hy' => 'Ծառայություն', 'ru' => 'Услуга', 'en' => 'Service']
                : ['hy' => 'Ապրանք', 'ru' => 'Товар', 'en' => 'Product'],
            self::Quantity => ['hy' => 'Քանակ', 'ru' => 'Количество', 'en' => 'Quantity'],
            self::Unit => ['hy' => 'Չափման միավոր', 'ru' => 'Единица', 'en' => 'Unit'],
            self::UnitPrice => ['hy' => 'Գին', 'ru' => 'Цена', 'en' => 'Price'],
            self::Amount => ['hy' => 'Գումար', 'ru' => 'Сумма', 'en' => 'Total'],
            self::Cost => ['hy' => 'Ինքնարժեք', 'ru' => 'Себестоимость', 'en' => 'Unit cost'],
            self::Payment => ['hy' => 'Վճարման եղանակ', 'ru' => 'Способ оплаты', 'en' => 'Payment method'],
            self::Channel => ['hy' => 'Աղբյուր', 'ru' => 'Канал', 'en' => 'Channel'],
            self::Status => ['hy' => 'Կարգավիճակ', 'ru' => 'Статус', 'en' => 'Status'],
            self::Performer => ['hy' => 'Կատարող', 'ru' => 'Исполнитель', 'en' => 'Performer'],
            self::Customer => ['hy' => 'Հաճախորդ', 'ru' => 'Клиент', 'en' => 'Customer'],
            self::Phone => ['hy' => 'Հեռախոս', 'ru' => 'Телефон', 'en' => 'Phone'],
            self::Address => ['hy' => 'Հասցե', 'ru' => 'Адрес', 'en' => 'Address'],
            self::Note => ['hy' => 'Մեկնաբանություն', 'ru' => 'Комментарий', 'en' => 'Note'],
        };
    }

    /**
     * Words of a header meaning this role, in Armenian, Russian, English and
     * Armenian typed in Latin letters ("gin", "qanak"). Checked in the order
     * of guess(): "Total price" is the amount, "Цена за кг" the price, not a
     * unit, "Product name" the item, not the customer.
     */
    private function pattern(): string
    {
        return match ($this) {
            self::Date => 'ամսաթիվ|ամսաթ|օր$|ժամ|date|time|day|when|created|дата|время|день|amsativ|jam',
            self::Cost => 'ինքնարժեք|себестоим|закуп|purchase|cost\\s*price|unit\\s*cost|inqnarjeq',
            self::UnitPrice => 'գին|արժեք|price|cost|rate|цена|стоимост|прайс|^gin|\bgin\b|gine|arjeq',
            self::Amount => 'գումար|ընդամենը|հասույթ|եկամուտ|վճարված|total|amount|sum|revenue|paid|сумма|итог|выручк|оплачено|gumar|yndamen',
            self::Quantity => 'քանակ|^հատ|qty|quantity|pcs|count|кол-?во|количеств|^шт|вес|weight|քաշ|qanak|kanak|qanaky',
            self::Unit => 'միավոր|չափ|unit|uom|measure|единиц|ед\.?\s*изм|^ед\.?$|mia?vor',
            self::Payment => 'վճարման|վճարում|payment|pay\b|оплат|расчет|vcharum',
            self::Channel => 'աղբյուր|ալիք|կայք|source|channel|utm|канал|источник|откуда|axbyur|aghbyur',
            self::Status => 'կարգավիճակ|վիճակ|status|state|статус|состояние|kargavichak',
            self::Performer => 'կատարող|աշխատակից|վարպետ|մասնագետ|исполнител|сотрудник|мастер|работник|performer|employee|staff|worker|katarox|katarogh|ashxatakic',
            self::Phone => 'հեռախոս|phone|tel\b|mobile|телефон|моб|herakhos|heraxos',
            self::Address => 'հասցե|address|адрес|hasce',
            self::Customer => 'հաճախորդ|գնորդ|պատվիրատու|անուն|client|customer|buyer|клиент|покупател|заказчик|имя|фио|hachakhord|hachaxord',
            self::Item => 'ապրանք|ծառայություն|անվանում|product|item|service|goods|sku|товар|услуг|наименован|продукт|apranq|tsarayutyun|carayutyun',
            self::Note => 'մեկնաբանություն|նշում|note|comment|remark|комментар|примечан|заметк',
        };
    }

    /** The role a header's words suggest, or null. */
    public static function guess(string $header): ?self
    {
        $header = mb_strtolower(trim($header));

        foreach ([self::Date, self::Cost, self::Amount, self::UnitPrice, self::Quantity, self::Unit, self::Payment, self::Channel, self::Status, self::Performer, self::Phone, self::Address, self::Item, self::Customer, self::Note] as $role) {
            if (preg_match('/'.$role->pattern().'/iu', $header)) {
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
            self::Quantity => [DataColumnType::Number],
            self::UnitPrice, self::Amount, self::Cost => [DataColumnType::Money, DataColumnType::Number],
            self::Unit, self::Payment, self::Channel, self::Status => [DataColumnType::Select, DataColumnType::Text],
            self::Item, self::Customer, self::Phone, self::Address, self::Note => [DataColumnType::Text, DataColumnType::Select],
            // The employee's name; the admin offers the business's employees.
            self::Performer => [DataColumnType::Text, DataColumnType::Select],
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
            default => [],
        };
    }

    /** About the buyer: kept out of breakdowns and of anything sent to the AI. */
    public function isPersonal(): bool
    {
        return in_array($this, [self::Customer, self::Phone, self::Address], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

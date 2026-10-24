<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Expenses\ExpenseCategories;

/**
 * The role a column's values suggest when its header says nothing (a file
 * with "Column 3", or a header in a language the words don't cover):
 * "14:30" is a time, "name@mail.am" an email, "iPhone 15 Pro" a model,
 * "Apple" a brand, "Китай" a country, "Կանխիկ" a payment method,
 * "Instagram" a channel, "Կոմունալ" an expense category.
 *
 * Most of a column's different values must fit (MIN_SHARE), so a column of
 * product names that happens to hold one "Red" isn't taken for a colour.
 */
final class ColumnValues
{
    private const MIN_SHARE = 0.7;

    private const SAMPLE = 200;

    /** Words, lower case, in Armenian, Russian and English (and Armenian in Latin letters). */
    private const COUNTRIES = ['հայաստան', 'չինաստան', 'թուրքիա', 'ռուսաստան', 'իրան', 'վրաստան', 'իտալիա', 'գերմանիա', 'ֆրանսիա', 'իսպանիա', 'ամն', 'կորեա', 'ճապոնիա', 'հնդկաստան', 'վիետնամ', 'լեհաստան', 'արաբական էմիրություններ', 'աէմ', 'բելառուս', 'ղազախստան', 'ուկրաինա', 'անգլիա', 'մեծ բրիտանիա', 'армения', 'китай', 'турция', 'россия', 'рф', 'иран', 'грузия', 'италия', 'германия', 'франция', 'испания', 'сша', 'корея', 'южная корея', 'япония', 'индия', 'вьетнам', 'польша', 'оаэ', 'эмираты', 'беларусь', 'белоруссия', 'казахстан', 'украина', 'узбекистан', 'киргизия', 'великобритания', 'англия', 'тайвань', 'таиланд', 'малайзия', 'индонезия', 'египет', 'armenia', 'china', 'turkey', 'türkiye', 'russia', 'iran', 'georgia', 'italy', 'germany', 'france', 'spain', 'usa', 'us', 'united states', 'korea', 'south korea', 'japan', 'india', 'vietnam', 'poland', 'uae', 'belarus', 'kazakhstan', 'ukraine', 'uzbekistan', 'uk', 'united kingdom', 'england', 'taiwan', 'thailand', 'malaysia', 'indonesia', 'egypt', 'hayastan', 'chinastan', 'turqia', 'rusastan'];

    private const CITIES = ['երևան', 'երեւան', 'գյումրի', 'վանաձոր', 'աբովյան', 'էջմիածին', 'վաղարշապատ', 'հրազդան', 'կապան', 'արմավիր', 'արտաշատ', 'աշտարակ', 'դիլիջան', 'սևան', 'սեւան', 'գորիս', 'իջևան', 'մասիս', 'չարենցավան', 'ստեփանավան', 'կոտայք', 'արարատ', 'արագածոտն', 'շիրակ', 'լոռի', 'տավուշ', 'գեղարքունիք', 'վայոց ձոր', 'սյունիք', 'արաբկիր', 'կենտրոն', 'դավթաշեն', 'աջափնյակ', 'մալաթիա', 'շենգավիթ', 'էրեբունի', 'նոր նորք', 'ավան', 'քանաքեռ', 'ереван', 'гюмри', 'ванадзор', 'абовян', 'эчмиадзин', 'раздан', 'капан', 'армавир', 'арташат', 'аштарак', 'дилижан', 'севан', 'горис', 'иджеван', 'масис', 'чаренцаван', 'степанаван', 'котайк', 'арарат', 'арагацотн', 'ширак', 'лори', 'тавуш', 'гегаркуник', 'вайоц дзор', 'сюник', 'арабкир', 'кентрон', 'центр', 'давташен', 'аджапняк', 'малатия', 'шенгавит', 'эребуни', 'нор норк', 'аван', 'канакер', 'москва', 'yerevan', 'erevan', 'gyumri', 'vanadzor', 'abovyan', 'echmiadzin', 'hrazdan', 'kapan', 'armavir', 'artashat', 'ashtarak', 'dilijan', 'sevan', 'goris', 'ijevan', 'masis', 'kotayk', 'ararat', 'shirak', 'lori', 'tavush', 'syunik', 'arabkir', 'kentron', 'davtashen', 'ajapnyak', 'malatia', 'shengavit', 'erebuni', 'nor nork', 'avan'];

    private const COLORS = ['սև', 'սեւ', 'սպիտակ', 'կարմիր', 'կապույտ', 'երկնագույն', 'կանաչ', 'դեղին', 'նարնջագույն', 'մանուշակագույն', 'վարդագույն', 'մոխրագույն', 'շագանակագույն', 'ոսկեգույն', 'արծաթագույն', 'բեժ', 'черный', 'чёрный', 'белый', 'красный', 'синий', 'голубой', 'зеленый', 'зелёный', 'желтый', 'жёлтый', 'оранжевый', 'фиолетовый', 'розовый', 'серый', 'коричневый', 'золотой', 'серебристый', 'бежевый', 'прозрачный', 'бордовый', 'black', 'white', 'red', 'blue', 'light blue', 'green', 'yellow', 'orange', 'purple', 'violet', 'pink', 'grey', 'gray', 'brown', 'gold', 'silver', 'beige', 'transparent', 'clear', 'navy'];

    private const SIZES = ['xxs', 'xs', 's', 'm', 'l', 'xl', 'xxl', 'xxxl', '2xl', '3xl', '4xl', 's/m', 'm/l', 'l/xl'];

    private const MATERIALS = ['կաշի', 'էկոկաշի', 'բամբակ', 'սիլիկոն', 'պլաստիկ', 'փայտ', 'մետաղ', 'ապակի', 'բուրդ', 'մետաքս', 'кожа', 'экокожа', 'хлопок', 'силикон', 'пластик', 'дерево', 'металл', 'стекло', 'шерсть', 'шелк', 'шёлк', 'полиэстер', 'кожзам', 'велюр', 'замша', 'leather', 'eco leather', 'cotton', 'silicone', 'plastic', 'wood', 'metal', 'glass', 'wool', 'silk', 'polyester', 'suede', 'tpu', 'pu'];

    private const PAYMENTS = ['կանխիկ', 'քարտ', 'քարտով', 'փոխանցում', 'փոխանցումով', 'անկանխիկ', 'наличные', 'наличными', 'нал', 'карта', 'картой', 'безнал', 'безналичный', 'перевод', 'переводом', 'cash', 'card', 'transfer', 'bank transfer', 'idram', 'telcell', 'easypay', 'pos', 'online'];

    private const CHANNELS = ['instagram', 'insta', 'facebook', 'fb', 'tiktok', 'telegram', 'whatsapp', 'viber', 'google', 'youtube', 'list.am', 'olx', 'site', 'website', 'կայք', 'сайт', 'звонок', 'զանգ', 'рекомендация', 'առաջարկ', 'ծանոթ', 'знакомые', 'реклама', 'գովազդ'];

    private const UNITS = ['հատ', 'կգ', 'գ', 'լ', 'մ', 'մ²', 'шт', 'шт.', 'кг', 'г', 'гр', 'л', 'м', 'м2', 'pcs', 'pc', 'kg', 'g', 'l', 'm', 'm2'];

    private const GENDERS = ['արական', 'իգական', 'տղամարդ', 'կին', 'мужской', 'женский', 'муж', 'жен', 'м', 'ж', 'мужчина', 'женщина', 'male', 'female', 'man', 'woman', 'm', 'f'];

    private const STATUSES = ['նոր', 'կատարված', 'չեղարկված', 'ընթացքում', 'հաստատված', 'новый', 'новая', 'выполнен', 'выполнено', 'отменен', 'отменён', 'в работе', 'подтвержден', 'new', 'done', 'completed', 'cancelled', 'canceled', 'in progress', 'confirmed', 'pending'];

    private const DELIVERY = ['առաքում', 'ինքնատար', 'փոստ', 'հայփոստ', 'доставка', 'самовывоз', 'курьер', 'почта', 'delivery', 'pickup', 'pick-up', 'courier', 'post', 'shipping'];

    /** Makers whose names start a model ("iPhone 15 Pro", "Galaxy A54", "Redmi Note 12"). */
    private const MAKERS = 'iphone|ipad|airpods|galaxy|redmi|poco|xiaomi|mi|huawei|honor|oppo|vivo|realme|oneplus|pixel|nokia|motorola|moto|sony|xperia|tecno|infinix|samsung|apple|nothing|zte|lenovo|asus|acer|hp|dell|macbook|lg|bmw|mercedes|toyota|kia|hyundai|lada|vaz|opel|nissan|mazda|honda|audi|volkswagen|vw|ford|chevrolet';

    private const BRANDS = ['apple', 'samsung', 'xiaomi', 'huawei', 'honor', 'oppo', 'vivo', 'realme', 'oneplus', 'google', 'nokia', 'motorola', 'sony', 'tecno', 'infinix', 'nothing', 'zte', 'lenovo', 'asus', 'acer', 'hp', 'dell', 'lg', 'bosch', 'philips', 'nike', 'adidas', 'puma', 'zara', 'bmw', 'mercedes', 'toyota', 'kia', 'hyundai', 'lada', 'opel', 'nissan', 'mazda', 'honda', 'audi', 'volkswagen', 'ford', 'chevrolet'];

    /**
     * @param  list<string>  $values  the column's raw values from a file or the table
     * @param  array<string, true>  $taken  roles another column already has
     */
    public static function guess(array $values, ?DataTableSection $section = null, array $taken = []): ?ColumnRole
    {
        $values = array_slice(array_values(array_filter(array_map(fn ($v) => trim((string) $v), $values), fn ($v) => $v !== '')), 0, self::SAMPLE);

        if (count($values) < 2) {
            return null;
        }

        $lower = array_map(fn (string $v) => mb_strtolower(preg_replace('/\s+/u', ' ', $v)), $values);
        $distinct = array_values(array_unique($lower));
        $free = fn (ColumnRole $role) => ! isset($taken[$role->value]);

        $checks = [
            // Times of day: "9:00", "14:30"; not "12.50", which is a number.
            [ColumnRole::Time, fn (string $v) => (bool) preg_match('/^\d{1,2}:\d{2}(:\d{2})?(\s*[-–]\s*\d{1,2}:\d{2})?$/', $v)],
            [ColumnRole::Email, fn (string $v) => (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $v)],
            [ColumnRole::Phone, fn (string $v) => (bool) preg_match('/^(\+?374|0)[\s\-()]*\d{2}[\s\-()]*\d{2,3}[\s\-]*\d{2,3}[\s\-]*\d{0,3}$/', $v) || (bool) preg_match('/^\+\d[\d\s\-()]{7,}$/', $v)],
            [ColumnRole::Model, fn (string $v) => (bool) preg_match('/^('.self::MAKERS.')\b.*\d|^('.self::MAKERS.')\s+\S+/u', $v)],
            [ColumnRole::Brand, self::in(self::BRANDS)],
            [ColumnRole::Country, self::in(self::COUNTRIES)],
            [ColumnRole::City, self::in(self::CITIES)],
            [ColumnRole::Color, self::in(self::COLORS)],
            [ColumnRole::Size, fn (string $v) => in_array($v, self::SIZES, true)],
            [ColumnRole::Material, self::in(self::MATERIALS)],
            [ColumnRole::Payment, self::in(self::PAYMENTS)],
            [ColumnRole::Channel, self::in(self::CHANNELS)],
            [ColumnRole::Unit, fn (string $v) => in_array($v, self::UNITS, true)],
            [ColumnRole::Gender, fn (string $v) => in_array($v, self::GENDERS, true)],
            [ColumnRole::Status, self::in(self::STATUSES)],
            [ColumnRole::DeliveryMethod, self::in(self::DELIVERY)],
        ];

        if ($section === DataTableSection::Expenses) {
            $checks[] = [ColumnRole::Category, fn (string $v) => ExpenseCategories::known($v)];
        }

        foreach ($checks as [$role, $fits]) {
            if (! $free($role)) {
                continue;
            }

            $matching = count(array_filter($distinct, $fits));
            // A one-letter size or unit column needs more than one value to be sure.
            if ($matching >= 1 && $matching / count($distinct) >= self::MIN_SHARE && ($matching >= 2 || count($lower) >= 3)) {
                return $role;
            }
        }

        return null;
    }

    /** A value that is one of the words, or starts with one ("Китай (Гуанчжоу)"). */
    private static function in(array $words): callable
    {
        $set = array_flip($words);

        return function (string $value) use ($set): bool {
            $value = trim(preg_replace('/[.,;:!()"«»]+/u', ' ', $value));
            if (isset($set[$value])) {
                return true;
            }
            $first = explode(' ', $value)[0];

            return mb_strlen($first) > 2 && isset($set[$first]);
        };
    }
}

<?php

namespace App\Expenses;

use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

/**
 * The kinds of a business's expenses. Every business starts with the
 * ready-made ones below (made the first time they're needed) and adds its
 * own. A row's category cell is matched to a category by its name, or for
 * a ready-made one also by its Russian or English name and usual words, so
 * an imported "Налоги" or "Tax" is taxes; a value matching none is counted
 * as it is (in the profit) under its own name.
 */
final class ExpenseCategories
{
    /**
     * Ready-made categories: key => [Armenian name, counted in the net
     * profit, words that mean it]. Equipment, a loan repayment and what the
     * owner takes out aren't costs of running the business month to month,
     * so the net profit leaves them out (the cash left counts them).
     */
    public const DEFAULTS = [
        'taxes' => ['Հարկեր', true, 'հարկ|налог|tax|ндс|vat|շրջանառու|եկամտահարկ|harker|hark'],
        'salaries' => ['Աշխատավարձ', true, 'աշխատավարձ|зарплат|зп\b|оклад|salary|salaries|payroll|wage|ashxatavardz|ashkhatavardz'],
        'rent' => ['Վարձակալություն', true, 'վարձակալ|տարածքի\\s*վարձ|аренд|\\brent|lease|vardz'],
        'utilities' => ['Կոմունալ վճարներ', true, 'կոմունալ|էլեկտր|գազ|ջուր|коммунал|электр|газ\b|вода|utilit|electric|water|komunal'],
        'purchases' => ['Ապրանքի գնումներ', true, 'գնում|պաշար|закуп|товар|сырь|stock|purchase|inventory|gnum'],
        'materials' => ['Նյութեր և պարագաներ', true, 'նյութ|պարագա|քիմի|материал|расходник|химия|материалы|supplies|material|consumable|nyut'],
        'marketing' => ['Գովազդ և մարքեթինգ', true, 'գովազդ|մարքեթինգ|реклам|маркетинг|таргет|smm|advert|marketing|ads\b|govazd'],
        'transport' => ['Տրանսպորտ և վառելիք', true, 'տրանսպորտ|վառելիք|բենզին|տաքսի|транспорт|бензин|топлив|такси|доставк|transport|fuel|petrol|taxi|delivery'],
        'services' => ['Բանկ, հաշվապահ, ծրագրեր', true, 'բանկ|հաշվապահ|ծրագ|ինտերնետ|банк|бухгалт|интернет|связь|подписк|bank|accountant|accounting|software|internet|subscription'],
        'equipment' => ['Սարքավորումներ', false, 'սարքավորում|գործիք|оборудован|инструмент|техник|equipment|\\btools?\\b|machine'],
        'loan' => ['Վարկի մարում', false, 'վարկ|кредит|заем|займ|loan'],
        'owner' => ['Սեփականատիրոջ վերցրած գումար', false, 'սեփականատեր|дивиденд|владел|изъят|owner|dividend|withdraw'],
        'other' => ['Այլ ծախսեր', true, 'այլ|прочее|другое|разное|other|misc'],
    ];

    /** @return Collection<int, ExpenseCategory> the business's categories, ready-made ones made if missing */
    public static function all(): Collection
    {
        $categories = ExpenseCategory::query()->orderBy('sort_order')->orderBy('id')->get();
        $missing = array_diff(array_keys(self::DEFAULTS), $categories->pluck('key')->filter()->all());

        if ($missing === []) {
            return $categories;
        }

        $order = array_flip(array_keys(self::DEFAULTS));
        foreach ($missing as $key) {
            [$name, $inProfit] = self::DEFAULTS[$key];
            // A business's own category of the same name becomes the ready-made one.
            $own = $categories->first(fn (ExpenseCategory $c) => $c->key === null && Employee::normalize($c->name) === Employee::normalize($name));
            if ($own) {
                $own->update(['key' => $key]);
            } else {
                ExpenseCategory::create(['key' => $key, 'name' => $name, 'in_profit' => $inProfit, 'sort_order' => $order[$key]]);
            }
        }

        return ExpenseCategory::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Finds the category of a cell's value.
     *
     * @param  Collection<int, ExpenseCategory>  $categories
     * @return callable(mixed): ?ExpenseCategory
     */
    public static function matcher(Collection $categories): callable
    {
        $byName = $categories->keyBy(fn (ExpenseCategory $c) => Employee::normalize($c->name));
        $byKey = $categories->filter(fn (ExpenseCategory $c) => $c->key !== null)->keyBy('key');
        $found = [];

        return function ($value) use ($byName, $byKey, &$found): ?ExpenseCategory {
            if (! is_string($value) || trim($value) === '') {
                return null;
            }

            $name = Employee::normalize($value);
            if (array_key_exists($name, $found)) {
                return $found[$name];
            }

            $category = $byName->get($name);
            if ($category === null) {
                foreach (self::DEFAULTS as $key => [, , $words]) {
                    if ($key !== 'other' && $byKey->has($key) && preg_match('/'.$words.'/iu', $name)) {
                        $category = $byKey->get($key);
                        break;
                    }
                }
            }

            return $found[$name] = $category;
        };
    }

    /** @return Collection<int, DataTable> the expenses tables with a category column */
    public static function tables(): Collection
    {
        return DataTable::query()
            ->where('section', DataTableSection::Expenses)
            ->get()
            ->filter(fn (DataTable $t) => $t->roleColumn(ColumnRole::Category) !== null)
            ->values();
    }

    /** A category was renamed: rows that named it now name it the new way. */
    public static function rename(string $from, string $to): void
    {
        $old = Employee::normalize($from);

        foreach (self::tables() as $table) {
            $key = $table->roleColumn(ColumnRole::Category)->key;

            DataTableRow::query()->where('data_table_id', $table->id)->select(['id', 'data'])->chunkById(500, function ($rows) use ($key, $old, $to) {
                foreach ($rows as $row) {
                    $data = $row->data ?? [];
                    if (is_string($data[$key] ?? null) && Employee::normalize($data[$key]) === $old) {
                        $data[$key] = $to;
                        DataTableRow::query()->whereKey($row->id)->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            });
        }
    }
}

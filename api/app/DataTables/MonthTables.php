<?php

namespace App\DataTables;

use App\Models\DataTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tables of one month: many businesses keep a table per month ("Հոկտեմբեր
 * 2026", "october", "Октябрь"). An order added from the calendar goes to
 * the table of its date's month, created like the chosen one when there is
 * none yet, instead of landing in another month's table.
 *
 * A table's month comes from its name, or else from its rows: a table whose
 * rows are (nearly) all of one month is that month's table.
 */
final class MonthTables
{
    /** Share of a table's dated rows that must fall in one month to make it that month's table. */
    public const ROWS_SHARE = 0.8;

    /** Month names by language, nominative first: the one a new table's name gets. */
    private const NAMES = [
        'hy' => ['հունվար', 'փետրվար', 'մարտ', 'ապրիլ', 'մայիս', 'հունիս', 'հուլիս', 'օգոստոս', 'սեպտեմբեր', 'հոկտեմբեր', 'նոյեմբեր', 'դեկտեմբեր'],
        'ru' => ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'],
        'en' => ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'],
    ];

    /** Other forms a month is written in: "հոկտեմբերի", "октября", "Oct". */
    private const FORMS = [
        'hy' => ['հունվարի', 'փետրվարի', 'մարտի', 'ապրիլի', 'մայիսի', 'հունիսի', 'հուլիսի', 'օգոստոսի', 'սեպտեմբերի', 'հոկտեմբերի', 'նոյեմբերի', 'դեկտեմբերի'],
        'ru' => ['января|январе', 'февраля|феврале', 'марта|марте', 'апреля|апреле', 'мая|мае', 'июня|июне', 'июля|июле', 'августа|августе', 'сентября|сентябре', 'октября|октябре', 'ноября|ноябре', 'декабря|декабре'],
        'en' => ['jan', 'feb', 'mar', 'apr', '', 'jun', 'jul', 'aug', 'sep|sept', 'oct', 'nov', 'dec'],
    ];

    /**
     * The month ("Y-m") of each table that is a month's table, by id.
     *
     * @param  Collection<int, DataTable>  $tables
     * @return array<int, string>
     */
    public static function months(Collection $tables): array
    {
        $histograms = self::histograms($tables->modelKeys());
        $months = [];

        foreach ($tables as $table) {
            $month = self::monthOf($table, $histograms[$table->id] ?? []);
            if ($month !== null) {
                $months[$table->id] = $month;
            }
        }

        return $months;
    }

    /**
     * The table of the month ("Y-m") among those like the chosen one: the
     * chosen table itself when it isn't a month's table or is this month's,
     * another table of the same section already kept for this month, or a
     * new one with the chosen table's columns.
     *
     * @return array{0: DataTable, 1: bool} the table, and whether it was created
     */
    public static function forMonth(DataTable $chosen, string $month): array
    {
        $tables = DataTable::query()
            ->where('section', $chosen->section)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (DataTable $table) => $table->dateColumn() !== null);
        $months = self::months($tables->push($chosen)->unique('id'));

        $chosenMonth = $months[$chosen->id] ?? null;
        if ($chosenMonth === null || $chosenMonth === $month) {
            return [$chosen, false];
        }

        $candidates = $tables->filter(fn (DataTable $table) => ($months[$table->id] ?? null) === $month);
        if ($candidates->isNotEmpty()) {
            // A table built like the chosen one first: the form's fields fit it as they are.
            $keys = self::keys($chosen);

            return [$candidates->first(fn (DataTable $t) => self::keys($t) === $keys) ?? $candidates->first(), false];
        }

        $table = DataTable::create([
            'section' => $chosen->section,
            'name' => self::nameFor($chosen, $chosenMonth, $month),
            'description' => $chosen->description,
            'columns' => $chosen->columns,
            'sort_order' => (DataTable::query()->where('section', $chosen->section)->max('sort_order') ?? 0) + 1,
        ]);

        return [$table, true];
    }

    /**
     * Values typed for the columns of one table, for the columns of another:
     * by the column's key, else its role, else its name.
     *
     * @param  array<string, mixed>  $values
     * @return array{0: array<string, mixed>, 1: array<string, string>} the values, and where each came from (to key → from key)
     */
    public static function carry(DataTable $from, DataTable $to, array $values): array
    {
        $source = $from->columnDefinitions();
        $carried = [];
        $origins = [];

        foreach ($to->columnDefinitions() as $column) {
            $match = collect($source)->first(fn (DataColumn $c) => $c->key === $column->key)
                ?? ($column->role ? collect($source)->first(fn (DataColumn $c) => $c->role === $column->role) : null)
                ?? collect($source)->first(fn (DataColumn $c) => mb_strtolower(trim($c->label)) === mb_strtolower(trim($column->label)));

            if ($match !== null && array_key_exists($match->key, $values)) {
                $carried[$column->key] = $values[$match->key];
                $origins[$column->key] = $match->key;
            }
        }

        return [$carried, $origins];
    }

    /**
     * The month a table's name says, if it names exactly one: its number,
     * its year when the name has one, and how it is written there.
     *
     * @return array{month: int, year: ?int, lang: ?string, word: string}|null
     */
    public static function fromName(string $name): ?array
    {
        $found = [];

        foreach (self::NAMES as $lang => $names) {
            foreach ($names as $i => $nominative) {
                $forms = array_filter([$nominative, ...explode('|', self::FORMS[$lang][$i])]);
                $pattern = '/(?<!\p{L})('.implode('|', array_map(fn ($f) => preg_quote($f, '/'), $forms)).')(?!\p{L})/iu';
                if (preg_match($pattern, $name, $m)) {
                    $found[$i + 1] = ['month' => $i + 1, 'lang' => $lang, 'word' => $m[1]];
                }
            }
        }

        // "2026-09", "09.2026", "09/2026"
        if (preg_match('/(?<!\d)(20\d{2})[-.\/](0?[1-9]|1[0-2])(?!\d)/', $name, $m)) {
            $found[(int) $m[2]] ??= ['month' => (int) $m[2], 'lang' => null, 'word' => $m[0]];
        } elseif (preg_match('/(?<![\d.\/-])(0?[1-9]|1[0-2])[.\/](20\d{2})(?!\d)/', $name, $m)) {
            $found[(int) $m[1]] ??= ['month' => (int) $m[1], 'lang' => null, 'word' => $m[0]];
        }

        if (count($found) !== 1) {
            return null;
        }

        $year = preg_match('/(?<!\d)(20\d{2})(?!\d)/', $name, $m) ? (int) $m[1] : null;

        return [...array_values($found)[0], 'year' => $year];
    }

    /** "Սեպտեմբեր 2026", or the chosen table's name with its month changed. */
    public static function nameFor(DataTable $chosen, ?string $chosenMonth, string $month): string
    {
        [$year, $number] = array_map('intval', explode('-', $month));
        $named = self::fromName($chosen->name);

        if ($named === null || $named['lang'] === null) {
            return mb_convert_case(self::NAMES['hy'][$number - 1], MB_CASE_TITLE)." {$year}";
        }

        $word = self::NAMES[$named['lang']][$number - 1];
        $word = match (true) {
            $named['word'] === mb_strtoupper($named['word']) => mb_strtoupper($word),
            $named['word'] === mb_strtolower($named['word']) => $word,
            default => mb_convert_case($word, MB_CASE_TITLE),
        };
        $name = preg_replace('/(?<!\p{L})'.preg_quote($named['word'], '/').'(?!\p{L})/u', $word, $chosen->name, 1);

        if ($named['year'] !== null) {
            return preg_replace('/(?<!\d)'.$named['year'].'(?!\d)/', (string) $year, $name);
        }

        // "october" next to "september" of another year would be ambiguous.
        return $chosenMonth !== null && (int) substr($chosenMonth, 0, 4) !== $year ? "{$name} {$year}" : $name;
    }

    /** @param  array<string, int>  $histogram  "Y-m" → rows */
    private static function monthOf(DataTable $table, array $histogram): ?string
    {
        arsort($histogram);
        $top = array_key_first($histogram);
        $named = self::fromName($table->name);

        if ($named !== null) {
            $year = $named['year']
                ?? ($top !== null ? (int) substr($top, 0, 4) : null)
                ?? (int) ($table->created_at ?? now())->format('Y');

            return sprintf('%04d-%02d', $year, $named['month']);
        }

        $total = array_sum($histogram);

        return $top !== null && $histogram[$top] / $total >= self::ROWS_SHARE ? $top : null;
    }

    /**
     * Dated rows of each table by month.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, int>>
     */
    private static function histograms(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', occurred_at)"
            : "to_char(occurred_at, 'YYYY-MM')";

        $histograms = [];
        DB::table('data_table_rows')
            ->whereIn('data_table_id', $ids)
            ->whereNotNull('occurred_at')
            ->selectRaw("data_table_id, {$month} as month, count(*) as total")
            ->groupBy('data_table_id', DB::raw($month))
            ->get()
            ->each(function ($row) use (&$histograms) {
                $histograms[(int) $row->data_table_id][$row->month] = (int) $row->total;
            });

        return $histograms;
    }

    /** @return list<string> */
    private static function keys(DataTable $table): array
    {
        return array_map(fn (DataColumn $c) => $c->key, $table->columnDefinitions());
    }
}

<?php

namespace App\DataTables;

use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Models\DataTable;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Pennant\Feature;

/**
 * Figures of a business's own tables, whatever columns they have: rows and
 * amounts per period (by the table's first date column), sums of number
 * columns, and the most frequent values of the other columns. Feeds the
 * analytics screen and the AI assistant.
 *
 * Like BusinessAnalytics, rows are aggregated in PHP: a small business's
 * table has thousands of rows, not millions.
 */
class TableAnalytics
{
    /** Rows read per table at most (the latest ones). */
    private const MAX_ROWS = 50000;

    private const TOP_VALUES = 10;

    /** Columns about the buyer (contacts, names) stay out of AI prompts and breakdowns. */
    public const PERSONAL_LABELS = '/հեռախոս|phone|tel\b|телефон|e-?mail|էլ\.?\s*փոստ|почта|հասցե|address|адрес|հաճախորդ|գնորդ|client|customer|buyer|клиент|покупатель|заказчик|անուն\s*ազգանուն|фио|instagram|insta|facebook|telegram|whatsapp|viber|\bnick|\buser/iu';

    private const QUANTITY_LABELS = '/քանակ|հատ|qty|quantity|pcs|count|кол-?во|количество|шт/iu';

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * Tables of the sections whose module is on.
     *
     * @return Collection<int, DataTable>
     */
    public function tables(): Collection
    {
        $sections = collect(DataTableSection::cases())
            ->filter(fn (DataTableSection $s) => Feature::active($s->feature()->value))
            ->map(fn (DataTableSection $s) => $s->value);

        return DataTable::query()->whereIn('section', $sections)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(DataTable $table, int $days): array
    {
        $columns = collect($table->columnDefinitions());
        $date = $columns->first(fn (DataColumn $c) => $c->type === DataColumnType::Date);
        $amount = $this->amount($columns);

        $now = CarbonImmutable::now($this->tenants->current()->timezone ?: config('app.timezone'))->startOfDay();
        $start = $now->subDays($days - 1);
        $previousStart = $start->subDays($days);

        $rows = $table->rows()->latest('id')->limit(self::MAX_ROWS)->pluck('data')
            ->map(fn ($data) => is_array($data) ? $data : (json_decode($data, true) ?? []))
            ->map(fn (array $data) => [
                'data' => $data,
                'date' => $date ? $this->date($data[$date->key] ?? null, $now->timezone) : null,
                'amount' => $amount ? $amount['value']($data) : null,
            ]);

        $current = $date ? $rows->filter(fn ($r) => $r['date'] && $r['date'] >= $start && $r['date'] <= $now) : $rows;
        $previous = $date ? $rows->filter(fn ($r) => $r['date'] && $r['date'] >= $previousStart && $r['date'] < $start) : collect();

        return [
            'table' => ['id' => $table->id, 'name' => $table->name, 'section' => $table->section->value],
            'columns' => $columns->map(fn (DataColumn $c) => ['label' => $c->label, 'type' => $c->type->value])->all(),
            'dateColumn' => $date?->label,
            'amountColumn' => $amount['label'] ?? null,
            'period' => ['days' => $days, 'from' => $start->toDateString(), 'to' => $now->toDateString()],
            'rows' => [
                'total' => $table->rows()->count(),
                'withoutDate' => $date ? $rows->whereNull('date')->count() : null,
                // Without a date column there is no period: "current" is every row.
                'current' => $current->count(),
                'previous' => $date ? $previous->count() : null,
            ],
            'amount' => $amount ? [
                'current' => $this->round($current->sum('amount')),
                'previous' => $date ? $this->round($previous->sum('amount')) : null,
                'average' => $current->whereNotNull('amount')->isEmpty() ? null : $this->round($current->whereNotNull('amount')->avg('amount')),
            ] : null,
            'numbers' => $columns
                ->filter(fn (DataColumn $c) => $c->type->isNumeric())
                ->map(fn (DataColumn $c) => $this->numberStats($c, $current, $previous, (bool) $date))
                ->values()
                ->all(),
            'series' => $date ? $this->series($current, $start, $now, $days > 90 ? 'month' : 'day') : [],
            'breakdowns' => $columns
                ->filter(fn (DataColumn $c) => in_array($c->type, [DataColumnType::Text, DataColumnType::Select, DataColumnType::Boolean], true))
                ->reject(fn (DataColumn $c) => self::isPersonal($c->label))
                ->map(fn (DataColumn $c) => $this->breakdown($c, $current))
                ->filter(fn (array $b) => $b['values'] !== [])
                ->values()
                ->all(),
        ];
    }

    /**
     * The latest rows, by column label, without columns about the buyer.
     *
     * @return list<array<string, mixed>>
     */
    public function recentRows(DataTable $table, int $limit = 30): array
    {
        $columns = collect($table->columnDefinitions())->reject(fn (DataColumn $c) => self::isPersonal($c->label));

        return $table->rows()->latest('id')->limit($limit)->pluck('data')
            ->map(fn ($data) => is_array($data) ? $data : (json_decode($data, true) ?? []))
            ->map(fn (array $data) => $columns
                ->mapWithKeys(fn (DataColumn $c) => [$c->label => $data[$c->key] ?? null])
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->all())
            ->values()
            ->all();
    }

    public static function isPersonal(string $label): bool
    {
        return (bool) preg_match(self::PERSONAL_LABELS, $label);
    }

    /**
     * What a row is worth: price × quantity when the table has both a money
     * and a quantity column, else the first money column.
     *
     * @param  Collection<int, DataColumn>  $columns
     * @return array{label: string, value: callable(array): (int|float|null)}|null
     */
    private function amount(Collection $columns): ?array
    {
        $money = $columns->first(fn (DataColumn $c) => $c->type === DataColumnType::Money);
        if (! $money) {
            return null;
        }

        $quantity = $columns->first(fn (DataColumn $c) => $c->type === DataColumnType::Number && preg_match(self::QUANTITY_LABELS, $c->label));
        $number = fn ($v) => is_int($v) || is_float($v) ? $v : null;

        if ($quantity) {
            return [
                'label' => "{$money->label} × {$quantity->label}",
                'value' => function (array $data) use ($money, $quantity, $number) {
                    $price = $number($data[$money->key] ?? null);

                    // A row without a quantity counts once.
                    return $price === null ? null : $price * ($number($data[$quantity->key] ?? null) ?? 1);
                },
            ];
        }

        return ['label' => $money->label, 'value' => fn (array $data) => $number($data[$money->key] ?? null)];
    }

    private function numberStats(DataColumn $column, Collection $current, Collection $previous, bool $hasDate): array
    {
        $values = $current->map(fn ($r) => $r['data'][$column->key] ?? null)->filter(fn ($v) => is_int($v) || is_float($v));

        return [
            'label' => $column->label,
            'type' => $column->type->value,
            'sum' => $this->round($values->sum()),
            'previousSum' => $hasDate
                ? $this->round($previous->map(fn ($r) => $r['data'][$column->key] ?? null)->filter(fn ($v) => is_int($v) || is_float($v))->sum())
                : null,
            'average' => $values->isEmpty() ? null : $this->round($values->avg()),
            'min' => $values->min(),
            'max' => $values->max(),
            'filled' => $values->count(),
        ];
    }

    /**
     * @return array{label: string, values: list<array{value: string, rows: int, amount: int|float|null}>, distinct: int}
     */
    private function breakdown(DataColumn $column, Collection $current): array
    {
        $groups = $current
            ->filter(fn ($r) => ($r['data'][$column->key] ?? null) !== null && ($r['data'][$column->key] ?? '') !== '')
            ->groupBy(fn ($r) => match (true) {
                $r['data'][$column->key] === true => 'Այո',
                $r['data'][$column->key] === false => 'Ոչ',
                default => (string) $r['data'][$column->key],
            });

        return [
            'label' => $column->label,
            'distinct' => $groups->count(),
            'values' => $groups
                ->map(fn (Collection $rows, string $value) => [
                    'value' => $value,
                    'rows' => $rows->count(),
                    'amount' => $rows->whereNotNull('amount')->isEmpty() ? null : $this->round($rows->sum('amount')),
                ])
                ->sortByDesc(fn (array $v) => [$v['amount'] ?? 0, $v['rows']])
                ->take(self::TOP_VALUES)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{date: string, rows: int, amount: int|float}>
     */
    private function series(Collection $current, CarbonImmutable $from, CarbonImmutable $to, string $bucket): array
    {
        $format = $bucket === 'month' ? 'Y-m' : 'Y-m-d';
        $points = [];

        for ($cursor = $from; $cursor <= $to; $cursor = $bucket === 'month' ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay()) {
            $points[$cursor->format($format)] = ['date' => $cursor->format($format), 'rows' => 0, 'amount' => 0];
        }

        foreach ($current as $row) {
            if (isset($points[$key = $row['date']->format($format)])) {
                $points[$key]['rows']++;
                $points[$key]['amount'] += $row['amount'] ?? 0;
            }
        }

        return array_values(array_map(fn ($p) => [...$p, 'amount' => $this->round($p['amount'])], $points));
    }

    private function date(mixed $value, \DateTimeZone $timezone): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone) ?: null;
    }

    private function round(int|float|null $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $rounded = round($value, 2);

        return floor($rounded) == $rounded ? (int) $rounded : $rounded;
    }
}

<?php

namespace App\Exports;

use App\DataTables\Period;
use App\Enums\TablePeriod;
use App\Expenses\ExpenseStatistics;
use App\Expenses\Finance;

/**
 * The owner's reports as sheets: net profit per period (Finance) and what
 * was spent in one period (ExpenseStatistics), as the admin shows them.
 */
final class ReportExport
{
    public function __construct(
        private readonly Finance $finance,
        private readonly ExpenseStatistics $expenses,
    ) {}

    /**
     * Revenue, cost of goods, expenses and profit per day, week, month,
     * quarter or year up to the one of a date, and the expenses by kind.
     *
     * @return array{name: string, sheets: list<Sheet>}
     */
    public function finance(TablePeriod $by, ?string $date = null): array
    {
        $report = $this->finance->report($by, $date);
        $money = fn (string $label) => Sheet::column($label, Sheet::MONEY);
        $line = fn (array $p, ?string $label = null) => [
            $label ?? $p['label'],
            $p['start'] ?? null,
            $p['end'] ?? null,
            $p['revenue'],
            $p['cogs'],
            $p['grossProfit'],
            $p['expenses'],
            $p['excluded'],
            $p['netProfit'],
            $p['cashLeft'],
            $p['margin'],
        ];

        $sheets = [
            new Sheet('Շահույթ', [
                Sheet::column('Ժամանակահատված'),
                Sheet::column('Սկիզբ', Sheet::DATE),
                Sheet::column('Ավարտ', Sheet::DATE),
                $money('Եկամուտ'),
                $money('Ինքնարժեք'),
                $money('Համախառն շահույթ'),
                $money('Ծախսեր'),
                $money('Շահույթից դուրս ծախսեր'),
                $money('Զուտ շահույթ'),
                $money('Մնացած գումար'),
                Sheet::column('Մարժա', Sheet::PERCENT),
            ], array_map(fn (array $p) => $line($p), $report['periods']), $line($report['total'], 'Ընդամենը')),
            $this->categories($report['categories']),
        ];

        if ($report['notes'] !== []) {
            $sheets[] = new Sheet('Նշումներ', [Sheet::column('Նշում')], array_map(fn (string $n) => [$n], $report['notes']));
        }

        $first = $report['periods'][0] ?? null;
        $last = end($report['periods']) ?: null;

        return [
            'name' => 'Շահույթ · '.TablePeriod::from($report['by'])->label().($first && $last ? " · {$first['label']} – {$last['label']}" : ''),
            'sheets' => $sheets,
        ];
    }

    /**
     * Every expense of the day, week, month, quarter or year a date falls
     * in, by kind, and per day (or month) inside it.
     *
     * @return array{name: string, sheets: list<Sheet>}
     */
    public function expenses(TablePeriod $by, ?string $date = null): array
    {
        $report = $this->expenses->report($by, $date, null);

        $rows = array_map(fn (array $e) => [
            $e['date'],
            $e['category'],
            $e['item'],
            $e['supplier'],
            $e['payment'],
            $e['performer'],
            $e['project'],
            $e['note'],
            $e['amount'],
            $e['inProfit'],
            $e['recurring'],
            $e['table'],
        ], array_reverse($report['rows']));

        $sheets = [
            new Sheet('Ծախսեր', [
                Sheet::column('Ամսաթիվ', Sheet::DATE),
                Sheet::column('Տեսակ'),
                Sheet::column('Ինչի համար'),
                Sheet::column('Ում է վճարվել'),
                Sheet::column('Վճարման եղանակ'),
                Sheet::column('Ով է ծախսել'),
                Sheet::column('Օբյեկտ'),
                Sheet::column('Նշում'),
                Sheet::column('Գումար', Sheet::MONEY),
                Sheet::column('Հաշվվում է շահույթում', Sheet::BOOLEAN),
                Sheet::column('Կրկնվող', Sheet::BOOLEAN),
                Sheet::column('Աղյուսակ'),
            ], $rows, ['Ընդամենը', null, null, null, null, null, null, null, $report['totals']['total']]),
            $this->categories($report['categories']),
        ];

        if ($report['inside'] !== []) {
            $sheets[] = new Sheet(in_array($by, [TablePeriod::Week, TablePeriod::Month], true) ? 'Ըստ օրերի' : 'Ըստ ամիսների', [
                Sheet::column('Ժամանակահատված'),
                Sheet::column('Սկիզբ', Sheet::DATE),
                Sheet::column('Ծախսեր', Sheet::MONEY),
                Sheet::column('Քանակ', Sheet::NUMBER),
            ], array_map(fn (array $p) => [$p['label'], $p['start'], $p['total'], $p['count']], $report['inside']));
        }

        return [
            'name' => 'Ծախսեր · '.Period::of($by, $report['period']['start'])->label(),
            'sheets' => $sheets,
        ];
    }

    /** @param  list<array<string, mixed>>  $categories */
    private function categories(array $categories): Sheet
    {
        $spent = array_values(array_filter($categories, fn (array $c) => $c['amount'] > 0));

        return new Sheet('Ըստ տեսակի', [
            Sheet::column('Տեսակ'),
            Sheet::column('Հաշվվում է շահույթում', Sheet::BOOLEAN),
            Sheet::column('Գումար', Sheet::MONEY),
            Sheet::column('Որից կրկնվող', Sheet::MONEY),
            Sheet::column('Քանակ', Sheet::NUMBER),
        ], array_map(fn (array $c) => [$c['name'], $c['inProfit'], $c['amount'], $c['recurring'], $c['count']], $spent));
    }
}

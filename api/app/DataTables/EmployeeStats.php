<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use Illuminate\Support\Collection;

/**
 * How much each employee did in a period: the rows of the order and sales
 * tables with a performer column, by the employee they're linked to. Money
 * is read by the analytics roles: revenue is the row's amount (the total,
 * else price × quantity), costs are unit cost × quantity, profit is revenue
 * minus costs.
 */
final class EmployeeStats
{
    /**
     * Tables with a performer column, of the sections whose module is on.
     *
     * @return Collection<int, DataTable>
     */
    public static function tables(?DataTableSection $section = null): Collection
    {
        return Performers::tables()
            ->filter(fn (DataTable $t) => $t->section->isRevenue() && $t->section->isActive() && ($section === null || $t->section === $section))
            ->values();
    }

    /** @return array<string, mixed> */
    public static function for(?Period $period, ?DataTableSection $section = null): array
    {
        $tables = self::tables($section);
        $cost = Performers::costReader($tables);
        $performerKeys = $tables->mapWithKeys(fn (DataTable $t) => [$t->id => $t->performerColumn()->key]);

        $rows = self::rows($tables, $period)
            ->get(['id', 'data_table_id', 'data', 'amount', 'employee_id'])
            ->map(fn (DataTableRow $row) => [
                'employee' => $row->employee_id,
                'name' => is_string($v = $row->data[$performerKeys[$row->data_table_id]] ?? null) ? trim($v) : '',
                'amount' => $row->amount,
                'cost' => $cost($row),
            ]);

        $hasAmount = $tables->contains(fn (DataTable $t) => $t->amountColumn() !== null);
        $hasCost = $tables->contains(fn (DataTable $t) => $t->roleColumn(ColumnRole::Cost) !== null);
        $byEmployee = $rows->whereNotNull('employee')->groupBy('employee');
        $employees = Employee::query()->orderByDesc('is_active')->orderBy('name')->get();
        $groupOf = $employees->pluck('group_id', 'id');

        return [
            'period' => $period?->toArray(),
            'hasPerformerColumn' => $tables->isNotEmpty(),
            'hasAmount' => $hasAmount,
            'hasCost' => $hasCost,
            'total' => self::figures($rows),
            'employees' => $employees
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'position' => $e->position,
                    'isActive' => $e->is_active,
                    'groupId' => $e->group_id,
                    ...self::figures($byEmployee->get($e->id, collect())),
                ])
                ->sortByDesc(fn (array $e) => [$e['isActive'] || $e['orders'] > 0, $e['revenue'] ?? 0, $e['orders']])
                ->values()
                ->all(),
            // Each group's employees together, with its manager.
            'groups' => EmployeeGroup::query()->with('manager:id,name')->orderBy('name')->get()
                ->map(fn (EmployeeGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'manager' => $g->manager?->name,
                    ...self::figures($rows->whereNotNull('employee')->filter(fn (array $r) => $groupOf->get($r['employee']) === $g->id)),
                ])
                ->values()
                ->all(),
            // Rows nobody is named in.
            'unassigned' => self::figures($rows->whereNull('employee')->where('name', '')),
            // Names in the performer column that aren't employees: outside
            // performers (another company, a freelancer), or names from a file.
            'unknown' => $rows->whereNull('employee')->where('name', '!=', '')
                ->groupBy(fn (array $r) => Employee::normalize($r['name']))
                ->map(fn (Collection $group) => ['name' => $group->first()['name'], ...self::figures($group)])
                ->sortByDesc('orders')
                ->values()
                ->all(),
        ];
    }

    /**
     * Rows of these tables in the period (all when null).
     *
     * @param  Collection<int, DataTable>  $tables
     */
    public static function rows(Collection $tables, ?Period $period)
    {
        return DataTableRow::query()
            ->whereIn('data_table_id', $tables->modelKeys())
            ->when($period !== null, fn ($query) => $query
                ->where('occurred_at', '>=', $period->start->format('Y-m-d H:i:s'))
                ->where('occurred_at', '<', $period->next()->format('Y-m-d H:i:s')));
    }

    /**
     * @param  Collection<int, array{amount: int|float|null, cost: int|float|null}>  $rows
     * @return array{orders: int, revenue: int|float|null, costs: int|float|null, profit: int|float|null, averageCheck: int|float|null}
     */
    public static function figures(Collection $rows): array
    {
        $priced = $rows->whereNotNull('amount');
        $costed = $rows->whereNotNull('cost');
        $revenue = $priced->isEmpty() ? null : Decimal::round($priced->sum('amount'));
        $costs = $costed->isEmpty() ? null : Decimal::round($costed->sum('cost'));

        return [
            'orders' => $rows->count(),
            'revenue' => $revenue,
            'costs' => $costs,
            'profit' => $costs === null ? null : Decimal::round(($revenue ?? 0) - ($costs ?? 0)),
            'averageCheck' => $priced->isEmpty() ? null : Decimal::round($priced->avg('amount')),
        ];
    }
}

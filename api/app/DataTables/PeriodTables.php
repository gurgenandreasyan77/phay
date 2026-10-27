<?php

namespace App\DataTables;

use App\Enums\TablePeriod;
use App\Models\DataTable;
use Illuminate\Support\Collection;

/**
 * Tables kept per day, week, month, quarter or year (DataTable::period). An
 * order added from the calendar goes to the table of its date's period,
 * like the chosen table: a November order chosen for the October table of
 * a monthly business lands in November's table, one of 18 November chosen
 * for a quarter's table lands in the 4th quarter's. That table is made with
 * the chosen one's columns when there is none yet.
 *
 * A table whose period was never chosen (made before periods existed) is a
 * month's table when its name or rows say so (MonthTables), and otherwise
 * takes any dates, as a custom table does.
 */
final class PeriodTables
{
    /**
     * Reads the month of the tables whose period isn't chosen (see
     * DataTable::$inferredMonth), so period() answers for each of them.
     *
     * @template T of Collection<int, DataTable>
     *
     * @param  T  $tables
     * @return T
     */
    public static function infer(Collection $tables): Collection
    {
        $unset = $tables->filter(fn (DataTable $table) => $table->period === null);
        $months = MonthTables::months($unset);

        foreach ($unset as $table) {
            $table->inferredMonth = $months[$table->id] ?? null;
        }

        return $tables;
    }

    /**
     * The table a row of this date ("Y-m-d...") goes to, starting from the
     * chosen one: itself when it takes any dates or the date is in its
     * period, another table of the same section kept for the date's period,
     * or a new one of that period with the chosen table's columns.
     *
     * @return array{0: DataTable, 1: bool} the table, and whether it was created
     */
    public static function forDate(DataTable $chosen, string $date): array
    {
        [$table, $target] = self::locate($chosen, $date);

        return $table ? [$table, false] : [self::make($chosen, $target), true];
    }

    /**
     * Like forDate(), without making anything: the table, or the period a
     * new table would be made for.
     *
     * @return array{0: DataTable, 1: null}|array{0: null, 1: Period}
     */
    public static function locate(DataTable $chosen, string $date): array
    {
        $tables = self::infer(
            DataTable::query()
                ->where('section', $chosen->section)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->filter(fn (DataTable $table) => $table->dateColumn() !== null && $table->isNot($chosen))
                ->push($chosen),
        );

        $period = $chosen->period();
        if ($period === null || $period->contains($date)) {
            return [$chosen, null];
        }

        $target = Period::of($period->kind, $date);
        $candidates = $tables->filter(fn (DataTable $table) => $table->period()?->kind === $target->kind
            && $table->period()->key() === $target->key());

        if ($candidates->isNotEmpty()) {
            // A table built like the chosen one first: the form's fields fit it as they are.
            $keys = MonthTables::keys($chosen);

            return [$candidates->first(fn (DataTable $t) => MonthTables::keys($t) === $keys) ?? $candidates->first(), null];
        }

        return [null, $target];
    }

    /** A new table of the period, with the chosen table's columns (see locate()). */
    public static function make(DataTable $chosen, Period $target): DataTable
    {
        if ($chosen->period === null && $chosen->inferredMonth === null) {
            self::infer(collect([$chosen]));
        }
        $table = DataTable::create([
            'section' => $chosen->section,
            'name' => self::nameFor($chosen, $chosen->period(), $target),
            'description' => $chosen->description,
            'columns' => $chosen->columns,
            'period' => $target->kind,
            'period_start' => $target->start,
            'sort_order' => (DataTable::query()->where('section', $chosen->section)->max('sort_order') ?? 0) + 1,
        ]);
        $table->inferredMonth = null;

        return $table;
    }

    /**
     * A new table's name: a month's in the chosen table's style ("october" →
     * "november", see MonthTables), else the chosen name with its period's
     * label changed ("Պատվերներ · 4-րդ եռամսյակ 2026" → "… 1-ին եռամսյակ
     * 2027"), else the period's label.
     */
    public static function nameFor(DataTable $chosen, Period $chosenPeriod, Period $target): string
    {
        if ($target->kind === TablePeriod::Month) {
            return MonthTables::nameFor($chosen, $chosenPeriod->key(), $target->key());
        }

        $label = $chosenPeriod->label();

        return $label !== $chosen->name && str_contains($chosen->name, $label)
            ? str_replace($label, $target->label(), $chosen->name)
            : $target->label();
    }
}

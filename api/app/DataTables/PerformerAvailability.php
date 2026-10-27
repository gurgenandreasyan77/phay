<?php

namespace App\DataTables;

use App\Enums\ColumnRole;
use App\Enums\DataColumnType;
use App\Enums\DataTableSection;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Whether the performer of a new order (or sale) can take it: not away that
 * day (a vacation, a day off, sick: EmployeeAbsence), and, for an order of
 * a service with a time, not on another order then. An order lasts its
 * duration column (minutes), an hour when it has none. A sale's seller is
 * only checked for being away: a seller sells several things at once.
 *
 * Only an employee of the business can be checked: someone from outside
 * (a name no employee has) is left alone. A name matches an employee's full
 * name, or a first name only one employee has.
 */
final class PerformerAvailability
{
    /** How long an order without a duration takes, in minutes. */
    public const DEFAULT_MINUTES = 60;

    /**
     * What keeps the row's performer from it, if anything: a message each
     * (Armenian, with who is free then), for the form and the AI's card.
     *
     * @param  array<string, mixed>  $data  the row's parsed values by column key
     * @param  list<array<string, mixed>>  $pending  the other rows added with it (an AI card's), of the same table
     * @return list<array{kind: 'away'|'busy', employee: string, message: string, free: list<string>}>
     */
    public static function check(DataTable $table, array $data, array $pending = [], ?int $ignoreRow = null): array
    {
        $performer = $table->performerColumn();
        $slot = self::slot($table, $data);
        if (! $performer || ! $slot || ! is_string($data[$performer->key] ?? null)) {
            return [];
        }

        $employees = Employee::query()->orderBy('id')->get(['id', 'name', 'group_id', 'is_active']);
        $employee = self::named($employees, $data[$performer->key]);
        if (! $employee) {
            return [];
        }

        $day = $slot['day'];
        $absences = EmployeeAbsence::query()->overlapping($day, $day)->get();
        $away = $absences->firstWhere('employee_id', $employee->id);
        // Who else is on an order then: by employee, their orders that day.
        $orders = $table->section === DataTableSection::Orders && isset($slot['start'])
            ? self::ordersOn($day, $ignoreRow)
            : collect();
        foreach ($pending as $other) {
            $otherSlot = self::slot($table, $other);
            $named = is_string($other[$performer->key] ?? null) ? self::named($employees, $other[$performer->key]) : null;
            if ($named && isset($otherSlot['start']) && $otherSlot['day'] === $day) {
                $orders->push(['employee' => $named->id, 'title' => self::title($table, $other), ...$otherSlot]);
            }
        }
        $busyAt = fn (int $id) => isset($slot['start'])
            ? $orders->first(fn (array $o) => $o['employee'] === $id && isset($o['start']) && $o['start'] < $slot['end'] && $slot['start'] < $o['end'])
            : null;

        $free = fn () => $employees
            ->filter(fn (Employee $e) => $e->is_active !== false && $e->id !== $employee->id
                && ! $absences->contains('employee_id', $e->id) && ! $busyAt($e->id))
            ->sortBy(fn (Employee $e) => $employee->group_id !== null && $e->group_id === $employee->group_id ? 0 : 1)
            ->take(5)
            ->pluck('name')
            ->values()
            ->all();
        $offer = fn (array $names) => $names === [] ? 'Ընտրեք ուրիշ կատարող կամ ժամ։' : 'Այդ ժամին ազատ են՝ '.implode(', ', $names).'։';
        $date = CarbonImmutable::parse($day)->format('d.m.Y');

        if ($away) {
            $names = $free();

            return [[
                'kind' => 'away',
                'employee' => $employee->name,
                'message' => sprintf(
                    '%s %s-ին բացակա է՝ %s (%s–%s)։ %s',
                    $employee->name,
                    $date,
                    mb_strtolower($away->kind->label()),
                    $away->starts_on->format('d.m.Y'),
                    $away->ends_on->format('d.m.Y'),
                    $names === [] ? 'Ընտրեք ուրիշ կատարող կամ օր։' : 'Այդ օրը ազատ են՝ '.implode(', ', $names).'։',
                ),
                'free' => $names,
            ]];
        }

        if ($busy = $busyAt($employee->id)) {
            $names = $free();

            return [[
                'kind' => 'busy',
                'employee' => $employee->name,
                'message' => sprintf(
                    '%s %s-ին %s–%s ազատ չէ՝ արդեն ունի պատվեր%s (%s–%s)։ %s',
                    $employee->name,
                    $date,
                    $slot['start']->format('H:i'),
                    $slot['end']->format('H:i'),
                    $busy['title'] !== null ? ' «'.$busy['title'].'»' : '',
                    $busy['start']->format('H:i'),
                    $busy['end']->format('H:i'),
                    $offer($names),
                ),
                'free' => $names,
            ]];
        }

        return [];
    }

    /**
     * The employee a performer cell names: the full name, or a first name
     * only one employee has.
     *
     * @param  Collection<int, Employee>  $employees
     */
    public static function named(Collection $employees, string $name): ?Employee
    {
        $name = Employee::normalize($name);
        if ($name === '') {
            return null;
        }
        $named = $employees->filter(fn (Employee $e) => Employee::normalize($e->name) === $name);
        if ($named->isEmpty()) {
            $named = $employees->filter(fn (Employee $e) => explode(' ', Employee::normalize($e->name))[0] === $name);
        }

        return $named->count() === 1 ? $named->first() : null;
    }

    /**
     * The row's day, and its start and end when it has a time.
     *
     * @return array{day: string, start?: CarbonImmutable, end?: CarbonImmutable}|null
     */
    public static function slot(DataTable $table, array $data): ?array
    {
        $date = $table->dateColumn();
        $raw = $date ? ($data[$date->key] ?? null) : null;
        if (! is_string($raw) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return null;
        }
        $moment = RowMetrics::for($table)($data)['occurred_at'];
        $time = $table->roleColumn(ColumnRole::Time);
        $timed = ($date->type === DataColumnType::DateTime && strlen($raw) > 10)
            || ($time && is_string($data[$time->key] ?? null) && trim($data[$time->key]) !== '');
        if (! $timed || $moment === null) {
            return ['day' => substr($raw, 0, 10)];
        }

        $start = CarbonImmutable::parse($moment);

        return ['day' => substr($raw, 0, 10), 'start' => $start, 'end' => $start->addMinutes(self::minutes($table, $data))];
    }

    /** How long the row's order takes: its duration column, else an hour. */
    private static function minutes(DataTable $table, array $data): int
    {
        $duration = $table->roleColumn(ColumnRole::Duration);
        $value = $duration ? ($data[$duration->key] ?? null) : null;

        return is_int($value) || is_float($value) ? max(1, (int) round($value)) : self::DEFAULT_MINUTES;
    }

    /**
     * The timed orders of the day in the business's current order tables
     * that are linked to an employee.
     *
     * @return Collection<int, array{employee: int, title: ?string, day: string, start?: CarbonImmutable, end?: CarbonImmutable}>
     */
    private static function ordersOn(string $day, ?int $ignoreRow): Collection
    {
        $tables = DataTable::query()->where('section', DataTableSection::Orders)->get()->keyBy('id');

        return DataTableRow::query()
            ->whereIn('data_table_id', $tables->keys())
            ->whereNotNull('employee_id')
            ->when($ignoreRow, fn ($q) => $q->whereKeyNot($ignoreRow))
            ->where('occurred_at', '>=', $day.' 00:00:00')
            ->where('occurred_at', '<', CarbonImmutable::parse($day)->addDay()->toDateString().' 00:00:00')
            ->get(['id', 'data_table_id', 'data', 'employee_id'])
            ->map(function (DataTableRow $row) use ($tables) {
                $table = $tables[$row->data_table_id];

                return ['employee' => $row->employee_id, 'title' => self::title($table, $row->data ?? []), ...(self::slot($table, $row->data ?? []) ?? ['day' => ''])];
            })
            ->filter(fn (array $o) => isset($o['start']))
            ->values();
    }

    /** What the order is, for the message: its service or product. */
    private static function title(DataTable $table, array $data): ?string
    {
        $item = $table->roleColumn(ColumnRole::Item);
        $value = $item ? ($data[$item->key] ?? null) : null;

        return is_scalar($value) && trim((string) $value) !== '' ? mb_substr(trim((string) $value), 0, 60) : null;
    }
}

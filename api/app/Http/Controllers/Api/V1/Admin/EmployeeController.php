<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\EmployeeStats;
use App\DataTables\Period;
use App\DataTables\Performers;
use App\Enums\ColumnRole;
use App\Enums\TablePeriod;
use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The business's employees: who does its orders. Everyone who keeps orders
 * reads the list (the performer field offers it); the owner and the admin
 * keep it; the owner sees what each employee brought in.
 */
class EmployeeController extends Controller
{
    public const PER_PAGE = 50;

    public function index(): JsonResponse
    {
        $employees = Employee::query()
            ->withCount('rows')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $employees->map(fn (Employee $e) => self::toArray($e))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $employee = Employee::create($validated);

        // Rows already naming them (typed in or imported before) are theirs.
        Performers::link($employee);

        return response()->json(['data' => self::toArray($employee->loadCount('rows'))], 201);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $this->validated($request, $employee);
        $employee->fill($validated);
        $renamed = $employee->isDirty('name');
        $employee->save();

        if ($renamed) {
            Performers::rename($employee);
        }

        return response()->json(['data' => self::toArray($employee->loadCount('rows'))]);
    }

    /** Their rows keep the name they were done under, no longer linked to anyone. */
    public function destroy(Employee $employee): Response
    {
        DataTableRow::query()->where('employee_id', $employee->id)->update(['employee_id' => null]);
        $employee->delete();

        return response()->noContent();
    }

    /** Orders, revenue, costs and profit of each employee in a period (?period=month&date=…; all time without it). */
    public function stats(Request $request): JsonResponse
    {
        return response()->json(['data' => EmployeeStats::for($this->period($request))]);
    }

    /** One employee: their figures in the period and their rows, newest first. */
    public function show(Request $request, Employee $employee): JsonResponse
    {
        $period = $this->period($request);
        $tables = EmployeeStats::tables()->keyBy('id');
        $cost = Performers::costReader($tables);

        $all = EmployeeStats::rows($tables, $period)->where('employee_id', $employee->id);
        $figures = EmployeeStats::figures(
            (clone $all)->get(['id', 'data_table_id', 'data', 'amount'])
                ->map(fn (DataTableRow $row) => ['amount' => $row->amount, 'cost' => $cost($row)]),
        );

        $rows = $all->orderByDesc('occurred_at')->orderByDesc('id')->paginate(self::PER_PAGE);
        $text = function (DataTable $table, DataTableRow $row, ColumnRole $role): ?string {
            $column = $table->roleColumn($role);
            $value = $column ? ($row->data[$column->key] ?? null) : null;

            return is_scalar($value) && $value !== '' ? (string) $value : null;
        };

        return response()->json([
            'data' => [
                'employee' => self::toArray($employee->loadCount('rows')),
                'period' => $period?->toArray(),
                'figures' => $figures,
                'rows' => collect($rows->items())->map(function (DataTableRow $row) use ($tables, $cost, $text) {
                    $table = $tables[$row->data_table_id];

                    return [
                        'id' => $row->id,
                        'table' => ['id' => $table->id, 'name' => $table->name, 'section' => $table->section->value],
                        'occurredAt' => ($at = $row->getRawOriginal('occurred_at')) ? substr($at, 0, 16) : null,
                        'item' => $text($table, $row, ColumnRole::Item),
                        'status' => $text($table, $row, ColumnRole::Status),
                        'amount' => $row->amount,
                        'cost' => $cost($row),
                    ];
                })->values(),
            ],
            'meta' => ['currentPage' => $rows->currentPage(), 'lastPage' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    /** ?period=day|week|month|quarter|year&date=Y-m-d (today by default), or none for all time. */
    private function period(Request $request): ?Period
    {
        $request->validate([
            'period' => ['nullable', Rule::in(TablePeriod::calendarValues())],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (! $request->filled('period')) {
            return null;
        }

        $today = now(app(TenantContext::class)->current()->timezone ?: config('app.timezone'))->toDateString();

        return Period::of(TablePeriod::from($request->input('period')), $request->input('date') ?: $today);
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $validated = $request->validate([
            'name' => [$employee ? 'sometimes' : 'required', 'string', 'max:100'],
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $validated)) {
            $validated['name'] = preg_replace('/\s+/u', ' ', trim($validated['name']));
            $taken = Employee::query()
                ->when($employee, fn ($q) => $q->whereKeyNot($employee->id))
                ->get(['id', 'name'])
                ->contains(fn (Employee $e) => Employee::normalize($e->name) === Employee::normalize($validated['name']));

            if ($validated['name'] === '' || $taken) {
                throw ValidationException::withMessages(['name' => [$taken ? 'Այս անունով աշխատակից արդեն կա։' : 'Պարտադիր դաշտ է։']]);
            }
        }

        if (array_key_exists('isActive', $validated)) {
            $validated['is_active'] = $validated['isActive'];
            unset($validated['isActive']);
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private static function toArray(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'position' => $employee->position,
            'phone' => $employee->phone,
            'isActive' => $employee->is_active,
            // Rows linked to them, of any period.
            'ordersCount' => $employee->rows_count ?? null,
            'createdAt' => $employee->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TablePeriod;
use App\Http\Controllers\Controller;
use App\Models\RecurringExpense;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Expenses that repeat (rent every month, taxes every quarter): kept once
 * and counted by the figures on each due date.
 */
class RecurringExpenseController extends Controller
{
    public function index(): JsonResponse
    {
        $expenses = RecurringExpense::query()->with('category')->orderBy('starts_on')->orderBy('id')->get();

        return response()->json(['data' => $expenses->map(fn (RecurringExpense $e) => self::toArray($e))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $expense = RecurringExpense::create($this->validated($request));

        return response()->json(['data' => self::toArray($expense->load('category'))], 201);
    }

    public function update(Request $request, RecurringExpense $recurringExpense): JsonResponse
    {
        $recurringExpense->update($this->validated($request, $recurringExpense));

        return response()->json(['data' => self::toArray($recurringExpense->fresh('category'))]);
    }

    public function destroy(RecurringExpense $recurringExpense): Response
    {
        $recurringExpense->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?RecurringExpense $expense = null): array
    {
        $required = $expense ? 'sometimes' : 'required';
        $validated = $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'categoryId' => [$required, 'nullable', 'integer', Rule::exists('expense_categories', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'amount' => [$required, 'numeric', 'gt:0', 'max:999999999999'],
            'frequency' => [$required, Rule::in(array_map(fn (TablePeriod $p) => $p->value, RecurringExpense::FREQUENCIES))],
            'startsOn' => [$required, 'date_format:Y-m-d'],
            'endsOn' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.($request->input('startsOn') ?? $expense?->starts_on?->toDateString() ?? '1970-01-01')],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $fields = ['name' => 'name', 'categoryId' => 'expense_category_id', 'amount' => 'amount', 'frequency' => 'frequency', 'startsOn' => 'starts_on', 'endsOn' => 'ends_on', 'note' => 'note'];

        return collect($fields)
            ->filter(fn (string $column, string $field) => array_key_exists($field, $validated))
            ->mapWithKeys(fn (string $column, string $field) => [$column => is_string($validated[$field]) ? trim($validated[$field]) : $validated[$field]])
            ->all();
    }

    /** @return array<string, mixed> */
    public static function toArray(RecurringExpense $e): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'category' => $e->category ? ['id' => $e->category->id, 'name' => $e->category->name, 'inProfit' => $e->category->in_profit] : null,
            'amount' => $e->value(),
            'monthly' => $e->monthly(),
            'frequency' => $e->frequency->value,
            'startsOn' => $e->starts_on->toDateString(),
            'endsOn' => $e->ends_on?->toDateString(),
            'note' => $e->note,
        ];
    }
}

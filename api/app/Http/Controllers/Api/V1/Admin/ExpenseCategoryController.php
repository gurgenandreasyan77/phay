<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Expenses\ExpenseCategories;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The kinds of the business's expenses: the ready-made ones and its own,
 * each counted in the net profit or not. Renaming one renames it in the
 * rows that name it; a ready-made one can't be deleted (a file's "Налоги"
 * is still matched to it), the business's own can.
 */
class ExpenseCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = ExpenseCategories::all()->loadCount('recurring');

        return response()->json(['data' => $categories->map(fn (ExpenseCategory $c) => [
            ...self::toArray($c),
            'recurringCount' => (int) $c->recurring_count,
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'inProfit' => ['sometimes', 'boolean'],
        ]);
        $name = $this->name($validated['name']);

        $category = ExpenseCategory::create([
            'name' => $name,
            'in_profit' => $validated['inProfit'] ?? true,
            'sort_order' => (ExpenseCategory::query()->max('sort_order') ?? 0) + 1,
        ]);

        return response()->json(['data' => self::toArray($category)], 201);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'inProfit' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('inProfit', $validated)) {
            $expenseCategory->in_profit = $validated['inProfit'];
        }

        $old = $expenseCategory->name;
        if (array_key_exists('name', $validated)) {
            $expenseCategory->name = $this->name($validated['name'], $expenseCategory);
        }

        DB::transaction(function () use ($expenseCategory, $old) {
            $expenseCategory->save();
            if ($expenseCategory->wasChanged('name')) {
                ExpenseCategories::rename($old, $expenseCategory->name);
            }
        });

        return response()->json(['data' => self::toArray($expenseCategory)]);
    }

    /** Rows naming it stay as they are (counted under that name); its recurring expenses lose it. */
    public function destroy(ExpenseCategory $expenseCategory): Response
    {
        if ($expenseCategory->key !== null) {
            throw ValidationException::withMessages(['category' => ['Պատրաստի տեսակը հնարավոր չէ ջնջել, բայց կարող եք վերանվանել այն կամ անջատել շահույթից։']]);
        }

        $expenseCategory->delete();

        return response()->noContent();
    }

    private function name(string $name, ?ExpenseCategory $category = null): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $taken = ExpenseCategory::query()
            ->when($category, fn ($q) => $q->whereKeyNot($category->id))
            ->get(['id', 'name'])
            ->contains(fn (ExpenseCategory $c) => Employee::normalize($c->name) === Employee::normalize($name));

        if ($name === '' || $taken) {
            throw ValidationException::withMessages(['name' => [$taken ? 'Այս անունով տեսակ արդեն կա։' : 'Պարտադիր դաշտ է։']]);
        }

        return $name;
    }

    /** @return array<string, mixed> */
    public static function toArray(ExpenseCategory $c): array
    {
        return [
            'id' => $c->id,
            'key' => $c->key,
            'name' => $c->name,
            'inProfit' => $c->in_profit,
            'isDefault' => $c->key !== null,
        ];
    }
}

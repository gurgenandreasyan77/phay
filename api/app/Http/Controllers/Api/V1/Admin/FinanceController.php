<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TablePeriod;
use App\Expenses\Finance;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Net profit per day, week, month, quarter or year: revenue, cost of goods,
 * expenses by category (see Finance). For the owner.
 */
class FinanceController extends Controller
{
    public function __invoke(Request $request, Finance $finance): JsonResponse
    {
        $validated = $request->validate([
            'by' => ['nullable', Rule::in(TablePeriod::calendarValues())],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json(['data' => $finance->report(TablePeriod::from($validated['by'] ?? 'month'), $validated['date'] ?? null)]);
    }
}

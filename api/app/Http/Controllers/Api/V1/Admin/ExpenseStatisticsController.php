<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TablePeriod;
use App\Expenses\ExpenseStatistics;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What the business spent in a day, week, month, quarter or year of any
 * date, on its own (see ExpenseStatistics).
 */
class ExpenseStatisticsController extends Controller
{
    public function __invoke(Request $request, ExpenseStatistics $statistics): JsonResponse
    {
        $validated = $request->validate([
            'by' => ['nullable', Rule::in(TablePeriod::calendarValues())],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json(['data' => $statistics->report(TablePeriod::from($validated['by'] ?? 'month'), $validated['date'] ?? null)]);
    }
}

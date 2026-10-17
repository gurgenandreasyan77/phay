<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\TableAnalytics;
use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Services\BusinessAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Figures of each of the business's own tables, for the analytics screen. */
class TableAnalyticsController extends Controller
{
    public function __invoke(Request $request, TableAnalytics $analytics): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'integer', Rule::in(BusinessAnalytics::PERIODS)],
        ]);
        $days = (int) ($validated['period'] ?? 30);

        return response()->json([
            'data' => $analytics->tables()->map(fn (DataTable $table) => $analytics->summary($table, $days))->values(),
        ]);
    }
}

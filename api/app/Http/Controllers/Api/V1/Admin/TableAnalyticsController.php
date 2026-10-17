<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTables\TableAnalytics;
use App\Enums\DataTableSection;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Services\BusinessAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

/**
 * Figures of the business's own tables, per section, for the analytics
 * screen. For orders, also the orders of the tables and of the site together.
 */
class TableAnalyticsController extends Controller
{
    public function __invoke(Request $request, TableAnalytics $analytics, BusinessAnalytics $site): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'integer', Rule::in(BusinessAnalytics::PERIODS)],
        ]);
        $days = (int) ($validated['period'] ?? 30);

        $sections = array_map(function (array $section) use ($days, $site) {
            if ($section['section'] === DataTableSection::Orders->value && Feature::active(FeatureFlag::Orders->value)) {
                $section['withSite'] = TableAnalytics::withSiteOrders($section, $site->summary($days));
            }

            return $section;
        }, $analytics->overview($days));

        return response()->json(['data' => $sections]);
    }
}

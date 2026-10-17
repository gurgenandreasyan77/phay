<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Analytics\Dashboard;
use App\Analytics\DashboardWidget;
use App\DataTables\TableAnalytics;
use App\Enums\DataTableSection;
use App\Features\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Models\DataTable;
use App\Services\BusinessAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

/**
 * Figures of the business's own tables, per section, for the analytics
 * screen: the widgets the owner chose (and the catalog to choose more
 * from). For orders, also the orders of the tables and of the site together.
 */
class TableAnalyticsController extends Controller
{
    public function __invoke(Request $request, TableAnalytics $analytics, BusinessAnalytics $site, Dashboard $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'integer', Rule::in(BusinessAnalytics::PERIODS)],
        ]);
        $days = (int) ($validated['period'] ?? 30);

        $sections = $analytics->tables()
            ->groupBy(fn (DataTable $t) => $t->section->value)
            ->map(function (Collection $tables, string $value) use ($analytics, $dashboard, $days, $site) {
                $section = DataTableSection::from($value);
                $tables = $tables->values();
                $board = $dashboard->for($section, $tables);
                $summary = $analytics->section($section, $tables, $days);

                if ($section === DataTableSection::Orders && Feature::active(FeatureFlag::Orders->value)) {
                    $summary['withSite'] = TableAnalytics::withSiteOrders($summary, $site->summary($days));
                }

                return [...$dashboard->apply($summary, $board['enabled']), 'dashboard' => $board];
            })
            ->values()
            ->all();

        return response()->json(['data' => $sections]);
    }

    /** The owner picks the widgets of a section's analytics. */
    public function update(Request $request, Dashboard $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'section' => ['required', Rule::in(array_column(DataTableSection::cases(), 'value'))],
            'widgets' => ['present', 'array'],
            'widgets.*' => ['string', Rule::in(DashboardWidget::values())],
        ]);

        $dashboard->save(DataTableSection::from($validated['section']), $validated['widgets']);

        return response()->json(['data' => ['section' => $validated['section'], 'widgets' => $validated['widgets']]]);
    }
}

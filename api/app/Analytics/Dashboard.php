<?php

namespace App\Analytics;

use App\DataTables\TableAnalytics;
use App\Enums\DataTableSection;
use App\Models\AnalyticsDashboard;
use App\Models\DataTable;
use Illuminate\Support\Collection;

/**
 * The analytics screen of a section as the owner set it up: which widgets
 * (DashboardWidget) are on, and a section summary (TableAnalytics::section)
 * cut down to them, for the screen and for the AI assistant alike.
 */
class Dashboard
{
    /** Figures of TableAnalytics::metrics by the widget that shows them. */
    private const METRICS = [
        'revenue' => DashboardWidget::Revenue,
        'profit' => DashboardWidget::Profit,
        'margin' => DashboardWidget::Margin,
        'count' => DashboardWidget::Count,
        'perDay' => DashboardWidget::PerDay,
        'perMonth' => DashboardWidget::PerMonth,
        'averageCheck' => DashboardWidget::AverageCheck,
        'quantity' => DashboardWidget::Units,
        'averageQuantity' => DashboardWidget::Units,
        'unitPrice' => DashboardWidget::Units,
    ];

    /** Breakdowns by the role of their column; a column without one is the business's own. */
    private const BREAKDOWNS = [
        'item' => DashboardWidget::ByItem,
        'payment' => DashboardWidget::ByPayment,
        'channel' => DashboardWidget::ByChannel,
        'status' => DashboardWidget::ByStatus,
    ];

    /**
     * The catalog for a section: every widget, whether its columns are
     * there (and which are missing), and the ones that are on.
     *
     * @param  Collection<int, DataTable>  $tables  tables of the section
     * @return array{saved: bool, enabled: list<string>, widgets: list<array{id: string, group: string, missing: list<string>}>}
     */
    public function for(DataTableSection $section, Collection $tables): array
    {
        $has = TableAnalytics::has($tables);
        $saved = AnalyticsDashboard::query()->where('section', $section->value)->first();
        $enabled = $saved
            ? array_values(array_filter($saved->widgets, fn ($id) => DashboardWidget::tryFrom($id) !== null))
            : array_map(fn (DashboardWidget $w) => $w->value, DashboardWidget::defaults($has));

        return [
            'saved' => $saved !== null,
            // A widget whose columns are gone (a column deleted) stays chosen but isn't shown.
            'enabled' => array_values(array_filter($enabled, fn (string $id) => DashboardWidget::from($id)->missing($has) === [])),
            'widgets' => array_map(fn (DashboardWidget $w) => [
                'id' => $w->value,
                'group' => $w->group(),
                'missing' => $w->missing($has),
            ], DashboardWidget::cases()),
        ];
    }

    /** @param  list<string>  $widgets */
    public function save(DataTableSection $section, array $widgets): void
    {
        AnalyticsDashboard::query()->updateOrCreate(
            ['section' => $section->value],
            ['widgets' => array_values(array_unique($widgets))],
        );
    }

    /**
     * A section summary with only what the enabled widgets show (the rows
     * and period stay: everything else is read against them).
     *
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $enabled
     * @return array<string, mixed>
     */
    public function apply(array $summary, array $enabled): array
    {
        $on = fn (DashboardWidget $w) => in_array($w->value, $enabled, true);
        $charts = $on(DashboardWidget::RevenueChart) || $on(DashboardWidget::CountChart) || $on(DashboardWidget::ProfitChart);

        return [
            ...$summary,
            'metrics' => array_values(array_filter($summary['metrics'], fn (array $m) => $on(self::METRICS[$m['key']]))),
            'series' => $charts ? $summary['series'] : [],
            'peaks' => $on(DashboardWidget::Peaks) ? $summary['peaks'] : null,
            'months' => $on(DashboardWidget::Months) ? $summary['months'] : null,
            'weekdays' => $on(DashboardWidget::Weekdays) ? $summary['weekdays'] : [],
            'hours' => $on(DashboardWidget::Hours) ? $summary['hours'] : [],
            'breakdowns' => array_values(array_filter($summary['breakdowns'], fn (array $b) => $on(self::BREAKDOWNS[$b['role']] ?? DashboardWidget::ByOwnColumns))),
            'numbers' => $on(DashboardWidget::OwnNumbers) ? $summary['numbers'] : [],
            'dashboard' => ['enabled' => $enabled],
        ];
    }
}

<?php

namespace App\Assistant;

use App\Analytics\Dashboard;
use App\DataTables\TableAnalytics;
use App\Enums\TablePeriod;
use App\Expenses\Finance;
use App\Enums\DataTableSection;
use App\Features\FeatureFlag;
use App\Models\AssistantUsage;
use App\Models\DataTable;
use App\Models\Lead;
use App\Models\Service;
use App\Services\BusinessAnalytics;
use App\Tenancy\TenantContext;
use Laravel\Pennant\Feature;

/**
 * Answers a business owner's questions about their own sales. Every request
 * carries a fresh snapshot of this business's figures; nothing from another
 * business ever reaches the prompt.
 */
class BusinessAssistant
{
    public function __construct(
        private readonly AssistantClient $client,
        private readonly BusinessAnalytics $analytics,
        private readonly TableAnalytics $tableAnalytics,
        private readonly TenantContext $tenants,
        private readonly Dashboard $dashboard,
        private readonly Finance $finance,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array{used: int, limit: int}
     */
    public function usage(): array
    {
        return [
            'used' => (int) AssistantUsage::query()->where('month', now()->format('Y-m'))->value('requests'),
            'limit' => (int) config('services.anthropic.monthly_limit'),
        ];
    }

    public function limitReached(): bool
    {
        $usage = $this->usage();

        return $usage['used'] >= $usage['limit'];
    }

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function ask(array $messages): AssistantReply
    {
        $reply = $this->client->reply($this->systemPrompt(), $messages);

        $usage = AssistantUsage::forCurrentMonth();
        $usage->increment('requests', 1, [
            'input_tokens' => $usage->input_tokens + $reply->inputTokens,
            'output_tokens' => $usage->output_tokens + $reply->outputTokens,
        ]);

        return $reply;
    }

    public function systemPrompt(): string
    {
        $tenant = $this->tenants->current();
        // Site orders only for a business that has any: no empty figures to talk about.
        $siteOrders = Feature::active(FeatureFlag::Orders->value) && Lead::query()->exists();
        $data = json_encode(array_filter([
            'business' => [
                'name' => $tenant->name,
                'currency' => $tenant->currency ?: 'AMD',
                'timezone' => $tenant->timezone,
                'today' => now($tenant->timezone ?: config('app.timezone'))->toDateString(),
                'sells' => array_values(array_filter([
                    $siteOrders ? 'services (orders)' : null,
                    Feature::active(FeatureFlag::Sales->value) ? 'goods (sales)' : null,
                ])),
            ],
            'services' => $this->services(),
            // Orders that came from the site (only for a business with the orders module).
            'siteOrders' => $siteOrders ? [
                'last30Days' => $this->analytics->summary(30),
                'last365Days' => $this->analytics->summary(365),
                'recentOrders' => $this->recentOrders(),
            ] : null,
            'ownTables' => $this->ownTables(),
            'finance' => $this->finance(),
        ], fn ($value) => $value !== null && $value !== []), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        You are the business assistant inside the admin panel of "{$tenant->name}", a small business in Armenia. The person writing to you is its owner or manager. Your job is to help them understand their sales and decide what to do next to get more orders and more revenue.

        Ground every claim in the data below. Quote the real numbers, compare periods, and point out what changed and the most likely reason. When the data cannot answer a question (for example, there are too few orders, or completed orders have no final price), say so plainly and tell them what to record so you can answer next time. Never invent figures.

        Give practical advice a small team can act on this week: pricing, which services to promote, follow-up on open orders, which channels bring orders, seasonality. Prefer two or three concrete steps over a long list.

        How the data works:
        - siteOrders: orders that came from the business's website or were added by hand on the orders screen. An order counts on the day it was created; revenue counts on the day an order was completed, from its final price. "conversion" is the share of the period's orders that are already completed. "source" is where the order came from (website, manual entry, or a UTM source).
        - ownTables: rows of tables the business designed itself (its own columns) and filled by hand or from files, e.g. sales from Instagram or a file imported every day. One entry per "section" (orders or sales), with all its tables taken together: rows and "amount" (the money a row is worth: its total column, else unit price × quantity, e.g. 0.5 kg at 3000 a kg is 1500) for the last 30 and 365 days by the date each row happened, with the previous period of the same length. "metrics" are the figures that make sense for what this business records, chosen by what its columns mean: revenue, profit and margin (only when a unit cost column exists: amount − cost × quantity), how many rows, rows a day and a month (counted from the business's first row), the average check, and per unit (kg, pieces...) the quantity sold, the average quantity of a sale and the average price of one unit. Only the figures the owner put on their analytics dashboard are given ("shownOnDashboard"); a figure that is missing was either not chosen or can't be computed from the business's columns: don't guess it, say it can be added on the analytics screen ("Ավելացնել գրաֆիկ") or which column would make it possible. "months" are the last twelve months with their best and weakest one, "weekdays" rows by day of the week (1 is Monday), "hours" rows by hour of the day. A monthly "series" and "peaks" (the best and the weakest finished month or day and the average one, by money, or by how many rows when there is no money), the most frequent values of columns of the same meaning across tables ("breakdowns", with the amount they bring) and sums of number columns (the business's own number columns). "tables" lists each table (rows and amount in the last 365 days, all rows, rows without a date, first and last date) and "latestRows" are the latest rows with their table and time. Rows without a date are in no period. Contact details of buyers are left out on purpose.
        - finance (only when the business records its expenses): money in and out per month for the last twelve months ("periods", this month the last, not over yet): "revenue" (orders and sales tables plus completed site orders), "cogs" (cost of goods: unit cost × quantity of the rows that have one), "grossProfit" = revenue − cogs, "expenses" (expenses of the categories counted in the profit), "netProfit" = grossProfit − expenses, "excluded" (expenses the owner keeps out of the profit: equipment, loan repayments, what the owner takes out...) and "cashLeft" = netProfit − excluded, "margin" = netProfit / revenue in %. "categories" are the expenses of the twelve months by category ("inProfit" false: kept out of the profit, "recurring": the part that repeats every week, month, quarter or year), "thisMonth" the same for this month, "notes" what makes the figures less exact. Talk about net profit, where the money goes, which costs grow, and what could be cut, with real numbers.
        Amounts are in {$tenant->currency}; write Armenian dram as "դր.".

        Reply in the language of the user's latest message (Armenian, Russian or English). Write natural, fluent text, as a native speaker would, with short paragraphs and simple lists. Do not use tables or headings unless asked.

        <business_data>
        {$data}
        </business_data>
        PROMPT;
    }

    /**
     * The business's own tables, per section (orders, sales): figures of all
     * its tables together, each table in short, and the latest rows.
     *
     * @return list<array<string, mixed>>
     */
    private function ownTables(): array
    {
        return $this->tableAnalytics->tables()
            ->groupBy(fn (DataTable $table) => $table->section->value)
            ->map(function ($tables, string $section) {
                $tables = $tables->values();
                $section = DataTableSection::from($section);
                // The owner's dashboard decides what's summed up here too.
                $enabled = $this->dashboard->for($section, $tables)['enabled'];
                $year = $this->dashboard->apply($this->tableAnalytics->section($section, $tables, 365), $enabled);
                $month = $this->dashboard->apply($this->tableAnalytics->section($section, $tables, 30), $enabled);

                return [
                    'section' => $section->value,
                    'shownOnDashboard' => $enabled,
                    'last30Days' => collect($month)->except(['section', 'series', 'tables', 'months', 'dashboard'])->all(),
                    'last365Days' => collect($year)->only(['period', 'rows', 'amount', 'metrics', 'series', 'peaks', 'months', 'weekdays', 'hours', 'breakdowns', 'numbers'])->all(),
                    'tables' => collect($year['tables'])->take(40)->map(fn (array $t) => collect($t)->except('id')->all())->all(),
                    'latestRows' => $this->tableAnalytics->recentRows($tables),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Money in and out a month over the last twelve months, when the
     * business records what it spends.
     *
     * @return array<string, mixed>|null
     */
    private function finance(): ?array
    {
        $report = $this->finance->report(TablePeriod::Month);

        if (! $report['has']['expenses']) {
            return null;
        }

        $line = fn (array $p) => collect($p)->only(['key', 'revenue', 'cogs', 'grossProfit', 'expenses', 'excluded', 'netProfit', 'cashLeft', 'margin'])->all();
        $spent = fn (array $categories) => collect($categories)->filter(fn ($c) => $c['amount'] > 0)->map(fn ($c) => collect($c)->only(['name', 'inProfit', 'amount', 'recurring'])->all())->values()->all();

        return [
            'periods' => array_map($line, $report['periods']),
            'total' => $line($report['total']),
            'categories' => $spent($report['categories']),
            'thisMonth' => $spent($report['current']['categories']),
            'notes' => $report['notes'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function services(): array
    {
        // Without the catalog module the business has no services to talk about.
        if (! Feature::someAreActive([FeatureFlag::Services->value, FeatureFlag::AutoCare->value])) {
            return [];
        }

        return Service::query()->orderBy('sort_order')->limit(50)->get()
            ->map(fn (Service $s) => array_filter([
                'name' => $s->trans('name', 'hy'),
                'status' => $s->status?->value,
                'priceType' => $s->price_type?->value,
                'price' => $s->price_amount,
                'priceMin' => $s->price_min,
                'priceMax' => $s->price_max,
            ], fn ($v) => $v !== null && $v !== ''))
            ->values()
            ->all();
    }

    /**
     * The latest orders without client names or contacts: the assistant
     * needs what was sold, not who bought it.
     *
     * @return list<array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return Lead::query()->with('service')->orderByDesc('created_at')->limit(30)->get()
            ->map(fn (Lead $l) => array_filter([
                'date' => $l->created_at?->toDateString(),
                'service' => $l->service?->trans('name', 'hy'),
                'status' => $l->status->value,
                'source' => $l->utm_source ?: $l->source,
                'estimate' => $l->estimated_price_min || $l->estimated_price_max
                    ? trim(($l->estimated_price_min ?? '').'-'.($l->estimated_price_max ?? ''), '-')
                    : null,
                'finalPrice' => $l->final_price,
            ], fn ($v) => $v !== null && $v !== ''))
            ->values()
            ->all();
    }
}

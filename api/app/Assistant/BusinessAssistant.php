<?php

namespace App\Assistant;

use App\Agents\Agent;
use App\Agents\AgentPrompt;
use App\Agents\AgentTools;
use App\Analytics\Dashboard;
use App\DataTables\TableAnalytics;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Enums\TablePeriod;
use App\Expenses\Finance;
use App\Features\FeatureFlag;
use App\Models\DataTable;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Services\BusinessAnalytics;
use App\Social\SocialReport;
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
        private readonly BusinessTools $tools,
        private readonly AssistantQuota $quota,
        private readonly SocialReport $social,
        private readonly AgentTools $agentTools,
        private readonly AgentPrompt $agentPrompt,
        private readonly TableActionTools $tableActions,
    ) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * This subscription month's requests: used, allowed, left and when they renew.
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        return $this->quota->status($this->tenants->current());
    }

    public function limitReached(): bool
    {
        return $this->quota->exhausted($this->tenants->current());
    }

    /**
     * What to offer once the requests ran out: plans with more of them,
     * and the day they renew anyway.
     *
     * @return array<string, mixed>
     */
    public function offer(): array
    {
        return ['plans' => $this->quota->biggerPlans($this->tenants->current())];
    }

    /**
     * Asks the assistant, or one of the business's AI agents (its role, its
     * look-ups, the brand profile and its notes). Either way it is one
     * request of the business's monthly AI requests.
     *
     * @param  list<array{role: 'user'|'assistant', content: string|list<array<string, mixed>>}>  $messages
     */
    public function ask(array $messages, ?Agent $agent = null, ?User $user = null): AssistantReply
    {
        $this->agentTools->newQuestion();
        $reply = $agent
            ? $this->client->reply($this->agentPrompt->build($agent, $this), $messages, $this->agentTools->for($agent, $user))
            : $this->client->reply($this->systemPrompt(), $messages, $this->tools);

        // Only an answer counts: a failed call throws before this.
        $this->quota->record($this->tenants->current(), $reply->inputTokens, $reply->outputTokens);

        return $reply;
    }

    /**
     * The prompt with the business's figures. $intro replaces who the
     * model is (an agent of the team, see AgentPrompt); the figures and how
     * to read them stay the same.
     */
    public function systemPrompt(?string $intro = null, ?Agent $agent = null): string
    {
        $tenant = $this->tenants->current();
        // Site orders only for a business that has any: no empty figures to talk about.
        $siteOrders = Lead::query()->exists();
        $data = json_encode(array_filter([
            'business' => [
                'name' => $tenant->name,
                'currency' => $tenant->currency ?: 'AMD',
                'timezone' => $tenant->timezone,
                'today' => now($tenant->timezone ?: config('app.timezone'))->toDateString(),
                'sells' => array_values(array_filter([
                    $siteOrders ? 'services (orders)' : null,
                    DataTable::query()->where('section', DataTableSection::Sales->value)->exists() ? 'goods (sales)' : null,
                ])),
            ],
            'services' => $this->services(),
            // Orders that came from the site (only for a business that has any).
            'siteOrders' => $siteOrders ? [
                'last30Days' => $this->analytics->summary(30),
                'last365Days' => $this->analytics->summary(365),
                'recentOrders' => $this->recentOrders(),
            ] : null,
            'ownTables' => $this->ownTables(),
            'columnMeanings' => $this->columnMeanings(),
            'finance' => $this->finance(),
            // The social module: a short summary, the tools give the rest.
            'social' => $this->social->snapshot(),
        ], fn ($value) => $value !== null && $value !== []), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $intro ??= "You are the business assistant inside the admin panel of \"{$tenant->name}\", a small business in Armenia. The person writing to you is its owner or manager. Your job is to help them understand their sales and decide what to do next to get more orders and more revenue.";

        return <<<PROMPT
        {$intro}

        Ground every claim in the data below. Quote the real numbers, compare periods, and point out what changed and the most likely reason. When the data cannot answer a question (for example, there are too few orders, or completed orders have no final price), say so plainly and tell them what to record so you can answer next time. Never invent figures.

        Give practical advice a small team can act on this week: pricing, which services to promote, follow-up on open orders, which channels bring orders, seasonality. Prefer two or three concrete steps over a long list.

        How the data works:
        - siteOrders: orders that came from the business's website or were added by hand on the orders screen. An order counts on the day it was created; revenue counts on the day an order was completed, from its final price. "conversion" is the share of the period's orders that are already completed. "source" is where the order came from (website, manual entry, or a UTM source).
        - ownTables: rows of tables the business designed itself (its own columns) and filled by hand or from files, e.g. sales from Instagram or a file imported every day. One entry per "section" (orders or sales), with all its tables taken together: rows and "amount" (the money a row is worth: its total column, which is the final price after any discount, else unit price × quantity less the discount plus the delivery fee, e.g. 0.5 kg at 3000 a kg is 1500) for the last 30 and 365 days by the date each row happened, with the previous period of the same length. "metrics" are the figures that make sense for what this business records, chosen by what its columns mean: revenue, profit and margin (only when a unit cost or commission column exists: amount − cost × quantity − commission paid to an intermediary), discounts, commissions, delivery fees and tax summed, the average duration of a service, how many rows, rows a day and a month (counted from the business's first row), the average check, and per unit (kg, pieces...) the quantity sold, the average quantity of a sale and the average price of one unit. Only the figures the owner put on their analytics dashboard are given ("shownOnDashboard"); a figure that is missing was either not chosen or can't be computed from the business's columns: don't guess it, say it can be added on the analytics screen ("Ավելացնել գրաֆիկ") or which column would make it possible. "months" are the last twelve months with their best and weakest one, "weekdays" rows by day of the week (1 is Monday), "hours" rows by hour of the day. A monthly "series" and "peaks" (the best and the weakest finished month or day and the average one, by money, or by how many rows when there is no money), the most frequent values of columns of the same meaning across tables ("breakdowns", with the amount they bring) and sums of number columns (the business's own number columns). "tables" lists each table (rows and amount in the last 365 days, all rows, rows without a date, first and last date) and "latestRows" are the latest rows with their table and time. Rows without a date are in no period. Contact details of buyers are left out on purpose.
        - columnMeanings: what the business's columns mean, whatever they are called (a column "Модель" or "Մոդել" has the meaning "model"): use these meanings to pick the column for group_rows ("by": "model", "supplier", "intermediary", "country"...), and to understand a question about a model, brand, supplier, mentor/intermediary, country of origin, material, colour, size, city, branch, service type or expense category.
        - finance (only when the business records its expenses): money in and out per month for the last twelve months ("periods", this month the last, not over yet): "revenue" (orders and sales tables plus completed site orders), "cogs" (cost of goods: unit cost × quantity of the rows that have one), "grossProfit" = revenue − cogs, "expenses" (expenses of the categories counted in the profit), "netProfit" = grossProfit − expenses, "excluded" (expenses the owner keeps out of the profit: equipment, loan repayments, what the owner takes out...) and "cashLeft" = netProfit − excluded, "margin" = netProfit / revenue in %. "categories" are the expenses of the twelve months by category ("inProfit" false: kept out of the profit, "recurring": the part that repeats every week, month, quarter or year), "thisMonth" the same for this month, "notes" what makes the figures less exact. Talk about net profit, where the money goes, which costs grow, and what could be cut, with real numbers.
        Amounts are in {$tenant->currency}; write Armenian dram as "դր.".
        {$this->socialGuide()}

        Looking things up: the data below is a summary. When a question needs more (a given day, week or month, a product, service, performer, channel or category, the biggest or latest rows, profit of a past period, site orders of some dates), use the tools to get the exact figures from the business's own records instead of estimating: list_tables shows the tables and their column labels, find_rows finds rows by dates and words, group_rows sums rows by day, week, month, weekday, hour or a column, finance_report gives profit and expenses for any period, expenses_report what was spent in any day, week, month, quarter or year (by category, payee, payment method, who spent, object), site_orders the website's orders. Dates are YYYY-MM-DD; today is in "business.today". Use as few look-ups as the answer needs, and don't mention the tools by name to the user: say what you found.

        {$this->tableActionsGuide($agent)}

        Files: the user may attach pictures, PDFs or text files to a message (named "[file #N: …]"), e.g. a price list, a receipt, a photo of a shelf or a competitor's post. Read them closely and answer about them; figures you read in a file are the file's, not the business's records.

        Reply in the language of the user's latest message (Armenian, Russian or English). Write natural, fluent text, as a native speaker would, with short paragraphs and simple lists. Do not use tables or headings unless asked.

        <business_data>
        {$data}
        </business_data>
        PROMPT;
    }

    /**
     * The business in short, for the agents that don't need its figures:
     * what it is and sells, its services, what its columns mean and its
     * social accounts (when the module is on).
     *
     * @return array<string, mixed>
     */
    public function basics(): array
    {
        $tenant = $this->tenants->current();

        return array_filter([
            'business' => [
                'name' => $tenant->name,
                'currency' => $tenant->currency ?: 'AMD',
                'today' => now($tenant->timezone ?: config('app.timezone'))->toDateString(),
                'sells' => array_values(array_filter([
                    Lead::query()->exists() || DataTable::query()->where('section', DataTableSection::Orders->value)->exists() ? 'services (orders)' : null,
                    DataTable::query()->where('section', DataTableSection::Sales->value)->exists() ? 'goods (sales)' : null,
                ])),
            ],
            'services' => $this->services(),
            'columnMeanings' => $this->columnMeanings(),
            'social' => $this->social->snapshot(),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * Splitting and joining tables, downloads: only for those who design
     * the tables, and only an agent that has these tools reads it.
     */
    public function tableActionsGuide(?Agent $agent = null): string
    {
        $tools = $agent ? array_intersect(TableActionTools::NAMES, $agent->tools()) : TableActionTools::NAMES;
        if ($tools === [] || ! $this->tableActions->available()) {
            return '';
        }

        $lines = ['Doing things with the tables: you never change the business\'s data yourself, you offer it as a card under your answer that the owner presses.'];
        if (in_array(TableActionTools::RESHAPE, $tools, true)) {
            $lines[] = '- Splitting or joining tables (reshape_tables): a year\'s table into months, months into a quarter or a year, weeks into a month, several tables into one. Find the tables with list_tables (their id, period and rows), check they are of one section, then prepare it; only periods that have rows get a table. Say in a sentence or two what it will make (how many tables, which periods, rows without a date) and that nothing changes until they press «Կիրառել» on the card; the old tables are archived and it can be undone. If the tool refuses, explain why in plain words.';
        }
        if (in_array(TableActionTools::DOWNLOAD, $tools, true)) {
            $lines[] = '- Files (download_file): when the owner wants a table, several tables, the profit report or the expenses of a period as Excel (XLSX) or CSV, offer it; the button under your answer downloads it. Never write a link or a path.';
        }

        return implode("\n", $lines);
    }

    /** How to read the social data, only when the module is on (agents read it too). */
    public function socialGuide(): string
    {
        if (! $this->social->enabled()) {
            return '';
        }

        return <<<'GUIDE'
        - social (the business's Instagram and Facebook, read from Meta on a schedule): its connected accounts with followers and the last 28 days against the 28 before, and the Instagram usernames of competitors it watches. Use social_profile for its own figures, followers' age, gender and city and how its posts do; social_posts for its posts by dates, kind or words, best or newest; social_competitors to compare with competitors (with a username: one account, even one not on the list, if it is a business or creator account). Of other accounts only public figures exist (followers, posts, likes, comments): never claim to know a competitor's reach, views, audience or ad spend. For competitors' ads give the Ad Library link from the tool. When nothing is connected ("connected": false), say the owner can connect Instagram and Facebook on the «Սոցցանցեր» screen. Tie social advice to sales when the data allows (e.g. orders by channel "Instagram" in the tables), and give concrete ideas: what kind of post, which days and hours, how often, what competitors do better.
        GUIDE;
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
     * The meanings of the business's columns (model, supplier...), in English.
     *
     * @return array<string, string>
     */
    private function columnMeanings(): array
    {
        return DataTable::query()->get()
            ->flatMap(fn (DataTable $t) => collect($t->columnDefinitions())->pluck('role')->filter())
            ->unique(fn (ColumnRole $r) => $r->value)
            ->reject(fn (ColumnRole $r) => $r->isPersonal())
            ->mapWithKeys(fn (ColumnRole $r) => [$r->value => $r->meaning()])
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
        if (! Feature::active(FeatureFlag::Services->value)) {
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

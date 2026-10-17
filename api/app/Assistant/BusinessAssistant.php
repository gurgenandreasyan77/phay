<?php

namespace App\Assistant;

use App\DataTables\TableAnalytics;
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
        $siteOrders = Feature::active(FeatureFlag::Orders->value);
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
        ], fn ($value) => $value !== null && $value !== []), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
        You are the business assistant inside the admin panel of "{$tenant->name}", a small business in Armenia. The person writing to you is its owner or manager. Your job is to help them understand their sales and decide what to do next to get more orders and more revenue.

        Ground every claim in the data below. Quote the real numbers, compare periods, and point out what changed and the most likely reason. When the data cannot answer a question (for example, there are too few orders, or completed orders have no final price), say so plainly and tell them what to record so you can answer next time. Never invent figures.

        Give practical advice a small team can act on this week: pricing, which services to promote, follow-up on open orders, which channels bring orders, seasonality. Prefer two or three concrete steps over a long list.

        How the data works:
        - siteOrders: orders that came from the business's website or were added by hand on the orders screen. An order counts on the day it was created; revenue counts on the day an order was completed, from its final price. "conversion" is the share of the period's orders that are already completed. "source" is where the order came from (website, manual entry, or a UTM source).
        - ownTables: rows of tables the business designed itself (its own columns) and filled by hand or from files, e.g. sales from Instagram or a file imported every day. One entry per "section" (orders or sales), with all its tables taken together: rows and "amount" (the money a row is worth: price × quantity when both exist) for the last 30 and 365 days by the date each row happened, with the previous period of the same length, a monthly "series", the most frequent values of columns of the same name across tables ("breakdowns", with the amount they bring) and sums of number columns. "tables" lists each table (rows and amount in the last 365 days, all rows, rows without a date, first and last date) and "latestRows" are the latest rows with their table and time. Rows without a date are in no period. Contact details of buyers are left out on purpose.
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
                $year = $this->tableAnalytics->section($section, $tables, 365);

                return [
                    'section' => $section->value,
                    'last30Days' => collect($this->tableAnalytics->section($section, $tables, 30))->except(['section', 'series', 'tables'])->all(),
                    'last365Days' => collect($year)->only(['period', 'rows', 'amount', 'series', 'breakdowns', 'numbers'])->all(),
                    'tables' => collect($year['tables'])->take(40)->map(fn (array $t) => collect($t)->except('id')->all())->all(),
                    'latestRows' => $this->tableAnalytics->recentRows($tables),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function services(): array
    {
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

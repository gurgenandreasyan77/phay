<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\AssistantReply;
use App\Models\DataTableRow;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Figures of a business's own tables (all tables of a section together) on
 * the analytics screen and in the AI assistant's prompt.
 */
class TableAnalyticsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private array $table;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 12:00:00');

        $this->shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->forTenant($this->shop)->create());

        $this->table = $this->salesTable('Instagram');
        $this->rows($this->table, [
            ['Մոմ', 2, 4500, '2026-10-16', '+37491111111'],
            ['Մոմ', 1, 4500, '2026-10-10', '+37491111111'],
            ['Օճառ', 3, 1000, '2026-10-01', '+37492222222'],
            ['Օճառ', 1, 1000, '2026-09-10', null], // previous 30 days
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function salesTable(string $name): array
    {
        return $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => $name,
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text'],
                ['label' => 'Քանակ', 'type' => 'number'],
                ['label' => 'Գին', 'type' => 'money'],
                ['label' => 'Ամսաթիվ', 'type' => 'date'],
                ['label' => 'Հեռախոս', 'type' => 'text'],
            ],
        ])->assertCreated()->json('data');
    }

    private function rows(array $table, array $rows): void
    {
        foreach ($rows as $row) {
            $values = array_combine(array_column($table['columns'], 'key'), $row);
            $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => $values])->assertCreated();
        }
    }

    public function test_the_analytics_screen_gets_figures_of_all_tables_of_a_section(): void
    {
        // A second day's file, made as its own table with the same columns.
        $this->rows($this->salesTable('17.10'), [['Մոմ', 1, 5000, '2026-10-17', null]]);
        // A row from before dates were required: in no period.
        DataTableRow::withoutTenancy()->where('data_table_id', $this->table['id'])->orderBy('id')->first()->forceFill(['occurred_at' => null])->saveQuietly();

        $summary = $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->assertJsonCount(1, 'data')->json('data.0');

        $this->assertSame('sales', $summary['section']);
        $this->assertSame(['total' => 5, 'withoutDate' => 1, 'current' => 3, 'previous' => 1], $summary['rows']);
        // 1×4500 + 3×1000 + 1×5000
        $this->assertSame(['current' => 12500, 'previous' => 1000, 'average' => 4166.67], $summary['amount']);
        $this->assertCount(30, $summary['series']);
        $this->assertSame(['date' => '2026-10-17', 'rows' => 1, 'amount' => 5000], $summary['series'][29]);

        // Products of both tables together; the phone column is not broken down.
        $this->assertSame(['Ապրանք'], array_column($summary['breakdowns'], 'label'));
        $this->assertSame(
            [['value' => 'Մոմ', 'rows' => 2, 'amount' => 9500], ['value' => 'Օճառ', 'rows' => 1, 'amount' => 3000]],
            $summary['breakdowns'][0]['values'],
        );
        // Quantities are in the metrics, not among the table's own number columns.
        $this->assertSame([], $summary['numbers']);

        // The figures the columns allow: money, how many, quantities.
        $this->assertSame(
            ['revenue', 'count', 'perDay', 'averageCheck', 'quantity', 'averageQuantity', 'unitPrice'],
            array_column($summary['metrics'], 'key'),
        );
        $metric = fn (string $key) => collect($summary['metrics'])->firstWhere('key', $key);
        $this->assertSame([12500, 1000], [$metric('revenue')['value'], $metric('revenue')['previous']]);
        // 3 rows in 30 days; the previous period only from the first row (10.09), 8 days.
        $this->assertSame([0.1, 0.13], [$metric('perDay')['value'], $metric('perDay')['previous']]);
        $this->assertSame([4166.67, 1000], [$metric('averageCheck')['value'], $metric('averageCheck')['previous']]);
        $this->assertSame([5, 1.67, 2500], [$metric('quantity')['value'], $metric('averageQuantity')['value'], $metric('unitPrice')['value']]);

        // The best day by money; the weakest is a finished day.
        $this->assertSame('amount', $summary['peaks']['by']);
        $this->assertSame(['date' => '2026-10-17', 'rows' => 1, 'amount' => 5000], $summary['peaks']['best']);
        $this->assertSame(0, $summary['peaks']['worst']['amount']);

        $this->assertSame(['Instagram', '17.10'], array_column($summary['tables'], 'name'));
        $this->assertSame('Գին × Քանակ', $summary['tables'][0]['amountColumn']);
        $this->assertSame(['rows' => 2, 'amount' => 7500, 'total' => 4, 'withoutDate' => 1], collect($summary['tables'][0])->only(['rows', 'amount', 'total', 'withoutDate'])->all());

        $this->getJson('/api/v1/admin/analytics/tables?period=365')
            ->assertOk()
            ->assertJsonPath('data.0.rows.current', 4)
            ->assertJsonCount(13, 'data.0.series');
    }

    public function test_changing_what_a_column_means_counts_the_rows_again(): void
    {
        $this->assertSame('sales', $this->table['section']);
        $this->assertSame(
            ['item', 'quantity', 'unit_price', 'date', 'phone'],
            array_column($this->table['columns'], 'role'),
            'Roles are guessed from the names of columns sent without one.',
        );

        // The price column no longer counted as a price: no money at all.
        $columns = $this->table['columns'];
        $columns[2]['role'] = null;
        $this->patchJson("/api/v1/admin/data-tables/{$this->table['id']}", ['columns' => $columns])->assertOk();
        $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->assertJsonPath('data.0.amount', null);

        // A total column filled on a row wins over price × quantity.
        $columns[2]['role'] = 'unit_price';
        $columns[] = ['label' => 'Վճարված', 'type' => 'money', 'role' => 'amount'];
        $table = $this->patchJson("/api/v1/admin/data-tables/{$this->table['id']}", ['columns' => $columns])->assertOk()->json('data');
        $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->assertJsonPath('data.0.amount.current', 16500);

        $row = DataTableRow::withoutTenancy()->orderBy('id')->first();
        $values = [...$row->data, $table['columns'][5]['key'] => 8000];
        $this->patchJson("/api/v1/admin/data-tables/{$this->table['id']}/rows/{$row->id}", ['values' => $values])->assertOk();
        // 8000 instead of 2 × 4500
        $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->assertJsonPath('data.0.amount.current', 15500);
    }

    public function test_a_price_per_kilogram_times_a_weight_is_counted_exactly(): void
    {
        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Կշռով',
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text', 'role' => 'item'],
                // Named in Latin letters: only the role says it's the price.
                ['label' => 'gin', 'type' => 'money', 'role' => 'unit_price'],
                ['label' => 'Քաշ', 'type' => 'number', 'role' => 'quantity'],
                ['label' => 'Միավոր', 'type' => 'select', 'role' => 'unit', 'options' => ['կգ', 'հատ']],
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
            ],
        ])->assertCreated()->assertJsonPath('data.amountLabel', 'gin × Քաշ')->json('data');

        $this->rows($table, [
            ['Պանիր', 3000, '0,5', 'կգ', '2026-10-17'],
            ['Մեղր', '1999.99', '0.3', 'կգ', '2026-10-17'],
            ['Մոմ', 4500, 2, 'հատ', '2026-10-17'],
        ]);

        // 1500 + 599.997 → 600 + 9000
        $this->assertSame([1500.0, 600.0, 9000.0], DataTableRow::withoutTenancy()->where('data_table_id', $table['id'])->orderBy('id')->pluck('amount')->all());

        $sales = $this->getJson('/api/v1/admin/analytics/tables?period=7')->assertOk()->json('data.0');
        $this->assertSame(11100, collect($sales['tables'])->firstWhere('name', 'Կշռով')['amount']);
        // Kilograms and pieces are not added together: sold, per sale, price of one.
        $byUnit = fn (string $unit) => collect($sales['metrics'])->where('unit', $unit)->mapWithKeys(fn ($m) => [$m['key'] => $m['value']])->all();
        $this->assertSame(['quantity' => 0.8, 'averageQuantity' => 0.4, 'unitPrice' => 2625], $byUnit('կգ'));
        $this->assertSame(['quantity' => 2, 'averageQuantity' => 2, 'unitPrice' => 4500], $byUnit('հատ'));
        $this->assertSame('1 կգ-ի միջին գինը', collect($sales['metrics'])->firstWhere(fn ($m) => $m['key'] === 'unitPrice' && $m['unit'] === 'կգ')['label']);
    }

    public function test_a_service_business_gets_orders_a_day_and_its_months_not_money_or_kilograms(): void
    {
        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Զանգեր',
            'columns' => [
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
            ],
        ])->assertCreated()->json('data');
        // Started in June: 3 orders in June, 1 in August, 2 in October.
        $this->rows($table, [
            ['Լվացք', '2026-06-03'], ['Լվացք', '2026-06-10'], ['Մաքրում', '2026-06-20'],
            ['Մաքրում', '2026-08-05'],
            ['Լվացք', '2026-10-01'], ['Լվացք', '2026-10-15'],
        ]);

        $year = collect($this->getJson('/api/v1/admin/analytics/tables?period=365')->assertOk()->json('data'))->firstWhere('section', 'orders');

        // Without money: what fits a service business, nothing about money or kilograms.
        $this->assertSame(['count', 'perDay', 'countChart', 'peaks', 'byItem'], $year['dashboard']['enabled']);
        $this->assertFalse($year['dashboard']['saved']);
        $this->assertSame(['money'], collect($year['dashboard']['widgets'])->firstWhere('id', 'revenue')['missing']);
        $this->assertSame(['datetime'], collect($year['dashboard']['widgets'])->firstWhere('id', 'hours')['missing']);

        // The owner adds orders a month and the months of the year.
        $this->putJson('/api/v1/admin/analytics/dashboard', ['section' => 'orders', 'widgets' => ['count', 'perDay', 'perMonth', 'peaks', 'months']])->assertOk();
        $year = collect($this->getJson('/api/v1/admin/analytics/tables?period=365')->assertOk()->json('data'))->firstWhere('section', 'orders');

        $this->assertTrue($year['dashboard']['saved']);
        $this->assertSame('orders', $year['section']);
        $this->assertNull($year['amount']);
        $this->assertSame([], $year['breakdowns'], 'By service is no longer chosen.');
        $this->assertSame(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10'], array_column($year['months']['series'], 'date'));
        $this->assertSame(['count', 'perDay', 'perMonth'], array_column($year['metrics'], 'key'));
        $this->assertSame('Պատվերներ ամսական', $year['metrics'][2]['label']);
        // 6 orders since 03.06, 137 days: 0.04 a day, 1.33 a month.
        $this->assertEquals([0.04, 1.33], [$year['metrics'][1]['value'], $year['metrics'][2]['value']]);

        // Months by how many orders, from June: the best is June, the weakest a finished month without orders.
        $this->assertSame('rows', $year['peaks']['by']);
        $this->assertSame('2026-06', $year['peaks']['best']['date']);
        $this->assertSame(['date' => '2026-07', 'rows' => 0, 'amount' => 0], $year['peaks']['worst']);
        // June to September: 4 orders in 4 finished months.
        $this->assertSame(1, $year['peaks']['average']);
        $this->assertSame(5, $year['peaks']['buckets']);
    }

    public function test_a_unit_cost_adds_profit_and_margin(): void
    {
        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Մրգեր',
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Կգ', 'type' => 'number', 'role' => 'quantity'],
                ['label' => 'Գին', 'type' => 'money', 'role' => 'unit_price'],
                ['label' => 'Ինքնարժեք', 'type' => 'money', 'role' => 'cost'],
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
            ],
        ])->assertCreated()->json('data');
        $this->rows($table, [
            ['Խնձոր', 2, 800, 500, '2026-10-17'],  // 1600 − 1000
            ['Տանձ', '0.5', 1200, null, '2026-10-17'], // no cost: not in profit, nor the Instagram table's row
        ]);

        $sales = $this->getJson('/api/v1/admin/analytics/tables?period=7')->assertOk()->json('data.0');
        // Profit is on by default once there is a cost; the margin is offered.
        $this->assertContains('profit', $sales['dashboard']['enabled']);
        $this->assertNotContains('margin', $sales['dashboard']['enabled']);
        $this->assertSame([], collect($sales['dashboard']['widgets'])->firstWhere('id', 'margin')['missing']);

        $this->putJson('/api/v1/admin/analytics/dashboard', ['section' => 'sales', 'widgets' => ['profit', 'margin', 'profitChart']])->assertOk();
        $sales = $this->getJson('/api/v1/admin/analytics/tables?period=7')->assertOk()->json('data.0');
        $this->assertSame(['profit', 'margin'], array_column($sales['metrics'], 'key'));
        $this->assertSame(600, $sales['series'][6]['profit']);
        $profit = collect($sales['metrics'])->firstWhere('key', 'profit');

        $this->assertSame(600, $profit['value']);
        $this->assertSame('Հաշվված է 3 տողից 1-ի համար, որոնց ինքնարժեքը լրացված է։', $profit['note']);
        $this->assertSame(37.5, collect($sales['metrics'])->firstWhere('key', 'margin')['value']);
    }

    public function test_the_dashboard_takes_only_known_widgets_and_the_ai_gets_only_the_chosen_ones(): void
    {
        $this->putJson('/api/v1/admin/analytics/dashboard', ['section' => 'sales', 'widgets' => ['revenue', 'pie']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('widgets.1');
        $this->putJson('/api/v1/admin/analytics/dashboard', ['section' => 'cars', 'widgets' => []])->assertUnprocessable();

        // Only how many: no money and no products reach the screen or the AI.
        $this->putJson('/api/v1/admin/analytics/dashboard', ['section' => 'sales', 'widgets' => ['count', 'weekdays']])->assertOk();
        $sales = $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->json('data.0');
        $this->assertSame(['count'], array_column($sales['metrics'], 'key'));
        $this->assertSame([], $sales['breakdowns']);
        $this->assertSame(7, count($sales['weekdays']));
        // 01.10, 16.10 and 10.10: a Thursday, a Friday and a Saturday.
        $this->assertSame([4 => 1, 5 => 1, 6 => 1], collect($sales['weekdays'])->where('rows', '>', 0)->mapWithKeys(fn ($d) => [$d['day'] => $d['rows']])->all());

        $client = new class implements AssistantClient
        {
            public string $system = '';

            public function isConfigured(): bool
            {
                return true;
            }

            public function reply(string $system, array $messages): AssistantReply
            {
                $this->system = $system;

                return new AssistantReply('ok', 1, 1);
            }
        };
        $this->app->instance(AssistantClient::class, $client);
        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Ինչպե՞ս են վաճառքները']]])->assertOk();

        $this->assertStringContainsString('"shownOnDashboard"', $client->system);
        $this->assertStringContainsString('"weekdays"', $client->system);
        $this->assertStringNotContainsString('"label": "Եկամուտ"', $client->system);
        $this->assertStringNotContainsString('"value": "Մոմ"', $client->system);
    }

    public function test_a_role_is_on_one_column_of_a_type_it_allows(): void
    {
        $columns = [['label' => 'Գին', 'type' => 'money', 'role' => 'unit_price'], ['label' => 'Цена', 'type' => 'money', 'role' => 'unit_price']];
        $this->postJson('/api/v1/admin/data-tables', ['section' => 'sales', 'name' => 'X', 'columns' => $columns])
            ->assertUnprocessable()->assertJsonValidationErrors('columns.1.role');

        $columns = [['label' => 'Ամսաթիվ', 'type' => 'text', 'role' => 'date']];
        $this->postJson('/api/v1/admin/data-tables', ['section' => 'sales', 'name' => 'X', 'columns' => $columns])
            ->assertUnprocessable()->assertJsonValidationErrors('columns.0.type');
    }

    public function test_table_orders_and_site_orders_are_counted_together(): void
    {
        $orders = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Excel',
            'columns' => [['label' => 'Ծառայություն', 'type' => 'text'], ['label' => 'Ամսաթիվ', 'type' => 'datetime'], ['label' => 'Գումար', 'type' => 'money']],
        ])->assertCreated()->json('data');
        $this->rows($orders, [['Բազմոց', '16.10.2026 10:30', 9900]]);

        $this->asTenant($this->shop, function () {
            $lead = Lead::create(['name' => 'Client', 'phone' => '+37491000000', 'preferred_locale' => 'hy', 'consent' => true, 'source' => 'website']);
            $lead->forceFill(['status' => 'completed', 'final_price' => 15000, 'created_at' => now()->subHour(), 'completed_at' => now()->subHour()])->saveQuietly();
        });

        $section = collect($this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->json('data'))->firstWhere('section', 'orders');

        $this->assertSame(2, $section['withSite']['orders']);
        $this->assertSame(24900, $section['withSite']['revenue']);
        $this->assertSame(['date' => '2026-10-16', 'rows' => 1, 'amount' => 9900], $section['withSite']['series'][28]);
        $this->assertSame(['date' => '2026-10-17', 'rows' => 1, 'amount' => 15000], $section['withSite']['series'][29]);
    }

    public function test_tables_are_listed_with_their_figures_and_deleted_together(): void
    {
        $second = $this->salesTable('17.10');

        $listed = $this->getJson('/api/v1/admin/data-tables?section=sales')->assertOk()->json('data.0');
        $this->assertSame(
            ['rowsCount' => 4, 'rowsWithoutDate' => 0, 'firstAt' => '2026-09-10 00:00', 'lastAt' => '2026-10-16 00:00', 'amountTotal' => 17500],
            collect($listed)->only(['rowsCount', 'rowsWithoutDate', 'firstAt', 'lastAt', 'amountTotal'])->all(),
        );

        $this->postJson('/api/v1/admin/data-tables/bulk-delete', ['ids' => [$this->table['id'], $second['id']]])->assertNoContent();

        $this->getJson('/api/v1/admin/data-tables')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, DataTableRow::withoutTenancy()->count());
    }

    public function test_only_the_owner_sees_table_figures(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'admin')->create());

        $this->getJson('/api/v1/admin/analytics/tables')->assertForbidden();
        $this->getJson('/api/v1/admin/analytics/statistics')->assertForbidden();
    }

    public function test_statistics_show_every_figure_whatever_the_dashboard_shows(): void
    {
        $this->putJson('/api/v1/admin/analytics/dashboard', ['section' => 'sales', 'widgets' => ['count']])->assertOk();

        $sales = $this->getJson('/api/v1/admin/analytics/statistics?period=30')->assertOk()->json('data.0');
        $this->assertSame('sales', $sales['section']);
        $this->assertArrayNotHasKey('dashboard', $sales);
        $this->assertContains('revenue', array_column($sales['metrics'], 'key'));
        $this->assertCount(30, $sales['series']);
        $this->assertSame($sales['rows']['current'], array_sum(array_column($sales['series'], 'rows')));
        $this->assertNotSame([], $sales['breakdowns']);

        $this->getJson('/api/v1/admin/analytics/statistics?period=12')->assertUnprocessable();
    }

    public function test_the_assistant_reads_the_tables_without_buyer_contacts(): void
    {
        $client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $client);

        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Ո՞ր ապրանքն է լավ վաճառվում']]])
            ->assertOk();

        $this->assertStringContainsString('"ownTables"', $client->system);
        $this->assertStringContainsString('"name": "Instagram"', $client->system);
        $this->assertStringContainsString('"amountColumn": "Գին × Քանակ"', $client->system);
        $this->assertStringContainsString('"Ապրանք": "Մոմ"', $client->system);
        $this->assertStringContainsString('"label": "Միջին չեկ"', $client->system);
        $this->assertStringContainsString('"peaks"', $client->system);
        $this->assertStringNotContainsString('37491111111', $client->system);
        // A shop without the orders module gets no (empty) site orders section.
        $this->assertStringNotContainsString('"siteOrders"', $client->system);
        $this->assertStringContainsString('goods (sales)', $client->system);
    }
}

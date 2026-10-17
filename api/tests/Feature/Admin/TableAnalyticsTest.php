<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Models\DataTableRow;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Pennant\Feature;
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
        Feature::for($this->shop)->activate('sales');
        Feature::for($this->shop)->deactivate('orders');
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
        $this->assertSame([['label' => 'Քանակ', 'unit' => null, 'sum' => 5, 'filled' => 3]], $summary['numbers']);

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
        // Kilograms and pieces are not added together.
        $this->assertSame(
            [['label' => 'Քանակ', 'unit' => 'կգ', 'sum' => 0.8, 'filled' => 2], ['label' => 'Քանակ', 'unit' => 'հատ', 'sum' => 2, 'filled' => 1]],
            collect($sales['numbers'])->where('unit', '!==', null)->values()->all(),
        );
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
        Feature::for($this->shop)->activate('orders');
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
        $this->assertStringNotContainsString('37491111111', $client->system);
        // A shop without the orders module gets no (empty) site orders section.
        $this->assertStringNotContainsString('"siteOrders"', $client->system);
        $this->assertStringContainsString('goods (sales)', $client->system);
    }
}

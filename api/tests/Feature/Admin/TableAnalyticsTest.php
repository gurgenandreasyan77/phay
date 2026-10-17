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
        $this->assertSame([['label' => 'Քանակ', 'sum' => 5, 'filled' => 3]], $summary['numbers']);

        $this->assertSame(['Instagram', '17.10'], array_column($summary['tables'], 'name'));
        $this->assertSame('Գին × Քանակ', $summary['tables'][0]['amountColumn']);
        $this->assertSame(['rows' => 2, 'amount' => 7500, 'total' => 4, 'withoutDate' => 1], collect($summary['tables'][0])->only(['rows', 'amount', 'total', 'withoutDate'])->all());

        $this->getJson('/api/v1/admin/analytics/tables?period=365')
            ->assertOk()
            ->assertJsonPath('data.0.rows.current', 4)
            ->assertJsonCount(13, 'data.0.series');
    }

    public function test_changing_the_date_or_amount_column_counts_the_rows_again(): void
    {
        $columns = $this->table['columns'];
        $columns[] = ['label' => 'Վճարված', 'type' => 'money'];

        $this->patchJson("/api/v1/admin/data-tables/{$this->table['id']}", [
            'columns' => $columns,
            'amount_column' => 'Վճարված',
            'quantity_column' => null,
        ])->assertOk()->assertJsonPath('data.quantityColumn', null);

        // No row has the new money column filled yet.
        $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->assertJsonPath('data.0.amount.current', 0);

        // Back to the price; the quantity column, not chosen, is picked again.
        $this->patchJson("/api/v1/admin/data-tables/{$this->table['id']}", ['amount_column' => $columns[2]['key']])->assertOk();
        $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->assertJsonPath('data.0.amount.current', 16500);
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

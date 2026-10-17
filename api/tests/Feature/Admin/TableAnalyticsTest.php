<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Figures of a business's own tables on the analytics screen and in the AI
 * assistant's prompt.
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

        $this->table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Instagram',
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text'],
                ['label' => 'Քանակ', 'type' => 'number'],
                ['label' => 'Գին', 'type' => 'money'],
                ['label' => 'Ամսաթիվ', 'type' => 'date'],
                ['label' => 'Հեռախոս', 'type' => 'text'],
            ],
        ])->assertCreated()->json('data');

        foreach ([
            ['Մոմ', 2, 4500, '2026-10-16', '+37491111111'],
            ['Մոմ', 1, 4500, '2026-10-10', '+37491111111'],
            ['Օճառ', 3, 1000, '2026-10-01', '+37492222222'],
            ['Օճառ', 1, 1000, '2026-09-10', null], // previous 30 days
            ['Բաժակ', null, 900, null, null],        // no date: outside every period
        ] as [$product, $qty, $price, $date, $phone]) {
            $values = array_combine(array_column($this->table['columns'], 'key'), [$product, $qty, $price, $date, $phone]);
            $this->postJson("/api/v1/admin/data-tables/{$this->table['id']}/rows", ['values' => $values])->assertCreated();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_analytics_screen_gets_figures_of_each_table(): void
    {
        $summary = $this->getJson('/api/v1/admin/analytics/tables?period=30')->assertOk()->json('data.0');

        $this->assertSame('Instagram', $summary['table']['name']);
        $this->assertSame('Ամսաթիվ', $summary['dateColumn']);
        $this->assertSame('Գին × Քանակ', $summary['amountColumn']);
        $this->assertSame(['total' => 5, 'withoutDate' => 1, 'current' => 3, 'previous' => 1], $summary['rows']);
        // 2×4500 + 1×4500 + 3×1000
        $this->assertSame(['current' => 16500, 'previous' => 1000, 'average' => 5500], $summary['amount']);
        $this->assertSame(['Քանակ', 'Գին'], array_column($summary['numbers'], 'label'));
        $this->assertSame(6, $summary['numbers'][0]['sum']);
        $this->assertCount(30, $summary['series']);
        $this->assertSame(['date' => '2026-10-16', 'rows' => 1, 'amount' => 9000], $summary['series'][28]);

        // Products by the money they brought; the phone column is not broken down.
        $this->assertSame(['Ապրանք'], array_column($summary['breakdowns'], 'label'));
        $this->assertSame(
            [['value' => 'Մոմ', 'rows' => 2, 'amount' => 13500], ['value' => 'Օճառ', 'rows' => 1, 'amount' => 3000]],
            $summary['breakdowns'][0]['values'],
        );

        $this->getJson('/api/v1/admin/analytics/tables?period=365')
            ->assertOk()
            ->assertJsonPath('data.0.rows.current', 4)
            ->assertJsonCount(13, 'data.0.series');
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

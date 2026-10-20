<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The business home: numbers from the business's own tables, expenses and
 * site orders, money only for the roles that see it.
 */
class DashboardHomeTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    protected function setUp(): void
    {
        parent::setUp();

        // A Monday: the week starts today.
        $this->travelTo('2026-10-19 12:00');
        $this->salon = $this->createTenant('test-salon');
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'owner')->create());
    }

    /** @return array{id: int, columns: list<array{key: string}>} */
    private function table(string $section, array $columns, string $name = 'Աղյուսակ'): array
    {
        return $this->postJson('/api/v1/admin/data-tables', ['section' => $section, 'name' => $name, 'columns' => $columns])
            ->assertCreated()->json('data');
    }

    private function row(array $table, array $values): void
    {
        $keys = array_column($table['columns'], 'key');
        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => array_combine($keys, $values)])->assertCreated();
    }

    private function fillBusiness(): void
    {
        $orders = $this->table('orders', [
            ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
            ['label' => 'Ամսաթիվ', 'type' => 'datetime', 'role' => 'date'],
            ['label' => 'Հաճախորդ', 'type' => 'text', 'role' => 'customer'],
            ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
        ], 'Պատվերներ');
        $this->row($orders, ['Բազմոց', '2026-10-19 10:00', 'Անի', 5000]);
        $this->row($orders, ['Բազմոց', '2026-10-18 15:00', 'Արամ', 3000]);
        $this->row($orders, ['Ներքնակ', '2026-10-02 09:00', 'Լուսինե', 2000]);
        // Last month: the same days count, the 25th doesn't.
        $this->row($orders, ['Բազմոց', '2026-09-10 11:00', 'Գոռ', 4000]);
        $this->row($orders, ['Բազմոց', '2026-09-25 11:00', 'Գոռ', 9999]);
        // Coming up on Wednesday.
        $this->row($orders, ['Անկյունային բազմոց', '2026-10-21 14:00', 'Մարիամ', 7000]);

        $expenses = $this->table('expenses', [
            ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
            ['label' => 'Տեսակ', 'type' => 'text', 'role' => 'category'],
            ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
        ], 'Ծախսեր');
        $this->row($expenses, ['2026-10-01', 'Վարձակալություն', 3000]);

        $this->asTenant($this->salon, fn () => Lead::create([
            'name' => 'Կարեն', 'phone' => '+37491000000', 'preferred_locale' => 'hy', 'consent' => true,
            'source' => 'website', 'preferred_date' => '2026-10-20',
        ]));
    }

    public function test_the_owner_sees_orders_money_net_profit_and_what_is_coming(): void
    {
        $this->fillBusiness();

        $data = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->assertSame('2026-10-19', $data['today']);
        $this->assertTrue($data['money']);

        $this->assertCount(1, $data['sections']);
        $orders = $data['sections'][0];
        $this->assertSame('orders', $orders['section']);
        $this->assertSame(1, $orders['tables']);
        $this->assertSame(6, $orders['rows']);
        $this->assertSame(['count' => 1, 'amount' => 5000], $orders['today']);
        $this->assertSame(['count' => 1, 'amount' => 5000], $orders['week']);
        $this->assertSame(['count' => 3, 'amount' => 10000], $orders['month']);
        $this->assertSame(['count' => 1, 'amount' => 4000], $orders['previousMonth']);
        // The latest that already happened, not the one on Wednesday.
        $this->assertSame('2026-10-19 10:00', $orders['lastAt']);

        $this->assertEquals(10000, $data['finance']['revenue']);
        $this->assertEquals(3000, $data['finance']['expenses']);
        $this->assertEquals(7000, $data['finance']['netProfit']);
        $this->assertEquals(4000, $data['finance']['previous']['netProfit']);

        $upcoming = $data['upcoming'];
        $this->assertSame(3, $upcoming['total']);
        $this->assertSame(['row', 'site', 'row'], array_column($upcoming['items'], 'kind'));
        $this->assertSame(['2026-10-19', '2026-10-20', '2026-10-21'], array_column($upcoming['items'], 'date'));
        $this->assertSame('Անկյունային բազմոց', $upcoming['items'][2]['title']);
        $this->assertSame('14:00', $upcoming['items'][2]['time']);
        $this->assertSame('Մարիամ', $upcoming['items'][2]['customer']);
        $this->assertEquals(7000, $upcoming['items'][2]['amount']);

        $this->assertSame(1, $data['site']['newToday']);
        $this->assertSame(1, $data['site']['waiting']);
        $this->assertSame(0, $data['site']['inWork']);
        $this->assertSame('Կարեն', $data['site']['recent'][0]['name']);
    }

    public function test_a_moderator_sees_counts_without_money(): void
    {
        $this->fillBusiness();
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'moderator')->create());

        $data = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->assertFalse($data['money']);
        $this->assertNull($data['finance']);
        $this->assertFalse($data['sections'][0]['hasAmount']);
        $this->assertSame(['count' => 3, 'amount' => null], $data['sections'][0]['month']);
        $this->assertSame([null, null, null], array_column($data['upcoming']['items'], 'amount'));
        $this->assertNull($data['site']['revenueMonth']);
    }

    public function test_an_admin_sees_no_money_either(): void
    {
        $this->fillBusiness();
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'admin')->create());

        $data = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->assertFalse($data['money']);
        $this->assertNull($data['finance']);
    }

    public function test_a_shop_that_keeps_only_sales_gets_its_sales_and_no_site(): void
    {
        $sales = $this->table('sales', [
            ['label' => 'Ապրանք', 'type' => 'text', 'role' => 'item'],
            ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
            ['label' => 'Քանակ', 'type' => 'number', 'role' => 'quantity'],
            ['label' => 'Գին', 'type' => 'money', 'role' => 'unit_price'],
        ]);
        $this->row($sales, ['Մոմ', '2026-10-19', 3, 1500]);

        $data = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->assertSame(['sales'], array_column($data['sections'], 'section'));
        $this->assertSame(['count' => 1, 'amount' => 4500], $data['sections'][0]['today']);
        $this->assertSame([], $data['upcoming']['items']);
        $this->assertNull($data['site']);
    }

    public function test_an_empty_business_gets_zeros_and_its_sections(): void
    {
        $data = $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->assertSame(0, $data['sections'][0]['tables']);
        $this->assertSame(['count' => 0, 'amount' => null], $data['sections'][0]['month']);
        $this->assertSame([], $data['upcoming']['items']);
        // No site orders yet: no block for them.
        $this->assertNull($data['site']);
    }
}

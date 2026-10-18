<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The calendar: orders of the order tables and of the site by day, and
 * rows added or deleted from it counted by the analytics like any other.
 */
class AdminCalendarTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = $this->createTenant('test-salon');
        $user = User::factory()->forTenant($this->salon, 'owner')->create();
        Sanctum::actingAs($user);
    }

    /** @return array{id: int, columns: list<array{key: string}>} */
    private function table(string $name, string $dateType = 'datetime', string $section = 'orders'): array
    {
        return $this->postJson('/api/v1/admin/data-tables', [
            'section' => $section,
            'name' => $name,
            'columns' => [
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Ամսաթիվ', 'type' => $dateType, 'role' => 'date'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
            ],
        ])->assertCreated()->json('data');
    }

    private function addRow(array $table, string $item, string $date, int $amount): int
    {
        [$itemKey, $dateKey, $amountKey] = array_column($table['columns'], 'key');

        return $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [
            $itemKey => $item, $dateKey => $date, $amountKey => $amount,
        ]])->assertCreated()->json('data.id');
    }

    public function test_it_lists_rows_of_order_tables_and_site_orders_by_day(): void
    {
        $visits = $this->table('Այցեր');
        $home = $this->table('Տնային', 'date');
        $this->addRow($visits, 'Բազմոց', '2026-10-05 14:30', 9900);
        $this->addRow($home, 'Ներքնակ', '2026-10-05', 5000);
        $this->addRow($visits, 'Ուշ', '2026-11-02 10:00', 1000);

        $noDate = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Առանց ամսաթվի',
            'columns' => [['label' => 'Նշում', 'type' => 'text']],
        ])->assertCreated()->json('data.id');

        $this->asTenant($this->salon, fn () => Lead::create([
            'name' => 'Անի', 'phone' => '+37491000000', 'preferred_locale' => 'hy', 'consent' => true,
            'source' => 'website', 'preferred_date' => '2026-10-07',
        ]));

        $response = $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2026-10-31')->assertOk();

        // The range's last day counts too.
        $this->assertCount(1, $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2026-10-07')->json('data.leads'));
        $this->assertCount(0, $this->getJson('/api/v1/admin/calendar?from=2026-10-08&to=2026-10-31')->json('data.leads'));

        $this->assertEqualsCanonicalizing([$visits['id'], $home['id']], array_column($response->json('data.tables'), 'id'));
        $this->assertNotContains($noDate, array_column($response->json('data.tables'), 'id'));

        $rows = $response->json('data.rows');
        $this->assertCount(2, $rows);
        // A date column has no time: the row is for the whole day, before the timed ones.
        $this->assertSame(['2026-10-05', null, 5000], [$rows[0]['date'], $rows[0]['time'], (int) $rows[0]['amount']]);
        $this->assertSame('Ներքնակ', $rows[0]['values'][$home['columns'][0]['key']]);
        $this->assertSame(['2026-10-05', '14:30', 9900], [$rows[1]['date'], $rows[1]['time'], (int) $rows[1]['amount']]);

        $this->assertSame([['2026-10-07', 'Անի', 'new']], array_map(fn ($l) => [$l['date'], $l['name'], $l['status']], $response->json('data.leads')));
        $this->assertFalse($response->json('data.truncated'));
    }

    public function test_a_row_deleted_from_the_calendar_leaves_the_analytics(): void
    {
        $visits = $this->table('Այցեր');
        $this->addRow($visits, 'Բազմոց', '2026-10-05 14:30', 9900);
        $second = $this->addRow($visits, 'Ներքնակ', '2026-10-05 16:00', 5000);

        $this->assertCount(2, $this->getJson('/api/v1/admin/calendar?from=2026-10-05&to=2026-10-05')->json('data.rows'));

        $this->deleteJson("/api/v1/admin/data-tables/{$visits['id']}/rows/{$second}")->assertNoContent();

        $rows = $this->getJson('/api/v1/admin/calendar?from=2026-10-05&to=2026-10-05')->json('data.rows');
        $this->assertSame([9900], array_map(fn ($r) => (int) $r['amount'], $rows));
        $this->assertDatabaseMissing('data_table_rows', ['id' => $second]);
    }

    public function test_sales_tables_and_other_businesses_stay_out(): void
    {
        Feature::for($this->salon)->activate('sales');
        $sales = $this->table('Վաճառքներ', 'date', 'sales');
        $this->addRow($sales, 'Մոմ', '2026-10-05', 3000);

        $other = $this->createTenant('other-salon');
        $this->asTenant($other, fn () => Lead::create([
            'name' => 'Ուրիշ', 'phone' => '+37491000001', 'preferred_locale' => 'hy', 'consent' => true,
            'source' => 'website', 'preferred_date' => '2026-10-05',
        ]));

        $response = $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2026-10-31')->assertOk();

        $this->assertSame([], $response->json('data.tables'));
        $this->assertSame([], $response->json('data.rows'));
        $this->assertSame([], $response->json('data.leads'));
    }

    public function test_it_needs_the_orders_module_and_a_short_range(): void
    {
        $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2027-01-31')->assertStatus(422);
        $this->getJson('/api/v1/admin/calendar?from=2026-10-31&to=2026-10-01')->assertStatus(422);

        Feature::for($this->salon)->deactivate('orders');

        $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2026-10-31')->assertNotFound();
    }

    public function test_a_moderator_sees_the_calendar(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'moderator')->create());

        $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2026-10-31')->assertOk();
    }
}

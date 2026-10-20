<?php

namespace Tests\Feature\Admin;

use App\DataTables\MonthTables;
use App\Models\DataTable;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_needs_a_short_range(): void
    {
        $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2027-01-31')->assertStatus(422);
        $this->getJson('/api/v1/admin/calendar?from=2026-10-31&to=2026-10-01')->assertStatus(422);
    }

    public function test_a_moderator_sees_the_calendar(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'moderator')->create());

        $this->getJson('/api/v1/admin/calendar?from=2026-10-01&to=2026-10-31')->assertOk();
    }

    /** @return array<string, mixed> the response's data */
    private function addFromCalendar(array $table, string $item, string $date, int $amount): array
    {
        [$itemKey, $dateKey, $amountKey] = array_column($table['columns'], 'key');

        return $this->postJson('/api/v1/admin/calendar/rows', ['table' => $table['id'], 'values' => [
            $itemKey => $item, $dateKey => $date, $amountKey => $amount,
        ]])->assertCreated()->json('data');
    }

    public function test_an_order_of_a_month_without_a_table_gets_a_new_table_like_the_chosen_one(): void
    {
        $october = $this->table('Հոկտեմբեր 2026');
        $this->addRow($october, 'Բազմոց', '2026-10-05 14:30', 9900);

        $added = $this->addFromCalendar($october, 'Ներքնակ', '2026-09-20 10:00', 5000);

        $this->assertTrue($added['created']);
        $this->assertSame('Սեպտեմբեր 2026', $added['table']['name']);
        $this->assertNotSame($october['id'], $added['table']['id']);
        $this->assertSame(array_column($october['columns'], 'key'), array_column($added['table']['columns'], 'key'));
        $this->assertSame('2026-09-20 10:00', $added['row']['values'][$october['columns'][1]['key']]);

        // The October table keeps only October.
        $this->assertDatabaseCount('data_table_rows', 2);
        $this->assertSame(1, $this->rowsOf($october['id']));

        // The next September order goes to the new table, not to a third one.
        $again = $this->addFromCalendar($october, 'Աթոռ', '2026-09-21 12:00', 500);
        $this->assertFalse($again['created']);
        $this->assertSame($added['table']['id'], $again['table']['id']);
        $this->assertSame(2, $this->tableCount());

        $calendar = $this->getJson('/api/v1/admin/calendar?from=2026-09-01&to=2026-10-31')->json('data');
        $this->assertSame(['2026-09', '2026-10'], [$calendar['periods'][$added['table']['id']]['key'], $calendar['periods'][$october['id']]['key']]);
        // The new table is kept for its month from now on; the old one's month is still read from its name.
        $this->assertSame(['month', null], [$added['table']['period'], $october['period']]);
        $this->assertSame(['year' => 2026, 'quarter' => 3, 'month' => 9, 'week' => 39, 'weekYear' => 2026, 'day' => '2026-09-21'], $again['period']);
        $this->assertCount(3, $calendar['rows']);
    }

    public function test_an_order_of_a_month_with_a_table_goes_to_it(): void
    {
        $october = $this->table('october');
        $september = $this->table('september');
        $this->addRow($september, 'Բազմոց', '2026-09-03 11:00', 9900);

        $added = $this->addFromCalendar($october, 'Ներքնակ', '2026-09-20 10:00', 5000);

        $this->assertFalse($added['created']);
        $this->assertSame($september['id'], $added['table']['id']);
        $this->assertSame(2, $this->rowsOf($september['id']));
        $this->assertSame(0, $this->rowsOf($october['id']));

        // Its own month stays in the chosen table.
        $this->assertSame($october['id'], $this->addFromCalendar($october, 'Աթոռ', '2026-10-02 10:00', 500)['table']['id']);
    }

    public function test_a_table_of_a_month_is_known_by_its_rows_too(): void
    {
        $orders = $this->table('Պատվերներ');
        foreach (range(1, 9) as $day) {
            $this->addRow($orders, 'Բազմոց', "2026-10-0{$day} 10:00", 1000);
        }
        // One stray September row (added before this fix) doesn't make it a two-month table.
        $this->addRow($orders, 'Ներքնակ', '2026-09-30 10:00', 1000);

        $added = $this->addFromCalendar($orders, 'Աթոռ', '2026-08-15 10:00', 500);

        $this->assertTrue($added['created']);
        $this->assertSame('Օգոստոս 2026', $added['table']['name']);
    }

    public function test_a_table_of_any_month_keeps_every_order(): void
    {
        $all = $this->table('Բոլոր պատվերները');
        $this->addRow($all, 'Բազմոց', '2026-08-05 10:00', 1000);
        $this->addRow($all, 'Բազմոց', '2026-10-05 10:00', 1000);
        $empty = $this->table('Տնային');

        $this->assertSame($all['id'], $this->addFromCalendar($all, 'Ներքնակ', '2026-09-20 10:00', 5000)['table']['id']);
        $this->assertSame($empty['id'], $this->addFromCalendar($empty, 'Ներքնակ', '2026-09-20 10:00', 5000)['table']['id']);
        $this->assertSame(2, $this->tableCount());
    }

    public function test_a_bad_order_creates_no_table_and_a_moderator_may_add(): void
    {
        $october = $this->table('Октябрь');
        $this->addRow($october, 'Բազմոց', '2026-10-05 14:30', 9900);
        [$itemKey, $dateKey, $amountKey] = array_column($october['columns'], 'key');

        $this->postJson('/api/v1/admin/calendar/rows', ['table' => $october['id'], 'values' => [
            $itemKey => 'Ներքնակ', $dateKey => 'երեկ', $amountKey => 5000,
        ]])->assertUnprocessable()->assertJsonValidationErrors("values.{$dateKey}");
        $this->assertSame(1, $this->tableCount());

        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'moderator')->create());
        $added = $this->addFromCalendar($october, 'Ներքնակ', '2026-11-02 10:00', 5000);
        $this->assertSame('Ноябрь', $added['table']['name']);

        // Another business's table can't be chosen.
        $other = $this->createTenant('other-salon');
        $foreign = $this->asTenant($other, fn () => DataTable::create(['section' => 'orders', 'name' => 'X', 'columns' => []]));
        $this->postJson('/api/v1/admin/calendar/rows', ['table' => $foreign->id, 'values' => []])->assertNotFound();
    }

    public function test_month_names(): void
    {
        $this->assertSame([10, 2026, 'hy'], $this->named('Հոկտեմբեր 2026'));
        $this->assertSame([10, null, 'hy'], $this->named('Պատվերներ հոկտեմբերի'));
        $this->assertSame([9, null, 'ru'], $this->named('Заказы сентября'));
        $this->assertSame([9, 2026, 'en'], $this->named('Sept 2026'));
        $this->assertSame([9, 2026, null], $this->named('orders 09.2026'));
        $this->assertSame([9, 2026, null], $this->named('2026-09'));
        $this->assertNull(MonthTables::fromName('Սեպտեմբեր–Հոկտեմբեր'));
        $this->assertNull(MonthTables::fromName('Բոլոր պատվերները 2026'));
        $this->assertNull(MonthTables::fromName('Marketing'));
    }

    private function rowsOf(int $table): int
    {
        return $this->asTenant($this->salon, fn () => DataTable::findOrFail($table)->rows()->count());
    }

    private function tableCount(): int
    {
        return $this->asTenant($this->salon, fn () => DataTable::count());
    }

    private function named(string $name): ?array
    {
        $named = MonthTables::fromName($name);

        return $named === null ? null : [$named['month'], $named['year'], $named['lang']];
    }
}

<?php

namespace Tests\Feature\Admin;

use App\DataTables\Period;
use App\Enums\TablePeriod;
use App\Models\DataTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Tables kept per day, week, month, quarter or year: every row sits in a
 * year, quarter, month, ISO week and day, and an order added from the
 * calendar goes to the table of its date's period.
 */
class TablePeriodsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = $this->createTenant('test-salon');
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'owner')->create());
    }

    /** @return array<string, mixed> */
    private function table(string $name, ?string $period = null, ?string $periodDate = null): array
    {
        return $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => $name,
            ...($period !== null ? ['period' => $period, 'periodDate' => $periodDate] : []),
            'columns' => [
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Ամսաթիվ', 'type' => 'datetime', 'role' => 'date'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
            ],
        ])->assertCreated()->json('data');
    }

    /** @return array<string, mixed> the response's data */
    private function addFromCalendar(array $table, string $date): array
    {
        [$item, $at, $amount] = array_column($table['columns'], 'key');

        return $this->postJson('/api/v1/admin/calendar/rows', ['table' => $table['id'], 'values' => [
            $item => 'Բազմոց', $at => $date, $amount => 9900,
        ]])->assertCreated()->json('data');
    }

    public function test_a_date_sits_in_a_year_quarter_month_week_and_day(): void
    {
        $this->assertSame(
            ['year' => 2026, 'quarter' => 4, 'month' => 11, 'week' => 47, 'weekYear' => 2026, 'day' => '2026-11-18'],
            Period::tags('2026-11-18 14:30:00'),
        );
        // ISO weeks: 1 January 2027 is a Friday, still in the last week of 2026.
        $this->assertSame(['week' => 53, 'weekYear' => 2026], array_intersect_key(Period::tags('2027-01-01'), ['week' => 0, 'weekYear' => 0]));

        $labels = collect(TablePeriod::calendarValues())
            ->mapWithKeys(fn (string $kind) => [$kind => Period::of(TablePeriod::from($kind), '2026-11-18')])
            ->map(fn (Period $p) => [$p->key(), $p->label(), $p->start->toDateString(), $p->end()->toDateString()])
            ->all();

        $this->assertSame([
            'day' => ['2026-11-18', '18 նոյեմբերի 2026', '2026-11-18', '2026-11-18'],
            'week' => ['2026-W47', '47-րդ շաբաթ 2026 (16.11–22.11)', '2026-11-16', '2026-11-22'],
            'month' => ['2026-11', 'Նոյեմբեր 2026', '2026-11-01', '2026-11-30'],
            'quarter' => ['2026-Q4', '4-րդ եռամսյակ 2026', '2026-10-01', '2026-12-31'],
            'year' => ['2026', '2026', '2026-01-01', '2026-12-31'],
        ], $labels);
    }

    public function test_a_table_is_kept_for_a_chosen_period(): void
    {
        $q4 = $this->table('Պատվերներ · 4-րդ եռամսյակ 2026', 'quarter', '2026-11-18');

        $this->assertSame('quarter', $q4['period']);
        $this->assertSame(['kind' => 'quarter', 'key' => '2026-Q4', 'label' => '4-րդ եռամսյակ 2026', 'start' => '2026-10-01', 'end' => '2026-12-31', 'inferred' => false], $q4['periodInfo']);

        // Without a day: the current period.
        $this->travelTo('2026-10-18 12:00');
        $this->assertSame('2026-W42', $this->table('Այս շաբաթ', 'week')['periodInfo']['key']);

        // Custom: any dates, even when the name has a month.
        $custom = $this->table('Հոկտեմբեր 2026', 'custom', '2026-10-01');
        $this->assertSame(['custom', null], [$custom['period'], $custom['periodInfo']]);

        $this->postJson('/api/v1/admin/data-tables', ['section' => 'orders', 'name' => 'X', 'period' => 'decade', 'columns' => [['label' => 'A', 'type' => 'text']]])
            ->assertUnprocessable()->assertJsonValidationErrors('period');
    }

    public function test_an_order_goes_to_the_table_of_its_dates_period(): void
    {
        $q4 = $this->table('Պատվերներ · 4-րդ եռամսյակ 2026', 'quarter', '2026-10-01');

        // 18 November is in the 4th quarter: the chosen table keeps it.
        $november = $this->addFromCalendar($q4, '2026-11-18 10:00');
        $this->assertSame([$q4['id'], false], [$november['table']['id'], $november['created']]);
        $this->assertSame(['year' => 2026, 'quarter' => 4, 'month' => 11, 'week' => 47, 'weekYear' => 2026, 'day' => '2026-11-18'], $november['period']);

        // January 2027 is another quarter: a new table of it, named like the chosen one.
        $january = $this->addFromCalendar($q4, '2027-01-10 10:00');
        $this->assertTrue($january['created']);
        $this->assertSame('Պատվերներ · 1-ին եռամսյակ 2027', $january['table']['name']);
        $this->assertSame(['quarter', '2027-Q1'], [$january['table']['period'], $january['table']['periodInfo']['key']]);

        // The next one of that quarter goes to that table.
        $march = $this->addFromCalendar($q4, '2027-03-31 23:00');
        $this->assertSame([$january['table']['id'], false], [$march['table']['id'], $march['created']]);

        $this->assertSame(2, DataTable::withoutTenancy()->count());
    }

    public function test_daily_weekly_and_yearly_tables(): void
    {
        $week = $this->table('47-րդ շաբաթ 2026 (16.11–22.11)', 'week', '2026-11-18');
        $this->assertSame('48-րդ շաբաթ 2026 (23.11–29.11)', $this->addFromCalendar($week, '2026-11-25 09:00')['table']['name']);
        $this->assertSame($week['id'], $this->addFromCalendar($week, '2026-11-22 21:00')['table']['id']);

        $day = $this->table('Այսօրվա պատվերներ', 'day', '2026-11-18');
        $next = $this->addFromCalendar($day, '2026-11-19 09:00')['table'];
        $this->assertSame(['19 նոյեմբերի 2026', 'day', '2026-11-19'], [$next['name'], $next['period'], $next['periodInfo']['start']]);

        $year = $this->table('2026', 'year', '2026-03-01');
        $this->assertSame($year['id'], $this->addFromCalendar($year, '2026-12-31 23:59')['table']['id']);
        $this->assertSame('2027', $this->addFromCalendar($year, '2027-01-01 00:00')['table']['name']);
    }

    public function test_a_table_made_before_periods_is_still_read_by_its_month(): void
    {
        $october = $this->table('Հոկտեմբեր 2026');

        $this->assertNull($october['period']);
        $this->assertSame(['month', '2026-10', true], [$october['periodInfo']['kind'], $october['periodInfo']['key'], $october['periodInfo']['inferred']]);

        // Choosing the period later keeps the month it already had.
        $saved = $this->patchJson("/api/v1/admin/data-tables/{$october['id']}", ['period' => 'month'])->assertOk()->json('data');
        $this->assertSame(['month', '2026-10', false], [$saved['period'], $saved['periodInfo']['key'], $saved['periodInfo']['inferred']]);

        // And changing it to a quarter keeps the quarter of that month.
        $saved = $this->patchJson("/api/v1/admin/data-tables/{$october['id']}", ['period' => 'quarter'])->assertOk()->json('data');
        $this->assertSame('2026-Q4', $saved['periodInfo']['key']);
    }

    public function test_rows_are_filtered_by_period_and_carry_their_tags(): void
    {
        $all = $this->table('Բոլորը', 'custom');
        foreach (['2026-09-30 10:00', '2026-10-01 00:00', '2026-11-18 14:30', '2026-12-31 23:59', '2027-01-01 00:00'] as $date) {
            $this->addFromCalendar($all, $date);
        }

        $q4 = $this->getJson("/api/v1/admin/data-tables/{$all['id']}/rows?period=quarter&date=2026-11-18")->assertOk();
        $this->assertSame(3, $q4->json('meta.total'));
        $this->assertSame(['2026-12-31', '2026-11-18', '2026-10-01'], array_column(array_column($q4->json('data'), 'period'), 'day'));

        $this->assertSame(1, $this->getJson("/api/v1/admin/data-tables/{$all['id']}/rows?period=week&date=2026-11-16")->json('meta.total'));
        $this->assertSame(5, $this->getJson("/api/v1/admin/data-tables/{$all['id']}/rows")->json('meta.total'));

        $this->getJson("/api/v1/admin/data-tables/{$all['id']}/rows?period=quarter")->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Assistant\BusinessTools;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The expenses' own statistics: a day, week, month, quarter or year of any
 * date, by category, payee, payment method and who spent, apart from sales.
 */
class ExpenseStatisticsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private array $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 12:00');
        $this->shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'owner')->create());

        // Headers only: the roles come from their words.
        $this->table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'expenses',
            'name' => 'Ծախսեր',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date'],
                ['label' => 'Ծախսի տեսակ', 'type' => 'text'],
                ['label' => 'Ինչի համար', 'type' => 'text'],
                ['label' => 'Ում է վճարվել', 'type' => 'text'],
                ['label' => 'Վճարման եղանակ', 'type' => 'text'],
                ['label' => 'Ով է ծախսել', 'type' => 'text'],
                ['label' => 'Գումար', 'type' => 'money'],
            ],
        ])->assertCreated()->json('data');

        $this->expense('2026-10-02', 'Նյութեր', 'Քիմիա', 'Ակվա ՍՊԸ', 'Քարտ', 'Արամ', 30000);
        $this->expense('2026-10-02', 'Գովազդ', 'Instagram', 'Meta', 'Քարտ', 'Անի', 20000);
        $this->expense('2026-10-15', 'Նյութեր', 'Լաթեր', 'Ակվա ՍՊԸ', 'Կանխիկ', 'Արամ', 5000);
        $this->expense('2026-09-10', 'Նյութեր', 'Քիմիա', 'Ակվա ՍՊԸ', 'Քարտ', 'Արամ', 25000);

        $rent = collect($this->getJson('/api/v1/admin/expense-categories')->json('data'))->firstWhere('key', 'rent')['id'];
        $this->postJson('/api/v1/admin/recurring-expenses', ['name' => 'Տարածքի վարձ', 'categoryId' => $rent, 'amount' => 100000, 'frequency' => 'month', 'startsOn' => '2026-08-05'])->assertCreated();
    }

    private function expense(string $date, string $category, string $what, string $payee, string $payment, string $who, int $amount): void
    {
        $values = array_combine(array_column($this->table['columns'], 'key'), [$date, $category, $what, $payee, $payment, $who, $amount]);
        $this->postJson("/api/v1/admin/data-tables/{$this->table['id']}/rows", ['values' => $values])->assertCreated();
    }

    public function test_the_roles_of_expense_columns_are_known_by_their_names(): void
    {
        $this->assertSame(['date', 'category', 'item', 'supplier', 'payment', 'performer', 'amount'], array_column($this->table['columns'], 'role'));
    }

    public function test_a_month_of_expenses_by_category_payee_payment_and_who_spent(): void
    {
        $report = $this->getJson('/api/v1/admin/expenses/statistics?by=month&date=2026-10-07')->assertOk()->json('data');

        $this->assertSame('2026-10', $report['period']['key']);
        $this->assertSame(155000, $report['totals']['total']);
        $this->assertSame(100000, $report['totals']['recurring']);
        $this->assertSame(55000, $report['totals']['oneOff']);
        $this->assertSame(125000, $report['totals']['previous']);
        $this->assertSame(4, $report['count']);

        $this->assertSame([12, 31], [count($report['series']), count($report['inside'])]);
        $this->assertSame(150000, collect($report['inside'])->firstWhere('key', '2026-10-05')['total'] + collect($report['inside'])->firstWhere('key', '2026-10-02')['total']);

        $breakdown = fn (string $role) => collect(collect($report['breakdowns'])->firstWhere('role', $role)['values'])->pluck('amount', 'value')->all();
        $this->assertSame(['Ակվա ՍՊԸ' => 35000, 'Meta' => 20000], $breakdown('supplier'));
        $this->assertSame(['Քարտ' => 50000, 'Կանխիկ' => 5000], $breakdown('payment'));
        $this->assertSame(['Արամ' => 35000, 'Անի' => 20000], $breakdown('performer'));
        // A recurring expense is "what for" by its name.
        $this->assertSame(100000, $breakdown('item')['Տարածքի վարձ']);

        $this->assertSame([100000, 30000], array_slice(array_column($report['largest'], 'amount'), 0, 2));
        $this->assertSame('Ակվա ՍՊԸ', $report['largest'][1]['supplier']);
    }

    public function test_a_single_day_and_a_year(): void
    {
        $day = $this->getJson('/api/v1/admin/expenses/statistics?by=day&date=2026-10-02')->assertOk()->json('data');
        $this->assertSame(50000, $day['totals']['total']);
        $this->assertSame(['Քիմիա', 'Instagram'], array_column($day['rows'], 'item'));
        $this->assertSame([], $day['inside']);

        $year = $this->getJson('/api/v1/admin/expenses/statistics?by=year&date=2026-03-01')->assertOk()->json('data');
        $this->assertSame('2026', $year['period']['key']);
        // August to October rent (due on the 5th, up to today) and the rows.
        $this->assertSame(300000 + 80000, $year['totals']['total']);
        $this->assertCount(12, $year['inside']);
    }

    public function test_only_the_owner_sees_expenses_and_the_ai_reads_them(): void
    {
        $this->asTenant($this->shop, function () {
            $report = app(BusinessTools::class)->run('expenses_report', ['by' => 'month', 'date' => '2026-10-19']);
            $this->assertSame(155000, $report['totals']['total']);
            $this->assertNotEmpty($report['expenses']);
        });

        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'admin')->create());
        $this->getJson('/api/v1/admin/expenses/statistics')->assertForbidden();
    }
}

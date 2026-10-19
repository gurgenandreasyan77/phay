<?php

namespace Tests\Feature\Admin;

use App\Enums\ColumnRole;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * What a business spends: expenses tables (by hand or from a file), kinds
 * of expenses counted in the profit or not, expenses that repeat, and the
 * net profit per day, week, month, quarter and year.
 */
class ExpensesTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    /** @var array<string, mixed>|null */
    private ?array $salesTable = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-19 12:00');
        $this->shop = $this->createTenant('test-shop');
        Feature::for($this->shop)->activate('sales');
        Feature::for($this->shop)->deactivate('orders');
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'owner')->create());
    }

    /** @return array<string, mixed> */
    private function expensesTable(): array
    {
        return $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'expenses',
            'name' => 'Ծախսեր 2026',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Ծախսի տեսակ', 'type' => 'text', 'role' => 'category'],
                ['label' => 'Ինչի համար', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
            ],
        ])->assertCreated()->json('data');
    }

    private function expense(array $table, string $date, string $category, int|float $amount, string $what = ''): void
    {
        [$at, $kind, $item, $sum] = array_column($table['columns'], 'key');
        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$at => $date, $kind => $category, $item => $what, $sum => $amount]])->assertCreated();
    }

    /** A sales table with a unit cost: revenue and cost of goods. */
    private function sale(string $date, int $price, int $cost, int $quantity = 1): void
    {
        $table = $this->salesTable ??= $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Վաճառքներ',
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Քանակ', 'type' => 'number', 'role' => 'quantity'],
                ['label' => 'Գին', 'type' => 'money', 'role' => 'unit_price'],
                ['label' => 'Ինքնարժեք', 'type' => 'money', 'role' => 'cost'],
            ],
        ])->assertCreated()->json('data');
        [$item, $at, $qty, $unitPrice, $unitCost] = array_column($table['columns'], 'key');
        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$item => 'Մոմ', $at => $date, $qty => $quantity, $unitPrice => $price, $unitCost => $cost]])->assertCreated();
    }

    public function test_a_business_starts_with_ready_made_categories_and_adds_its_own(): void
    {
        $categories = $this->getJson('/api/v1/admin/expense-categories')->assertOk()->json('data');

        $this->assertSame('Հարկեր', $categories[0]['name']);
        $this->assertTrue(collect($categories)->firstWhere('key', 'taxes')['inProfit']);
        $this->assertFalse(collect($categories)->firstWhere('key', 'loan')['inProfit']);
        $this->assertFalse(collect($categories)->firstWhere('key', 'equipment')['inProfit']);
        $this->assertCount(13, $categories);
        // Asking again doesn't make them twice.
        $this->assertCount(13, $this->getJson('/api/v1/admin/expense-categories')->json('data'));

        $own = $this->postJson('/api/v1/admin/expense-categories', ['name' => 'Ֆրանչայզի վճար', 'inProfit' => true])->assertCreated()->json('data');
        $this->postJson('/api/v1/admin/expense-categories', ['name' => ' հարկեր '])->assertUnprocessable()->assertJsonValidationErrors('name');

        $taxes = collect($categories)->firstWhere('key', 'taxes');
        $this->deleteJson("/api/v1/admin/expense-categories/{$taxes['id']}")->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/expense-categories/{$own['id']}")->assertNoContent();

        // Another business has its own list.
        $other = $this->createTenant('other');
        $this->asTenant($other, fn () => $this->assertSame(0, ExpenseCategory::query()->count()));
    }

    public function test_net_profit_is_revenue_minus_cost_of_goods_minus_expenses_counted_in_profit(): void
    {
        $table = $this->expensesTable();
        $this->sale('2026-10-05', 3000, 1000, 10);   // 30 000 revenue, 10 000 cost of goods
        $this->sale('2026-09-10', 2000, 500, 5);     // September: 10 000 revenue, 2 500 cost
        $this->expense($table, '2026-10-01', 'Վարձակալություն', 8000, 'Խանութի վարձ');
        // From a Russian file: matched to the ready-made "taxes".
        $this->expense($table, '2026-10-15', 'Налоги', 1500);
        // Equipment is kept out of the profit, counted in the cash left.
        $this->expense($table, '2026-10-12', 'Սարքավորումներ', 5000, 'Սառնարան');
        // A kind that isn't in the list: counted in the profit under its own name.
        $this->expense($table, '2026-10-18', 'Ծաղիկներ', 700);

        $month = $this->getJson('/api/v1/admin/analytics/finance?by=month')->assertOk()->json('data');

        $this->assertSame('month', $month['by']);
        $this->assertCount(12, $month['periods']);
        $october = $month['current'];
        $this->assertSame('2026-10', $october['key']);
        $this->assertSame(30000, $october['revenue']);
        $this->assertSame(10000, $october['cogs']);
        $this->assertSame(20000, $october['grossProfit']);
        $this->assertSame(10200, $october['expenses']);
        $this->assertSame(5000, $october['excluded']);
        $this->assertSame(9800, $october['netProfit']);
        $this->assertSame(4800, $october['cashLeft']);
        $this->assertSame(32.7, $october['margin']);
        $this->assertSame('2026-09', $october['previous']['key']);
        $this->assertSame(7500, $october['previous']['netProfit']);

        $byName = collect($october['categories'])->keyBy('name');
        $this->assertSame(8000, $byName['Վարձակալություն']['amount']);
        $this->assertSame(1500, $byName['Հարկեր']['amount']);
        $this->assertFalse($byName['Սարքավորումներ']['inProfit']);
        $this->assertNull($byName['Ծաղիկներ']['id']);
        $this->assertStringContainsString('«Ծաղիկներ»', implode(' ', $month['notes']));

        // The same by quarter, day and year.
        $quarter = $this->getJson('/api/v1/admin/analytics/finance?by=quarter')->json('data')['current'];
        $this->assertSame('2026-Q4', $quarter['key']);
        $this->assertSame(9800, $quarter['netProfit']);
        $q3 = $this->getJson('/api/v1/admin/analytics/finance?by=quarter&date=2026-08-01')->json('data')['current'];
        $this->assertSame(7500, $q3['netProfit']);
        $day = collect($this->getJson('/api/v1/admin/analytics/finance?by=day')->json('data')['periods'])->keyBy('key');
        $this->assertSame(-8000, $day['2026-10-01']['netProfit']);
        $this->assertSame(20000, $day['2026-10-05']['netProfit']);
        $year = $this->getJson('/api/v1/admin/analytics/finance?by=year')->json('data')['current'];
        $this->assertSame(17300, $year['netProfit']);
    }

    public function test_turning_a_category_out_of_the_profit_and_renaming_it(): void
    {
        $table = $this->expensesTable();
        $this->expense($table, '2026-10-03', 'Գովազդ և մարքեթինգ', 4000);
        $marketing = collect($this->getJson('/api/v1/admin/expense-categories')->json('data'))->firstWhere('key', 'marketing');

        $this->patchJson("/api/v1/admin/expense-categories/{$marketing['id']}", ['inProfit' => false, 'name' => 'Instagram գովազդ'])->assertOk();

        $october = $this->getJson('/api/v1/admin/analytics/finance')->json('data')['current'];
        $this->assertSame(0, $october['expenses']);
        $this->assertSame(4000, $october['excluded']);
        // The row now names the category the new way.
        $row = $this->getJson("/api/v1/admin/data-tables/{$table['id']}/rows")->json('data.0');
        $this->assertSame('Instagram գովազդ', $row['values'][$table['columns'][1]['key']]);
    }

    public function test_recurring_expenses_count_on_each_due_date_up_to_today(): void
    {
        $categories = collect($this->getJson('/api/v1/admin/expense-categories')->json('data'))->keyBy('key');
        $rentId = $categories['rent']['id'];
        $taxes = $categories['taxes'];

        $this->postJson('/api/v1/admin/recurring-expenses', ['name' => 'Տարածքի վարձ', 'categoryId' => $rentId, 'amount' => 150000, 'frequency' => 'month', 'startsOn' => '2026-08-25'])
            ->assertCreated()
            ->assertJsonPath('data.monthly', 150000);
        $this->postJson('/api/v1/admin/recurring-expenses', ['name' => 'Շրջանառության հարկ', 'categoryId' => $taxes['id'], 'amount' => 60000, 'frequency' => 'quarter', 'startsOn' => '2026-01-20', 'endsOn' => '2026-12-31'])
            ->assertCreated()
            ->assertJsonPath('data.monthly', 20000);
        $this->postJson('/api/v1/admin/recurring-expenses', ['name' => 'Սխալ', 'categoryId' => $taxes['id'], 'amount' => 10, 'frequency' => 'day', 'startsOn' => '2026-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('frequency');

        $months = collect($this->getJson('/api/v1/admin/analytics/finance?by=month')->json('data')['periods'])->keyBy('key');
        $this->assertSame(150000, $months['2026-08']['expenses']);
        $this->assertSame(150000, $months['2026-09']['expenses']);
        // Rent of 25 October isn't due yet on the 19th; the quarter's tax of 20 October neither.
        $this->assertSame(0, $months['2026-10']['expenses']);
        $this->assertSame(60000, $months['2026-07']['expenses']);
        $this->assertSame(60000, $months['2026-04']['expenses']);
        $this->assertSame(-150000, $months['2026-09']['netProfit']);

        $year = $this->getJson('/api/v1/admin/analytics/finance?by=year')->json('data')['current'];
        $this->assertSame(300000 + 180000, $year['expenses']);
        $this->assertSame(180000, collect($year['categories'])->firstWhere('key', 'taxes')['recurring']);
    }

    public function test_expenses_are_the_owners_and_stay_out_of_sales_analytics(): void
    {
        $table = $this->expensesTable();
        $this->expense($table, '2026-10-01', 'Հարկեր', 1000);

        $this->assertContains('expenses', $this->getJson('/api/v1/admin/me')->json('data.permissions'));
        // Sales analytics know nothing of expenses.
        $sections = $this->getJson('/api/v1/admin/analytics/statistics')->assertOk()->json('data');
        $this->assertNotContains('expenses', array_column($sections, 'section'));

        foreach (['admin', 'moderator'] as $role) {
            Sanctum::actingAs(User::factory()->forTenant($this->shop, $role)->create());
            $this->getJson('/api/v1/admin/expense-categories')->assertForbidden();
            $this->getJson('/api/v1/admin/recurring-expenses')->assertForbidden();
            $this->getJson("/api/v1/admin/data-tables/{$table['id']}/rows")->assertNotFound();
            $this->assertSame([], $this->getJson('/api/v1/admin/data-tables?section=expenses')->assertOk()->json('data'));
        }
    }

    public function test_expenses_are_imported_from_a_file_like_any_table(): void
    {
        Storage::fake('local');
        $table = $this->expensesTable();
        $file = UploadedFile::fake()->createWithContent('expenses.csv', "Дата;Статья расхода;За что;Сумма\n01.10.2026;Аренда;Магазин;80 000\n05.10.2026;Зарплата;Ани;120000\n07.10.2026;Кредит;Банк;50000\n");

        $preview = $this->post('/api/v1/admin/data-imports', ['file' => $file, 'data_table_id' => $table['id']], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $keys = array_column($table['columns'], 'key');
        $this->assertSame([$keys[0] => 0, $keys[1] => 1, $keys[2] => 2, $keys[3] => 3], $preview['mapping']);

        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/run", ['data_table_id' => $table['id'], 'mapping' => $preview['mapping']])
            ->assertOk()->assertJsonPath('data.importedRows', 3);

        $october = $this->getJson('/api/v1/admin/analytics/finance')->json('data')['current'];
        $byKey = collect($october['categories'])->keyBy('key');
        $this->assertSame(80000, $byKey['rent']['amount']);
        $this->assertSame(120000, $byKey['salaries']['amount']);
        // A loan repayment is out of the profit.
        $this->assertSame(50000, $october['excluded']);
        $this->assertSame(-200000, $october['netProfit']);
    }

    public function test_a_file_header_for_the_kind_of_expense_gets_the_category_role(): void
    {
        $this->assertSame(ColumnRole::Category, ColumnRole::guess('Статья расхода'));
        $this->assertSame(ColumnRole::Category, ColumnRole::guess('Ծախսի տեսակ'));
        $this->assertSame(ColumnRole::Category, ColumnRole::guess('Category'));
    }
}

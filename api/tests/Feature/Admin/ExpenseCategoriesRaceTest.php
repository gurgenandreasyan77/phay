<?php

namespace Tests\Feature\Admin;

use App\Expenses\ExpenseCategories;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The ready-made expense categories are made the first time they're needed.
 * Several requests of one page (home, statistics, the assistant) can all
 * find them missing at once: the one that writes second must not fail with
 * the unique (tenant, key), it keeps the row the first one made.
 */
class ExpenseCategoriesRaceTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->createTenant('test-shop');
        app(TenantContext::class)->set($this->shop);
    }

    /** Another request writes the row between this one's read and its insert. */
    private function anotherRequestMakesFirst(string $key, ?string $name = null): void
    {
        $done = false;
        ExpenseCategory::creating(function (ExpenseCategory $category) use ($key, $name, &$done) {
            if ($done || $category->key !== $key) {
                return;
            }
            $done = true;
            DB::table('expense_categories')->insert([
                'tenant_id' => $this->shop->id,
                'key' => $key,
                'name' => $name ?? $category->name,
                'in_profit' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_a_ready_made_category_made_meanwhile_by_another_request_is_kept(): void
    {
        $this->anotherRequestMakesFirst('taxes', 'Հարկեր');

        $categories = ExpenseCategories::all();

        $this->assertCount(count(ExpenseCategories::DEFAULTS), $categories);
        $this->assertSame(1, ExpenseCategory::query()->where('key', 'taxes')->count());
        $this->assertEqualsCanonicalizing(array_keys(ExpenseCategories::DEFAULTS), $categories->pluck('key')->all());
    }

    public function test_it_works_inside_a_transaction_too(): void
    {
        $this->anotherRequestMakesFirst('rent');

        $count = DB::transaction(function () {
            ExpenseCategories::all();

            // The transaction is still usable after the skipped insert.
            return ExpenseCategory::query()->count();
        });

        $this->assertSame(count(ExpenseCategories::DEFAULTS), $count);
    }

    public function test_an_own_category_of_the_same_name_is_left_when_another_request_made_the_ready_made_one(): void
    {
        $own = ExpenseCategory::create(['name' => 'Հարկեր', 'in_profit' => true, 'sort_order' => 5]);

        // Right after this request reads the categories, another one makes the ready-made taxes.
        $done = false;
        ExpenseCategory::retrieved(function () use (&$done) {
            if ($done) {
                return;
            }
            $done = true;
            DB::table('expense_categories')->insert([
                'tenant_id' => $this->shop->id, 'key' => 'taxes', 'name' => 'Հարկեր', 'in_profit' => true, 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $categories = ExpenseCategories::all();

        $this->assertSame(1, $categories->where('key', 'taxes')->count());
        $this->assertNull($own->fresh()->key);
        $this->assertSame(count(ExpenseCategories::DEFAULTS), $categories->whereNotNull('key')->count());
    }

    public function test_an_own_category_of_the_same_name_becomes_the_ready_made_one(): void
    {
        $own = ExpenseCategory::create(['name' => 'Հարկեր', 'in_profit' => true, 'sort_order' => 5]);

        ExpenseCategories::all();

        $this->assertSame('taxes', $own->fresh()->key);
        $this->assertSame(count(ExpenseCategories::DEFAULTS), ExpenseCategory::query()->count());
    }

    public function test_calling_it_again_adds_nothing(): void
    {
        ExpenseCategories::all();
        ExpenseCategories::all();

        $this->assertSame(count(ExpenseCategories::DEFAULTS), ExpenseCategory::query()->count());
    }
}

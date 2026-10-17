<?php

namespace Tests\Feature;

use App\Models\MarketTaskPage;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\MarketTaskPageSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketSeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_running_market_seeders_twice_does_not_create_duplicates(): void
    {
        (new ProductCategorySeeder)->run();
        (new ProductSeeder)->run();
        (new MarketTaskPageSeeder)->run();

        $categoryCount = ProductCategory::count();
        $productCount = Product::count();
        $taskPageCount = MarketTaskPage::count();

        $this->assertGreaterThan(0, $categoryCount);
        $this->assertGreaterThan(0, $productCount);
        $this->assertGreaterThan(0, $taskPageCount);

        (new ProductCategorySeeder)->run();
        (new ProductSeeder)->run();
        (new MarketTaskPageSeeder)->run();

        $this->assertSame($categoryCount, ProductCategory::count());
        $this->assertSame($productCount, Product::count());
        $this->assertSame($taskPageCount, MarketTaskPage::count());
    }

    public function test_all_seeded_products_are_drafts(): void
    {
        (new ProductCategorySeeder)->run();
        (new ProductSeeder)->run();

        $this->assertSame(0, Product::where('status', '!=', 'draft')->count());
    }
}

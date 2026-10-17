<?php

namespace Tests\Feature;

use App\Features\FeatureFlag;
use App\Models\MarketTaskPage;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MarketFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategory $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ProductCategory::create([
            'slug_hy' => 'hataki-mijocner',
            'slug_ru' => 'sredstva-dlya-pola',
            'slug_en' => 'floor-cleaners',
            'name' => ['hy' => 'Հատակի միջոցներ', 'ru' => 'Средства для пола', 'en' => 'Floor cleaners'],
        ]);

        $this->product = Product::create([
            'slug' => 'floor-cleaner-concentrate',
            'slug_hy' => 'floor-cleaner-concentrate',
            'slug_ru' => 'floor-cleaner-concentrate',
            'slug_en' => 'floor-cleaner-concentrate',
            'status' => 'published',
            'name' => ['hy' => 'Հատակի միջոց', 'ru' => 'Средство для полов', 'en' => 'Floor cleaner'],
            'price_amount' => 4500,
            'sort_order' => 1,
        ]);
        $this->product->categories()->attach($this->category);

        MarketTaskPage::create([
            'slug_hy' => 'mizi-hoti-heracum',
            'slug_ru' => 'udaleniye-zapaha-mochi',
            'slug_en' => 'urine-odor-removal',
            'status' => 'published',
            'title' => ['hy' => 'Վերնագիր', 'ru' => 'Заголовок', 'en' => 'Title'],
        ]);
    }

    public function test_when_active_market_endpoints_serve_data(): void
    {
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products/floor-cleaners/floor-cleaner-concentrate')->assertOk();
        $this->getJson('/api/v1/product-categories')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/product-categories/floor-cleaners')->assertOk();
        $this->getJson('/api/v1/task-pages')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/task-pages/urine-odor-removal')->assertOk();
    }

    public function test_when_disabled_market_endpoints_return_404(): void
    {
        Feature::deactivate(FeatureFlag::Market->value);

        $this->getJson('/api/v1/products')->assertNotFound();
        $this->getJson('/api/v1/products/floor-cleaners/floor-cleaner-concentrate')->assertNotFound();
        $this->getJson('/api/v1/product-categories')->assertNotFound();
        $this->getJson('/api/v1/product-categories/floor-cleaners')->assertNotFound();
        $this->getJson('/api/v1/task-pages')->assertNotFound();
        $this->getJson('/api/v1/task-pages/urine-odor-removal')->assertNotFound();
    }

    public function test_disabling_market_does_not_affect_unrelated_catalog_endpoints(): void
    {
        Feature::deactivate(FeatureFlag::Market->value);

        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/services')->assertOk();
    }

    public function test_disabling_does_not_delete_any_data(): void
    {
        Feature::deactivate(FeatureFlag::Market->value);

        $this->assertDatabaseHas('products', ['id' => $this->product->id]);
        $this->assertDatabaseHas('product_categories', ['id' => $this->category->id]);
    }

    public function test_re_enabling_restores_access_to_the_same_data(): void
    {
        Feature::deactivate(FeatureFlag::Market->value);
        $this->getJson('/api/v1/products')->assertNotFound();

        Feature::activate(FeatureFlag::Market->value);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'floor-cleaner-concentrate');
    }
}

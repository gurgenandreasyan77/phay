<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSlugRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(array $overrides = []): ProductCategory
    {
        return ProductCategory::create(array_merge([
            'slug_hy' => 'hataki-mijocner',
            'slug_ru' => 'sredstva-dlya-pola',
            'slug_en' => 'floor-cleaners',
            'name' => ['hy' => 'Հատակի միջոցներ', 'ru' => 'Средства для пола', 'en' => 'Floor cleaners'],
        ], $overrides));
    }

    private function makeItem(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'slug' => 'floor-cleaner-concentrate',
            'slug_hy' => 'floor-cleaner-concentrate',
            'slug_ru' => 'floor-cleaner-concentrate',
            'slug_en' => 'floor-cleaner-concentrate',
            'status' => 'published',
            'name' => ['hy' => 'Հատակի միջոց', 'ru' => 'Средство для полов', 'en' => 'Floor cleaner'],
            'short_description' => ['hy' => 'Կարճ նկարագիր', 'ru' => 'Краткое описание', 'en' => 'Short description'],
            'description' => [
                'hy' => 'Նկարագրություն',
                'ru' => 'Описание',
                'en' => 'Description',
            ],
            'alt' => ['hy' => 'Շիշ', 'ru' => 'Бутылка', 'en' => 'Bottle'],
            'image' => '/images/products/floor-cleaner-concentrate.webp',
            'price_amount' => 4500,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_listing_returns_only_published_items_ordered_by_sort_order(): void
    {
        $this->makeItem(['slug' => 'second', 'slug_hy' => 'second', 'slug_ru' => 'second', 'slug_en' => 'second', 'sort_order' => 2]);
        $this->makeItem(['slug' => 'first', 'slug_hy' => 'first', 'slug_ru' => 'first', 'slug_en' => 'first', 'sort_order' => 1]);
        $this->makeItem(['slug' => 'draft-item', 'slug_hy' => 'draft-item', 'slug_ru' => 'draft-item', 'slug_en' => 'draft-item', 'sort_order' => 0, 'status' => 'draft']);
        $this->makeItem(['slug' => 'archived-item', 'slug_hy' => 'archived-item', 'slug_ru' => 'archived-item', 'slug_en' => 'archived-item', 'sort_order' => 0, 'status' => 'archived']);

        $response = $this->getJson('/api/v1/products')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'first')
            ->assertJsonPath('data.1.slug', 'second');
    }

    public function test_listing_is_localized_via_lang_query_param(): void
    {
        $this->makeItem();

        $this->getJson('/api/v1/products?lang=ru')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Средство для полов');
    }

    public function test_listing_exposes_price(): void
    {
        $this->makeItem();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.priceAmount', 4500);
    }

    public function test_listing_can_filter_by_category(): void
    {
        $floors = $this->makeCategory();
        $windows = $this->makeCategory(['slug_hy' => 'apaki', 'slug_ru' => 'okna', 'slug_en' => 'windows']);

        $floorItem = $this->makeItem();
        $floorItem->categories()->attach($floors->id, ['is_primary' => true]);

        $windowItem = $this->makeItem(['slug' => 'glass', 'slug_hy' => 'glass', 'slug_ru' => 'glass', 'slug_en' => 'glass']);
        $windowItem->categories()->attach($windows->id, ['is_primary' => true]);

        $this->getJson('/api/v1/products?category=floor-cleaners')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'floor-cleaner-concentrate');
    }

    public function test_show_returns_product_by_locale_slug_within_its_category(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem();
        $item->categories()->attach($category->id, ['is_primary' => true]);

        $this->getJson('/api/v1/products/floor-cleaners/floor-cleaner-concentrate')
            ->assertOk()
            ->assertJsonPath('data.slug', 'floor-cleaner-concentrate')
            ->assertJsonPath('data.category.slug', 'floor-cleaners');
    }

    public function test_show_uses_locale_specific_slug_column(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem(['slug_ru' => 'sredstvo-dlya-pola-ru']);
        $item->categories()->attach($category->id, ['is_primary' => true]);

        $this->getJson('/api/v1/products/sredstva-dlya-pola/sredstvo-dlya-pola-ru?lang=ru')
            ->assertOk()
            ->assertJsonPath('data.slug', 'sredstvo-dlya-pola-ru');
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/products/floor-cleaners/does-not-exist')
            ->assertNotFound();
    }

    public function test_show_returns_redirect_payload_for_an_old_slug(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem();
        $item->categories()->attach($category->id, ['is_primary' => true]);

        ProductSlugRedirect::create([
            'locale' => 'en',
            'old_slug' => 'old-floor-cleaner-slug',
            'product_id' => $item->id,
        ]);

        $this->getJson('/api/v1/products/floor-cleaners/old-floor-cleaner-slug')
            ->assertOk()
            ->assertJsonPath('redirect.product', 'floor-cleaner-concentrate')
            ->assertJsonPath('redirect.category', 'floor-cleaners');
    }

    public function test_draft_product_is_not_reachable_via_show(): void
    {
        $category = $this->makeCategory();
        $item = $this->makeItem(['status' => 'draft']);
        $item->categories()->attach($category->id, ['is_primary' => true]);

        $this->getJson('/api/v1/products/floor-cleaners/floor-cleaner-concentrate')
            ->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_categories_ordered_by_sort_order(): void
    {
        $categoryB = ProductCategory::create([
            'slug_hy' => 'b', 'slug_ru' => 'b', 'slug_en' => 'b',
            'name' => ['hy' => 'Բ', 'ru' => 'Б', 'en' => 'B'],
            'sort_order' => 2,
        ]);
        $categoryA = ProductCategory::create([
            'slug_hy' => 'a', 'slug_ru' => 'a', 'slug_en' => 'a',
            'name' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A'],
            'sort_order' => 1,
        ]);

        foreach ([$categoryA, $categoryB] as $index => $category) {
            $product = Product::create([
                'slug' => "product-{$index}", 'slug_hy' => "product-{$index}", 'slug_ru' => "product-{$index}", 'slug_en' => "product-{$index}",
                'status' => 'published',
                'name' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A'],
                'description' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A'],
                'price_amount' => 1000,
            ]);
            $product->categories()->attach($category->id, ['is_primary' => true]);
        }

        $this->getJson('/api/v1/product-categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'a')
            ->assertJsonPath('data.1.slug', 'b');
    }

    public function test_index_excludes_categories_without_published_products(): void
    {
        $empty = ProductCategory::create([
            'slug_hy' => 'empty', 'slug_ru' => 'empty', 'slug_en' => 'empty',
            'name' => ['hy' => 'Դատարկ', 'ru' => 'Пустая', 'en' => 'Empty'],
            'sort_order' => 1,
        ]);
        $draftOnly = ProductCategory::create([
            'slug_hy' => 'draft-only', 'slug_ru' => 'draft-only', 'slug_en' => 'draft-only',
            'name' => ['hy' => 'Սևագիր', 'ru' => 'Черновик', 'en' => 'Draft only'],
            'sort_order' => 2,
        ]);
        $withProduct = ProductCategory::create([
            'slug_hy' => 'has-product', 'slug_ru' => 'has-product', 'slug_en' => 'has-product',
            'name' => ['hy' => 'Ունի ապրանք', 'ru' => 'С товаром', 'en' => 'Has product'],
            'sort_order' => 3,
        ]);

        $draft = Product::create([
            'slug' => 'draft-item', 'slug_hy' => 'draft-item', 'slug_ru' => 'draft-item', 'slug_en' => 'draft-item',
            'status' => 'draft',
            'name' => ['hy' => 'Բ', 'ru' => 'Б', 'en' => 'B'],
        ]);
        $draft->categories()->attach($draftOnly->id, ['is_primary' => true]);

        $published = Product::create([
            'slug' => 'published-item', 'slug_hy' => 'published-item', 'slug_ru' => 'published-item', 'slug_en' => 'published-item',
            'status' => 'published',
            'name' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A'],
            'description' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A'],
            'price_amount' => 1000,
        ]);
        $published->categories()->attach($withProduct->id, ['is_primary' => true]);

        $response = $this->getJson('/api/v1/product-categories')->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug');

        $this->assertFalse($slugs->contains('empty'));
        $this->assertFalse($slugs->contains('draft-only'));
        $this->assertTrue($slugs->contains('has-product'));
    }

    public function test_show_returns_category_with_only_published_products(): void
    {
        $category = ProductCategory::create([
            'slug_hy' => 'hataki-mijocner', 'slug_ru' => 'sredstva-dlya-pola', 'slug_en' => 'floor-cleaners',
            'name' => ['hy' => 'Հատակի միջոցներ', 'ru' => 'Средства для пола', 'en' => 'Floor cleaners'],
        ]);

        $published = Product::create([
            'slug' => 'published-item', 'slug_hy' => 'published-item', 'slug_ru' => 'published-item', 'slug_en' => 'published-item',
            'status' => 'published',
            'name' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A'],
            'description' => ['hy' => 'Ա', 'ru' => 'А', 'en' => 'A'],
            'price_amount' => 1000,
        ]);
        $draft = Product::create([
            'slug' => 'draft-item', 'slug_hy' => 'draft-item', 'slug_ru' => 'draft-item', 'slug_en' => 'draft-item',
            'status' => 'draft',
            'name' => ['hy' => 'Բ', 'ru' => 'Б', 'en' => 'B'],
        ]);
        $published->categories()->attach($category->id, ['is_primary' => true]);
        $draft->categories()->attach($category->id, ['is_primary' => true]);

        $response = $this->getJson('/api/v1/product-categories/floor-cleaners')->assertOk();

        $response->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.slug', 'published-item');
    }

    public function test_show_returns_404_for_unknown_category(): void
    {
        $this->getJson('/api/v1/product-categories/does-not-exist')->assertNotFound();
    }
}

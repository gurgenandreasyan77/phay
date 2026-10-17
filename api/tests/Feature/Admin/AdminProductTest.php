<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'slug_hy' => 'floor-cleaner-concentrate',
            'slug_ru' => 'floor-cleaner-concentrate',
            'slug_en' => 'floor-cleaner-concentrate',
            'status' => 'draft',
            'name' => ['hy' => 'Հատակի միջոց', 'ru' => 'Средство для полов', 'en' => 'Floor cleaner'],
            'description' => [
                'hy' => 'Նկարագրություն',
                'ru' => 'Описание',
                'en' => 'Description',
            ],
            'alt' => ['hy' => 'Շիշ', 'ru' => 'Бутылка', 'en' => 'Bottle'],
            'image' => '/images/products/floor-cleaner-concentrate.webp',
            'price_amount' => 4500,
        ], $overrides);
    }

    public function test_index_lists_all_statuses(): void
    {
        Product::create($this->payload());
        Product::create($this->payload(['slug_hy' => 'archived-item', 'slug_ru' => 'archived-item', 'slug_en' => 'archived-item', 'status' => 'archived']));

        $this->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_a_draft_item(): void
    {
        $response = $this->postJson('/api/v1/admin/products', $this->payload());

        $response->assertCreated()->assertJsonPath('data.slugHy', 'floor-cleaner-concentrate');
        $this->assertDatabaseHas('products', ['slug_hy' => 'floor-cleaner-concentrate', 'price_amount' => 4500, 'status' => 'draft']);
    }

    public function test_store_rejects_an_invalid_status(): void
    {
        $this->postJson('/api/v1/admin/products', $this->payload(['status' => 'not-a-status']))
            ->assertStatus(422);
    }

    public function test_store_rejects_missing_translations(): void
    {
        $payload = $this->payload();
        unset($payload['description']['ru']);

        $this->postJson('/api/v1/admin/products', $payload)->assertStatus(422);
    }

    public function test_store_rejects_publishing_without_short_description_in_all_locales(): void
    {
        $payload = $this->payload([
            'status' => 'published',
            'short_description' => ['hy' => 'Ամփոփում', 'ru' => 'Кратко', 'en' => ''],
        ]);

        $this->postJson('/api/v1/admin/products', $payload)->assertStatus(422);
    }

    public function test_store_allows_publishing_when_all_locale_fields_are_filled(): void
    {
        $payload = $this->payload([
            'status' => 'published',
            'short_description' => ['hy' => 'Ամփոփում', 'ru' => 'Кратко', 'en' => 'Summary'],
        ]);

        $this->postJson('/api/v1/admin/products', $payload)->assertCreated();
    }

    public function test_store_syncs_primary_category(): void
    {
        $category = ProductCategory::create([
            'slug_hy' => 'hataki-mijocner',
            'slug_ru' => 'sredstva-dlya-pola',
            'slug_en' => 'floor-cleaners',
            'name' => ['hy' => 'Հատակի միջոցներ', 'ru' => 'Средства для пола', 'en' => 'Floor cleaners'],
        ]);

        $response = $this->postJson('/api/v1/admin/products', $this->payload([
            'category_ids' => [$category->id],
            'primary_category_id' => $category->id,
        ]));

        $response->assertCreated()->assertJsonPath('data.primaryCategoryId', $category->id);
    }

    public function test_update_changes_the_price(): void
    {
        $item = Product::create($this->payload());

        $response = $this->patchJson("/api/v1/admin/products/{$item->id}", [
            'price_amount' => 5200,
        ]);

        $response->assertOk()->assertJsonPath('data.priceAmount', 5200);
        $this->assertDatabaseHas('products', ['id' => $item->id, 'price_amount' => 5200]);
    }

    public function test_update_can_archive_and_restore_an_item(): void
    {
        $item = Product::create($this->payload());

        $this->patchJson("/api/v1/admin/products/{$item->id}", ['status' => 'archived'])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('products', ['id' => $item->id, 'status' => 'archived']);

        $this->patchJson("/api/v1/admin/products/{$item->id}", ['status' => 'draft'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_update_rejects_publishing_a_product_with_missing_translations(): void
    {
        $item = Product::create($this->payload());

        $this->patchJson("/api/v1/admin/products/{$item->id}", ['status' => 'published'])
            ->assertStatus(422);
    }

    public function test_update_changing_a_locale_slug_records_a_redirect(): void
    {
        $item = Product::create($this->payload());

        $this->patchJson("/api/v1/admin/products/{$item->id}", ['slug_hy' => 'new-slug-hy'])
            ->assertOk()
            ->assertJsonPath('data.slugHy', 'new-slug-hy');

        $this->assertDatabaseHas('product_slug_redirects', [
            'locale' => 'hy',
            'old_slug' => 'floor-cleaner-concentrate',
            'product_id' => $item->id,
        ]);
    }

    public function test_update_does_not_duplicate_redirect_rows_on_repeated_saves(): void
    {
        $item = Product::create($this->payload());

        $this->patchJson("/api/v1/admin/products/{$item->id}", ['slug_hy' => 'new-slug-hy'])->assertOk();
        $this->patchJson("/api/v1/admin/products/{$item->id}", ['price_amount' => 4600])->assertOk();

        $this->assertEquals(
            1,
            \App\Models\ProductSlugRedirect::query()
                ->where('locale', 'hy')
                ->where('old_slug', 'floor-cleaner-concentrate')
                ->count(),
        );
    }

    public function test_destroy_removes_the_item(): void
    {
        $item = Product::create($this->payload());

        $this->deleteJson("/api/v1/admin/products/{$item->id}")->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $item->id]);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductCategoryTest extends TestCase
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
            'slug_hy' => 'hataki-mijocner',
            'slug_ru' => 'sredstva-dlya-pola',
            'slug_en' => 'floor-cleaners',
            'name' => ['hy' => 'Հատակի միջոցներ', 'ru' => 'Средства для пола', 'en' => 'Floor cleaners'],
        ], $overrides);
    }

    public function test_store_creates_a_category(): void
    {
        $this->postJson('/api/v1/admin/product-categories', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.slugEn', 'floor-cleaners');
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        ProductCategory::create($this->payload());

        $this->postJson('/api/v1/admin/product-categories', $this->payload())
            ->assertStatus(422);
    }

    public function test_update_changes_the_name(): void
    {
        $category = ProductCategory::create($this->payload());

        $this->patchJson("/api/v1/admin/product-categories/{$category->id}", [
            'name' => ['hy' => 'Նոր անուն', 'ru' => 'Новое имя', 'en' => 'New name'],
        ])->assertOk()->assertJsonPath('data.name.en', 'New name');
    }

    public function test_destroy_removes_the_category(): void
    {
        $category = ProductCategory::create($this->payload());

        $this->deleteJson("/api/v1/admin/product-categories/{$category->id}")->assertNoContent();

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }
}

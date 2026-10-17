<?php

namespace Tests\Feature;

use App\Models\AdditionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdditionalServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(array $overrides = []): AdditionalService
    {
        return AdditionalService::create(array_merge([
            'slug' => 'two-seat-sofa',
            'category' => 'upholstery',
            'name' => ['hy' => 'Բազմոց', 'ru' => 'Диван', 'en' => 'Sofa'],
            'alt' => ['hy' => 'Բազմոցի մաքրում', 'ru' => 'Химчистка дивана', 'en' => 'Sofa cleaning'],
            'image' => '/images/services/additional/two-seat-sofa.webp',
            'price_amount' => 7000,
            'price_unit' => 'flat',
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_listing_returns_only_active_items_ordered_by_sort_order(): void
    {
        $this->makeItem(['slug' => 'second', 'sort_order' => 2]);
        $this->makeItem(['slug' => 'first', 'sort_order' => 1]);
        $this->makeItem(['slug' => 'archived', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->getJson('/api/v1/additional-services')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'first')
            ->assertJsonPath('data.1.slug', 'second');
    }

    public function test_listing_is_localized_via_lang_query_param(): void
    {
        $this->makeItem();

        $this->getJson('/api/v1/additional-services?lang=ru')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Диван');
    }

    public function test_listing_exposes_discount_percent(): void
    {
        $this->makeItem(['discount_percent' => 20]);

        $this->getJson('/api/v1/additional-services')
            ->assertOk()
            ->assertJsonPath('data.0.discountPercent', 20);
    }
}

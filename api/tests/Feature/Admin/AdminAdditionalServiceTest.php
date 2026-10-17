<?php

namespace Tests\Feature\Admin;

use App\Models\AdditionalService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAdditionalServiceTest extends TestCase
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
            'slug' => 'sofa-cleaning-2-seat',
            'category' => 'upholstery',
            'name' => ['hy' => 'Բազմոց', 'ru' => 'Диван', 'en' => 'Sofa'],
            'alt' => ['hy' => 'Բազմոցի մաքրում', 'ru' => 'Химчистка дивана', 'en' => 'Sofa cleaning'],
            'image' => '/images/services/additional/two-seat-sofa.webp',
            'price_amount' => 7000,
            'price_unit' => 'flat',
        ], $overrides);
    }

    public function test_index_lists_active_and_archived_items(): void
    {
        AdditionalService::create($this->payload());
        AdditionalService::create($this->payload(['slug' => 'archived-item', 'is_active' => false]));

        $this->getJson('/api/v1/admin/additional-services')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_creates_an_item(): void
    {
        $response = $this->postJson('/api/v1/admin/additional-services', $this->payload());

        $response->assertCreated()->assertJsonPath('data.slug', 'sofa-cleaning-2-seat');
        $this->assertDatabaseHas('additional_services', ['slug' => 'sofa-cleaning-2-seat', 'price_amount' => 7000]);
    }

    public function test_store_rejects_an_invalid_category(): void
    {
        $this->postJson('/api/v1/admin/additional-services', $this->payload(['category' => 'not-a-category']))
            ->assertStatus(422);
    }

    public function test_store_rejects_missing_translations(): void
    {
        $payload = $this->payload();
        unset($payload['name']['ru']);

        $this->postJson('/api/v1/admin/additional-services', $payload)->assertStatus(422);
    }

    public function test_update_changes_the_price(): void
    {
        $item = AdditionalService::create($this->payload());

        $response = $this->patchJson("/api/v1/admin/additional-services/{$item->id}", [
            'price_amount' => 9500,
        ]);

        $response->assertOk()->assertJsonPath('data.priceAmount', 9500);
        $this->assertDatabaseHas('additional_services', ['id' => $item->id, 'price_amount' => 9500]);
    }

    public function test_update_changes_the_discount_percent(): void
    {
        $item = AdditionalService::create($this->payload());

        $response = $this->patchJson("/api/v1/admin/additional-services/{$item->id}", [
            'discount_percent' => 15,
        ]);

        $response->assertOk()->assertJsonPath('data.discountPercent', 15);
        $this->assertDatabaseHas('additional_services', ['id' => $item->id, 'discount_percent' => 15]);
    }

    public function test_store_rejects_a_discount_percent_over_100(): void
    {
        $this->postJson('/api/v1/admin/additional-services', $this->payload(['discount_percent' => 150]))
            ->assertStatus(422);
    }

    public function test_update_can_archive_and_restore_an_item(): void
    {
        $item = AdditionalService::create($this->payload());

        $this->patchJson("/api/v1/admin/additional-services/{$item->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.isActive', false);

        $this->assertDatabaseHas('additional_services', ['id' => $item->id, 'is_active' => false]);

        $this->patchJson("/api/v1/admin/additional-services/{$item->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.isActive', true);

        $this->assertDatabaseHas('additional_services', ['id' => $item->id, 'is_active' => true]);
    }

    public function test_destroy_removes_the_item(): void
    {
        $item = AdditionalService::create($this->payload());

        $this->deleteJson("/api/v1/admin/additional-services/{$item->id}")->assertNoContent();

        $this->assertDatabaseMissing('additional_services', ['id' => $item->id]);
    }
}

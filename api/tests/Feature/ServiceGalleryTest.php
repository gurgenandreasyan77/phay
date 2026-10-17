<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceGalleryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function seedService(): Service
    {
        $category = ServiceCategory::create([
            'slug' => 'auto-care',
            'sort_order' => 3,
            'name' => ['hy' => 'Ավտոխնամք', 'ru' => 'Автоуход', 'en' => 'Auto Care'],
        ]);

        return Service::create([
            'category_id' => $category->id,
            'slug' => 'car-interior-cleaning',
            'status' => 'active',
            'name' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => 'N'],
        ]);
    }

    private function galleryPayload(array $overrides = []): array
    {
        return array_merge([
            'before_image' => '/uploads/service-gallery/before.webp',
            'after_image' => '/uploads/service-gallery/after.webp',
            'client_consent' => true,
            'is_published' => true,
        ], $overrides);
    }

    public function test_item_requires_both_consent_and_publish_to_appear_publicly(): void
    {
        $service = $this->seedService();

        ServiceGalleryItem::create(array_merge($this->galleryPayload(['is_published' => false]), ['service_id' => $service->id]));
        ServiceGalleryItem::create(array_merge($this->galleryPayload(['client_consent' => false]), ['service_id' => $service->id]));
        ServiceGalleryItem::create(array_merge($this->galleryPayload(), ['service_id' => $service->id]));

        $this->getJson('/api/v1/service-gallery')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_gallery_can_be_filtered_by_service(): void
    {
        $service = $this->seedService();
        ServiceGalleryItem::create(array_merge($this->galleryPayload(), ['service_id' => $service->id]));

        $this->getJson('/api/v1/service-gallery?service=car-interior-cleaning')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/service-gallery?service=does-not-exist')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_can_create_an_unconsented_item_that_stays_hidden(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $service = $this->seedService();

        $response = $this->postJson('/api/v1/admin/service-gallery-items', array_merge(
            $this->galleryPayload(['client_consent' => false, 'is_published' => false]),
            ['service_id' => $service->id],
        ));

        $response->assertCreated();
        $this->getJson('/api/v1/service-gallery')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_admin_index_lists_items_regardless_of_visibility(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $service = $this->seedService();
        ServiceGalleryItem::create(array_merge($this->galleryPayload(['is_published' => false]), ['service_id' => $service->id]));

        $this->getJson('/api/v1/admin/service-gallery-items')->assertOk()->assertJsonCount(1, 'data');
    }
}

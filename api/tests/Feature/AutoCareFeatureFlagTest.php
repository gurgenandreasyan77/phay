<?php

namespace Tests\Feature;

use App\Features\FeatureFlag;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceGalleryItem;
use App\Models\ServicePackage;
use App\Models\ServiceVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class AutoCareFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private ServiceCategory $category;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ServiceCategory::create([
            'slug' => 'auto-care',
            'sort_order' => 3,
            'name' => ['hy' => 'Ավտոխնամք', 'ru' => 'Автоуход', 'en' => 'Auto Care'],
        ]);

        $this->service = Service::create([
            'category_id' => $this->category->id,
            'slug' => 'car-interior-cleaning',
            'sort_order' => 1,
            'status' => 'active',
            'name' => ['hy' => 'Սրահի քիմմաքրում', 'ru' => 'Химчистка салона', 'en' => 'Interior Cleaning'],
        ]);

        ServiceVideo::create([
            'service_id' => $this->service->id,
            'sort_order' => 1,
            'is_published' => true,
            'video_path' => 'videos/example.mp4',
            'title' => ['hy' => 'Վ', 'ru' => 'В', 'en' => 'V'],
        ]);

        ServiceGalleryItem::create([
            'service_id' => $this->service->id,
            'sort_order' => 1,
            'client_consent' => true,
            'is_published' => true,
            'before_image' => 'before.jpg',
            'after_image' => 'after.jpg',
        ]);

        ServicePackage::create([
            'category_id' => $this->category->id,
            'slug' => 'auto-care-package',
            'sort_order' => 1,
            'status' => 'active',
            'name' => ['hy' => 'Փ', 'ru' => 'П', 'en' => 'P'],
            'price_type' => 'fixed',
            'price_amount' => 10000,
        ]);
    }

    public function test_when_active_auto_care_endpoints_serve_data(): void
    {
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonFragment(['slug' => 'auto-care']);
        $this->getJson('/api/v1/services?category=auto-care')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/services/car-interior-cleaning')->assertOk();
        $this->getJson('/api/v1/service-videos')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/service-gallery')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/service-packages?category=auto-care')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/service-packages/auto-care-package')->assertOk();
    }

    public function test_when_disabled_auto_care_endpoints_return_404_or_hide_the_data(): void
    {
        Feature::deactivate(FeatureFlag::AutoCare->value);

        $this->getJson('/api/v1/categories')->assertOk()->assertJsonMissing(['slug' => 'auto-care']);
        $this->getJson('/api/v1/services?category=auto-care')->assertNotFound();
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/services/car-interior-cleaning')->assertNotFound();
        $this->getJson('/api/v1/service-videos')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/service-gallery')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/service-packages?category=auto-care')->assertNotFound();
        $this->getJson('/api/v1/service-packages/auto-care-package')->assertNotFound();
    }

    public function test_disabling_does_not_delete_any_data(): void
    {
        Feature::deactivate(FeatureFlag::AutoCare->value);

        $this->assertDatabaseHas('service_categories', ['id' => $this->category->id]);
        $this->assertDatabaseHas('services', ['id' => $this->service->id]);
        $this->assertDatabaseHas('service_packages', ['slug' => 'auto-care-package']);
    }

    public function test_re_enabling_restores_access_to_the_same_data(): void
    {
        Feature::deactivate(FeatureFlag::AutoCare->value);
        $this->getJson('/api/v1/services/car-interior-cleaning')->assertNotFound();

        Feature::activate(FeatureFlag::AutoCare->value);

        $this->getJson('/api/v1/services/car-interior-cleaning')
            ->assertOk()
            ->assertJsonPath('data.slug', 'car-interior-cleaning');
    }
}

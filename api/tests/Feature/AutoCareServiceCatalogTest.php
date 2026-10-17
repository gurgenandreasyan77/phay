<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceSlugRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCareServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function seedAutoCareService(array $overrides = []): Service
    {
        $category = ServiceCategory::create([
            'slug' => 'auto-care',
            'sort_order' => 3,
            'name' => ['hy' => 'Ավտոխնամք', 'ru' => 'Автоуход', 'en' => 'Auto Care'],
        ]);

        return Service::create(array_merge([
            'category_id' => $category->id,
            'slug' => 'car-interior-cleaning',
            'sort_order' => 1,
            'status' => 'active',
            'name' => ['hy' => 'Սրահի քիմմաքրում', 'ru' => 'Химчистка салона', 'en' => 'Interior Cleaning'],
            'car_types' => ['sedan', 'suv'],
            'materials' => ['fabric', 'leather'],
            'contamination_types' => ['dust', 'coffee'],
        ], $overrides));
    }

    public function test_draft_services_are_excluded_from_index_and_404_on_show(): void
    {
        $this->seedAutoCareService(['status' => 'draft']);

        $this->getJson('/api/v1/services?category=auto-care')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/services/car-interior-cleaning')->assertNotFound();
    }

    public function test_archived_services_are_excluded(): void
    {
        $this->seedAutoCareService(['status' => 'archived']);

        $this->getJson('/api/v1/services?category=auto-care')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/services/car-interior-cleaning')->assertNotFound();
    }

    public function test_coming_soon_service_is_visible_but_not_orderable(): void
    {
        $this->seedAutoCareService(['status' => 'coming_soon']);

        $this->getJson('/api/v1/services/car-interior-cleaning')
            ->assertOk()
            ->assertJsonPath('data.status', 'coming_soon')
            ->assertJsonPath('data.isOrderable', false);
    }

    public function test_active_service_exposes_car_care_fields(): void
    {
        $this->seedAutoCareService();

        $this->getJson('/api/v1/services/car-interior-cleaning')
            ->assertOk()
            ->assertJsonPath('data.isOrderable', true)
            ->assertJsonPath('data.carTypes', ['sedan', 'suv'])
            ->assertJsonPath('data.materials', ['fabric', 'leather'])
            ->assertJsonPath('data.contaminationTypes', ['dust', 'coffee']);
    }

    public function test_renamed_slug_resolves_via_301_style_redirect_payload(): void
    {
        $service = $this->seedAutoCareService();

        ServiceSlugRedirect::create([
            'old_slug' => 'car-salon-cleaning',
            'service_id' => $service->id,
        ]);

        $this->getJson('/api/v1/services/car-salon-cleaning')
            ->assertOk()
            ->assertJsonPath('redirect.slug', 'car-interior-cleaning');
    }

    public function test_unknown_slug_without_redirect_is_404(): void
    {
        $this->getJson('/api/v1/services/does-not-exist')->assertNotFound();
    }
}

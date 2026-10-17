<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): Service
    {
        $category = ServiceCategory::create([
            'slug' => 'cleaning',
            'icon' => 'spray-can',
            'sort_order' => 1,
            'name' => ['hy' => 'Մաքրում', 'ru' => 'Уборка', 'en' => 'Cleaning'],
            'description' => ['hy' => 'Նկարագրություն', 'ru' => 'Описание', 'en' => 'Description'],
        ]);

        return Service::create([
            'category_id' => $category->id,
            'slug' => 'apartment-cleaning',
            'icon' => 'home',
            'sort_order' => 1,
            'status' => 'active',
            'name' => ['hy' => 'Բնակարանների մաքրում', 'ru' => 'Уборка квартир', 'en' => 'Apartment Cleaning'],
            'short_description' => ['hy' => 'Կարճ', 'ru' => 'Кратко', 'en' => 'Short'],
            'description' => ['hy' => 'Ամբողջական', 'ru' => 'Полное', 'en' => 'Full'],
        ]);
    }

    public function test_categories_are_listed_with_nested_active_services(): void
    {
        $this->seedCatalog();

        $response = $this->getJson('/api/v1/categories')->assertOk();

        $response->assertJsonPath('data.0.slug', 'cleaning')
            ->assertJsonPath('data.0.services.0.slug', 'apartment-cleaning');
    }

    public function test_categories_are_localized_via_lang_query_param(): void
    {
        $this->seedCatalog();

        $this->getJson('/api/v1/categories?lang=ru')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Уборка');

        $this->getJson('/api/v1/categories?lang=hy')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Մաքրում');
    }

    public function test_services_can_be_filtered_by_category(): void
    {
        $this->seedCatalog();

        $this->getJson('/api/v1/services?category=cleaning')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/services?category=does-not-exist')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_single_service_can_be_fetched_by_slug(): void
    {
        $this->seedCatalog();

        $this->getJson('/api/v1/services/apartment-cleaning')
            ->assertOk()
            ->assertJsonPath('data.slug', 'apartment-cleaning')
            ->assertJsonPath('data.category.slug', 'cleaning');
    }

    public function test_a_single_service_includes_sibling_services_in_its_category(): void
    {
        $service = $this->seedCatalog();

        $sibling = Service::create([
            'category_id' => $service->category_id,
            'slug' => 'house-cleaning',
            'sort_order' => 2,
            'status' => 'active',
            'name' => ['hy' => 'Առանձնատների մաքրում', 'ru' => 'Уборка домов', 'en' => 'House Cleaning'],
        ]);

        $response = $this->getJson('/api/v1/services/apartment-cleaning')->assertOk();

        $slugs = collect($response->json('data.category.services'))->pluck('slug');
        $this->assertTrue($slugs->contains($sibling->slug));
        $this->assertTrue($slugs->contains('apartment-cleaning'));
    }

    public function test_an_unknown_service_slug_returns_404(): void
    {
        $this->getJson('/api/v1/services/does-not-exist')->assertNotFound();
    }

    public function test_inactive_services_are_not_reachable(): void
    {
        $service = $this->seedCatalog();
        $service->update(['status' => 'archived']);

        $this->getJson('/api/v1/services/apartment-cleaning')->assertNotFound();
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_draft_services_are_not_reachable(): void
    {
        $service = $this->seedCatalog();
        $service->update(['status' => 'draft']);

        $this->getJson('/api/v1/services/apartment-cleaning')->assertNotFound();
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_coming_soon_services_are_visible_but_not_orderable(): void
    {
        $service = $this->seedCatalog();
        $service->update(['status' => 'coming_soon']);

        $this->getJson('/api/v1/services/apartment-cleaning')
            ->assertOk()
            ->assertJsonPath('data.status', 'coming_soon')
            ->assertJsonPath('data.isOrderable', false);

        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_temporarily_unavailable_services_are_visible_with_order_disabled(): void
    {
        $service = $this->seedCatalog();
        $service->update(['status' => 'temporarily_unavailable']);

        $this->getJson('/api/v1/services/apartment-cleaning')
            ->assertOk()
            ->assertJsonPath('data.isOrderable', false);
    }
}

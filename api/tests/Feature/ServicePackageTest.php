<?php

namespace Tests\Feature;

use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicePackageTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'full-interior-cleaning',
            'status' => 'draft',
            'name' => ['hy' => 'Ամբողջական քիմմաքրում', 'ru' => 'Полная химчистка', 'en' => 'Full Interior Cleaning'],
            'price_type' => 'on_inspection',
        ], $overrides);
    }

    public function test_draft_packages_are_not_publicly_listed_or_reachable(): void
    {
        ServicePackage::create($this->payload());

        $this->getJson('/api/v1/service-packages')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/service-packages/full-interior-cleaning')->assertNotFound();
    }

    public function test_active_packages_are_publicly_reachable(): void
    {
        ServicePackage::create($this->payload(['status' => 'active']));

        $this->getJson('/api/v1/service-packages')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/service-packages/full-interior-cleaning')
            ->assertOk()
            ->assertJsonPath('data.slug', 'full-interior-cleaning')
            ->assertJsonPath('data.priceType', 'on_inspection');
    }

    public function test_admin_can_create_and_update_a_package(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/admin/service-packages', $this->payload());
        $response->assertCreated()->assertJsonPath('data.status', 'draft');

        $id = $response->json('data.id');

        $this->patchJson("/api/v1/admin/service-packages/{$id}", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_admin_can_delete_a_package(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $package = ServicePackage::create($this->payload());

        $this->deleteJson("/api/v1/admin/service-packages/{$package->id}")->assertNoContent();
        $this->assertDatabaseMissing('service_packages', ['id' => $package->id]);
    }
}

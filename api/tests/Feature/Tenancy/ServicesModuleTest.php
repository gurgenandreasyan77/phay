<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The services catalog is a module the platform turns on per business (and
 * charges for), not a section every business has.
 */
class ServicesModuleTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private function signInTo(Tenant $tenant): void
    {
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);
        Sanctum::actingAs($user);
    }

    public function test_servio_keeps_the_module_and_its_catalog(): void
    {
        $this->assertTrue(Feature::for(Tenant::default())->active('services'));

        $this->getJson('/api/v1/services')->assertOk();
        $this->getJson('/api/v1/categories')->assertOk();
    }

    public function test_a_business_without_the_module_has_no_catalog_on_its_site_or_admin(): void
    {
        $shop = $this->createTenant('test-shop');
        $key = $this->createSite($shop)->public_key;
        $this->asTenant($shop, fn () => $this->createService('cake', 'Տորթ'));
        $this->signInTo($shop);

        $this->assertFalse(Feature::for($shop)->active('services'));
        $this->getJson('/api/v1/services', ['X-Site-Key' => $key])->assertNotFound();
        $this->getJson('/api/v1/services/cake', ['X-Site-Key' => $key])->assertNotFound();
        $this->getJson('/api/v1/categories', ['X-Site-Key' => $key])->assertNotFound();
        $this->getJson('/api/v1/admin/services')->assertNotFound();
        $this->getJson('/api/v1/admin/service-categories')->assertNotFound();
        $this->postJson('/api/v1/admin/service-categories', [])->assertNotFound();

        Feature::for($shop)->activate('services');

        $this->getJson('/api/v1/services', ['X-Site-Key' => $key])->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/services')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_auto_care_alone_still_opens_the_catalog_it_is_built_on(): void
    {
        $shop = $this->createTenant('test-shop');
        $key = $this->createSite($shop)->public_key;
        Feature::for($shop)->activate('auto-care');

        $this->getJson('/api/v1/services', ['X-Site-Key' => $key])->assertOk();
    }

    public function test_the_module_has_its_own_price_in_the_subscription(): void
    {
        $this->superAdmin();

        $this->putJson('/api/v1/admin/module-prices', ['prices' => ['services' => 2000]])->assertOk();
        $this->getJson('/api/v1/admin/module-prices')
            ->assertOk()
            ->assertJsonFragment(['key' => 'services', 'price' => 2000]);
    }

    private function superAdmin(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($user);
    }
}

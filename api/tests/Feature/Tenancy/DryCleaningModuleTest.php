<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

/**
 * The upholstery calculator and its add-on services are Servio's own module,
 * not part of every business's services.
 */
class DryCleaningModuleTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    public function test_servio_keeps_the_module_and_its_public_endpoints(): void
    {
        $this->assertTrue(Feature::for(Tenant::default())->active('dry-cleaning'));

        $this->getJson('/api/v1/calculator-settings')->assertOk();
        $this->getJson('/api/v1/additional-services')->assertOk();
    }

    public function test_another_business_has_no_calculator_until_the_module_is_on(): void
    {
        $shop = $this->createTenant('test-shop');
        $key = $this->createSite($shop)->public_key;

        $this->assertFalse(Feature::for($shop)->active('dry-cleaning'));
        $this->getJson('/api/v1/calculator-settings', ['X-Site-Key' => $key])->assertNotFound();
        $this->getJson('/api/v1/additional-services', ['X-Site-Key' => $key])->assertNotFound();

        Feature::for($shop)->activate('dry-cleaning');
        $this->getJson('/api/v1/calculator-settings', ['X-Site-Key' => $key])->assertOk();
    }
}

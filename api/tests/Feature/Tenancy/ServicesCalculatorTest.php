<?php

namespace Tests\Feature\Tenancy;

use App\Models\CalculatorSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The upholstery calculator and its add-on services are a part of the
 * services module a business has only once it was added (Servio has one),
 * not a module of their own.
 */
class ServicesCalculatorTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    public function test_servio_keeps_its_calculator_and_add_ons(): void
    {
        $this->assertTrue(Feature::for(Tenant::default())->active('services'));
        $this->assertSame(1, CalculatorSetting::withoutTenancy()->where('tenant_id', Tenant::default()->id)->count());

        $this->getJson('/api/v1/calculator-settings')->assertOk();
        $this->getJson('/api/v1/additional-services')->assertOk();
    }

    public function test_another_business_has_no_calculator_until_one_is_added(): void
    {
        $shop = $this->withServices($this->createTenant('test-shop'));
        $key = $this->createSite($shop)->public_key;
        $owner = User::factory()->forTenant($shop, 'owner')->create();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/calculator-settings', ['X-Site-Key' => $key])->assertNotFound();
        $this->getJson('/api/v1/admin/calculator-settings')->assertNotFound();
        $this->assertSame(0, CalculatorSetting::withoutTenancy()->where('tenant_id', $shop->id)->count());

        $this->postJson('/api/v1/admin/calculator-settings')->assertCreated();
        $this->postJson('/api/v1/admin/calculator-settings')->assertOk();

        $this->getJson('/api/v1/admin/calculator-settings')->assertOk();
        $this->getJson('/api/v1/calculator-settings', ['X-Site-Key' => $key])->assertOk();
        $this->assertSame(1, CalculatorSetting::withoutTenancy()->where('tenant_id', $shop->id)->count());
    }

    public function test_without_the_services_module_there_is_no_calculator(): void
    {
        Feature::for(Tenant::default())->deactivate('services');

        $this->getJson('/api/v1/calculator-settings')->assertNotFound();
        $this->getJson('/api/v1/additional-services')->assertNotFound();
    }
}

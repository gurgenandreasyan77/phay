<?php

namespace Tests\Feature\Tenancy;

use App\Models\Lead;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Admin API: a user works on their own business and can neither see nor
 * touch another one's data.
 */
class AdminTenantTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private Lead $servioLead;

    private Lead $shopLead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->withServices($this->createTenant('test-shop'));

        $this->servioLead = $this->createLead('Servio client');
        $this->shopLead = $this->asTenant($this->shop, fn () => $this->createLead('Shop client'));
    }

    private function createLead(string $name): Lead
    {
        return Lead::create([
            'name' => $name,
            'phone' => '+37491000000',
            'preferred_locale' => 'hy',
            'consent' => true,
        ]);
    }

    private function actingAsShopOwner(): User
    {
        return Sanctum::actingAs(User::factory()->forTenant($this->shop)->create());
    }

    public function test_a_user_sees_only_their_business_leads(): void
    {
        $this->actingAsShopOwner();

        $this->getJson('/api/v1/admin/leads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Shop client');
    }

    public function test_a_user_can_open_their_own_lead_but_not_another_business_lead(): void
    {
        $this->actingAsShopOwner();

        $this->getJson("/api/v1/admin/leads/{$this->shopLead->id}")->assertOk();
        $this->getJson("/api/v1/admin/leads/{$this->servioLead->id}")->assertNotFound();
        $this->patchJson("/api/v1/admin/leads/{$this->servioLead->id}", ['status' => 'contacted'])->assertNotFound();

        $this->assertSame('new', $this->servioLead->fresh()->status->value);
    }

    public function test_records_created_in_the_admin_belong_to_the_users_business(): void
    {
        $this->actingAsShopOwner();

        $this->postJson('/api/v1/admin/service-categories', [
            'slug' => 'cleaning',
            'name' => ['hy' => 'Մաքրում', 'ru' => 'Уборка', 'en' => 'Cleaning'],
        ])->assertCreated();

        $this->assertSame($this->shop->id, ServiceCategory::withoutTenancy()->sole()->tenant_id);
    }

    public function test_slugs_are_unique_per_business_not_globally(): void
    {
        ServiceCategory::create(['slug' => 'cleaning', 'sort_order' => 1, 'name' => ['hy' => 'a', 'ru' => 'a', 'en' => 'a']]);
        $this->actingAsShopOwner();

        $payload = ['slug' => 'cleaning', 'name' => ['hy' => 'Մաքրում', 'ru' => 'Уборка', 'en' => 'Cleaning']];

        $this->postJson('/api/v1/admin/service-categories', $payload)->assertCreated();
        $this->postJson('/api/v1/admin/service-categories', $payload)->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public function test_another_business_records_cannot_be_referenced_by_id_or_slug(): void
    {
        $servioService = $this->createService('sofa-cleaning', 'Servio sofa');
        $this->actingAsShopOwner();

        $this->postJson('/api/v1/admin/leads', [
            'name' => 'Manual client',
            'phone' => '+374 91 123456',
            'service_slug' => $servioService->slug,
        ])->assertUnprocessable()->assertJsonValidationErrors('service_slug');

        Feature::for($this->shop)->activate('site');
        $this->postJson('/api/v1/admin/service-videos', [
            'service_id' => $servioService->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_a_user_without_a_business_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->withoutTenant()->create());

        $this->getJson('/api/v1/admin/leads')->assertForbidden();
    }

    public function test_a_super_admin_without_a_business_works_on_the_default_one(): void
    {
        $user = User::factory()->withoutTenant()->create();
        $user->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/leads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Servio client');
    }

    public function test_the_chosen_current_business_is_used_when_the_user_belongs_to_it(): void
    {
        $user = User::factory()->create(); // owner of the default business
        $user->tenants()->attach($this->shop->id, ['role' => 'editor']);
        $user->forceFill(['current_tenant_id' => $this->shop->id])->save();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/leads')->assertOk()->assertJsonPath('data.0.name', 'Shop client');

        // Removed from that business: falls back to one they still belong to.
        $user->tenants()->detach($this->shop->id);

        $this->getJson('/api/v1/admin/leads')->assertOk()->assertJsonPath('data.0.name', 'Servio client');
    }
}

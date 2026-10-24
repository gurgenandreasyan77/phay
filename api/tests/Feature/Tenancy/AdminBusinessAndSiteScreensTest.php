<?php

namespace Tests\Feature\Tenancy;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBusinessAndSiteScreensTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private function superAdmin(): User
    {
        $user = User::factory()->withTwoFactor()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return Sanctum::actingAs($user);
    }

    public function test_me_lists_the_current_business_and_the_ones_the_user_can_switch_to(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->create()); // owner of the default business only

        $this->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.currentTenant.slug', 'phay')
            ->assertJsonCount(1, 'data.tenants')
            ->assertJsonPath('data.tenants.0.role', 'owner');

        $this->superAdmin();

        $this->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonCount(2, 'data.tenants')
            ->assertJsonPath('data.tenants.1.slug', $shop->slug);
    }

    public function test_switching_business_changes_what_the_admin_works_on(): void
    {
        $shop = $this->createTenant('test-shop');
        $user = User::factory()->create();
        $user->tenants()->attach($shop->id, ['role' => 'editor']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/switch-tenant', ['tenant_id' => $shop->id])
            ->assertOk()
            ->assertJsonPath('data.currentTenant.slug', 'test-shop');

        $this->assertSame($shop->id, $user->fresh()->current_tenant_id);
        $this->getJson('/api/v1/admin/me')->assertJsonPath('data.currentTenant.slug', 'test-shop');
    }

    public function test_a_user_cannot_switch_to_a_business_they_do_not_belong_to(): void
    {
        $other = $this->createTenant('other-shop');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/switch-tenant', ['tenant_id' => $other->id])->assertForbidden();
    }

    public function test_only_a_super_admin_can_create_a_business(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/admin/tenants', ['name' => 'Test Shop'])->assertForbidden();

        $this->superAdmin();
        $this->postJson('/api/v1/admin/tenants', ['name' => 'Test Shop'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'test-shop')
            ->assertJsonPath('data.sitesCount', 0);

        $this->postJson('/api/v1/admin/tenants', ['name' => 'Test Shop'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_a_super_admin_lists_every_business(): void
    {
        $this->createTenant('other-shop');
        $this->superAdmin();

        $this->getJson('/api/v1/admin/tenants')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'phay')
            ->assertJsonPath('data.0.sitesCount', 1)
            ->assertJsonPath('data.0.usersCount', 1);
    }

    public function test_a_super_admin_lists_every_site_and_creates_one_for_a_chosen_business(): void
    {
        $shop = $this->withServices($this->createTenant('test-shop'));
        $this->superAdmin();

        $this->getJson('/api/v1/admin/sites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant.name', 'Phay');

        $this->postJson('/api/v1/admin/sites', ['name' => 'shop.am'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tenant_id');

        $response = $this->postJson('/api/v1/admin/sites', [
            'tenant_id' => $shop->id,
            'name' => 'shop.am',
            'domain' => 'shop.am',
            'allowed_origins' => ['https://shop.am', 'https://shop.am'],
        ])->assertCreated()
            ->assertJsonPath('data.allowedOrigins', ['https://shop.am'])
            ->assertJsonPath('data.isActive', true)
            ->assertJsonPath('data.tenant.id', $shop->id);

        $this->assertStringStartsWith(Site::KEY_PREFIX, $response->json('data.publicKey'));
        $this->assertSame($shop->id, Site::query()->where('name', 'shop.am')->sole()->tenant_id);
        $this->getJson('/api/v1/admin/sites')->assertJsonCount(2, 'data');

        // The new key works against the public API right away.
        $this->getJson('/api/v1/services', ['X-Site-Key' => $response->json('data.publicKey')])->assertOk();
    }

    public function test_invalid_origins_and_domains_are_rejected(): void
    {
        $this->superAdmin();

        $this->postJson('/api/v1/admin/sites', [
            'tenant_id' => Tenant::default()->id,
            'name' => 'x',
            'domain' => 'https://shop.am/path',
            'allowed_origins' => ['https://shop.am/path'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['domain', 'allowed_origins.0']);
    }

    public function test_a_site_can_be_updated_deactivated_and_its_key_rotated(): void
    {
        $this->superAdmin();
        $site = Tenant::default()->sites()->sole();
        $oldKey = $site->public_key;

        $this->patchJson("/api/v1/admin/sites/{$site->id}", ['is_active' => false, 'allowed_origins' => []])
            ->assertOk()
            ->assertJsonPath('data.isActive', false)
            ->assertJsonPath('data.allowedOrigins', []);

        $newKey = $this->postJson("/api/v1/admin/sites/{$site->id}/rotate-key")->assertOk()->json('data.publicKey');

        $this->assertNotSame($oldKey, $newKey);
        $this->getJson('/api/v1/services', ['X-Site-Key' => $oldKey])->assertUnauthorized();
    }

    public function test_a_super_admin_manages_the_site_of_any_business(): void
    {
        $shop = $this->createTenant('test-shop');
        $shopSite = $this->createSite($shop);
        $this->superAdmin(); // works on the default business

        $this->patchJson("/api/v1/admin/sites/{$shopSite->id}", ['name' => 'shop-renamed.am'])
            ->assertOk()
            ->assertJsonPath('data.tenant.id', $shop->id);
        $this->postJson("/api/v1/admin/sites/{$shopSite->id}/rotate-key")->assertOk();

        $this->assertSame('shop-renamed.am', $shopSite->fresh()->name);
        $this->assertNotSame($shopSite->public_key, $shopSite->fresh()->public_key);
    }
}

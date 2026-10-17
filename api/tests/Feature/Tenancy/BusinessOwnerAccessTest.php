<?php

namespace Tests\Feature\Tenancy;

use App\Models\FeatureFlagLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A business owner signs in to the same admin panel as the platform
 * administrator, but works on their own business only.
 */
class BusinessOwnerAccessTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private function createOwnerLogin(Tenant $tenant, string $email = 'owner@shop.am'): User
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson("/api/v1/admin/tenants/{$tenant->id}/users", [
            'name' => 'Shop Owner',
            'email' => $email,
            'password' => 'secret-pass',
        ])->assertCreated()
            ->assertJsonPath('data.email', $email)
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.isSuperAdmin', false);

        return User::query()->where('email', $email)->sole();
    }

    public function test_a_super_admin_creates_a_login_and_the_owner_signs_in_to_their_business(): void
    {
        $shop = $this->createTenant('test-shop');
        $this->createOwnerLogin($shop);
        // Sign in for real, as the admin panel does (no Sanctum::actingAs).
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $token = $this->postJson('/api/v1/admin/login', ['email' => 'owner@shop.am', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.user.isSuperAdmin', false)
            ->json('data.token');

        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.isSuperAdmin', false)
            ->assertJsonPath('data.currentTenant.slug', 'test-shop')
            ->assertJsonCount(1, 'data.tenants');

        $this->getJson("/api/v1/admin/tenants/{$shop->id}/users")
            ->assertForbidden(); // still the owner's token
    }

    public function test_the_owner_cannot_use_platform_screens(): void
    {
        $shop = $this->createTenant('test-shop');
        $owner = $this->createOwnerLogin($shop);
        $site = $this->createSite($shop);
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/admin/tenants')->assertForbidden();
        $this->postJson('/api/v1/admin/tenants', ['name' => 'Mine'])->assertForbidden();
        $this->getJson("/api/v1/admin/tenants/{$shop->id}/users")->assertForbidden();
        $this->postJson("/api/v1/admin/tenants/{$shop->id}/users", [
            'name' => 'X', 'email' => 'x@shop.am', 'password' => 'secret-pass',
        ])->assertForbidden();
        $this->getJson('/api/v1/admin/sites')->assertForbidden();
        $this->postJson('/api/v1/admin/sites', ['name' => 'x.am'])->assertForbidden();
        $this->patchJson("/api/v1/admin/sites/{$site->id}", ['name' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/admin/sites/{$site->id}/rotate-key")->assertForbidden();
        $this->patchJson('/api/v1/admin/feature-flags/market', ['active' => true])->assertForbidden();
        $this->getJson("/api/v1/admin/tenants/{$shop->id}/features")->assertForbidden();
        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/features/market", ['active' => true])->assertForbidden();
        $this->postJson('/api/v1/admin/switch-tenant', ['tenant_id' => Tenant::default()->id])->assertForbidden();

        // The basic sections work.
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/leads')->assertOk();
        $this->getJson('/api/v1/admin/services')->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertOk();
        $this->getJson('/api/v1/admin/feature-flags')->assertOk();
    }

    public function test_the_owner_sees_and_changes_only_their_business_data(): void
    {
        $shop = $this->createTenant('test-shop');
        $servioService = $this->createService('sofa', 'Servio sofa');
        $shopService = $this->asTenant($shop, fn () => $this->createService('shop-item', 'Shop item'));
        Sanctum::actingAs($this->createOwnerLogin($shop));

        $this->getJson('/api/v1/admin/services')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shopService->id);

        $this->patchJson("/api/v1/admin/services/{$servioService->id}", ['status' => 'hidden'])->assertNotFound();
        $this->deleteJson("/api/v1/admin/services/{$servioService->id}")->assertNotFound();
        $this->assertSame('active', $servioService->fresh()->status->value);
    }

    public function test_a_suspended_business_locks_its_owner_out_but_not_the_platform_admin(): void
    {
        $shop = $this->createTenant('test-shop');
        $owner = $this->createOwnerLogin($shop);
        $shop->update(['status' => Tenant::STATUS_SUSPENDED]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/me')->assertForbidden();

        $admin = User::factory()->forTenant($shop)->superAdmin()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/me')->assertOk()->assertJsonPath('data.currentTenant.slug', 'test-shop');
    }

    public function test_login_details_are_validated(): void
    {
        $shop = $this->createTenant('test-shop');
        $this->createOwnerLogin($shop);

        $this->postJson("/api/v1/admin/tenants/{$shop->id}/users", [
            'name' => 'Again', 'email' => 'owner@shop.am', 'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_a_new_password_signs_the_owner_out_and_works(): void
    {
        $shop = $this->createTenant('test-shop');
        $owner = $this->createOwnerLogin($shop);
        $owner->createToken('admin-panel');

        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/users/{$owner->id}", ['password' => 'new-secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@shop.am');

        $this->assertSame(0, $owner->tokens()->count());
        $this->assertTrue(Hash::check('new-secret-pass', $owner->fresh()->password));
    }

    public function test_removing_a_login_deletes_it_unless_it_is_a_platform_admin(): void
    {
        $shop = $this->createTenant('test-shop');
        $owner = $this->createOwnerLogin($shop);
        $admin = User::query()->where('is_super_admin', true)->sole();
        $otherAdmin = User::factory()->forTenant($shop)->superAdmin()->create();

        $this->deleteJson("/api/v1/admin/tenants/{$shop->id}/users/{$owner->id}")->assertNoContent();
        $this->assertModelMissing($owner);

        $this->deleteJson("/api/v1/admin/tenants/{$shop->id}/users/{$otherAdmin->id}")->assertNoContent();
        $this->assertModelExists($otherAdmin);
        $this->assertFalse($otherAdmin->tenants()->whereKey($shop->id)->exists());

        $admin->tenants()->attach($shop->id, ['role' => 'owner']);
        $this->deleteJson("/api/v1/admin/tenants/{$shop->id}/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user');
    }

    public function test_a_login_of_another_business_cannot_be_changed_through_this_one(): void
    {
        $shop = $this->createTenant('test-shop');
        $other = $this->createTenant('other-shop');
        $owner = $this->createOwnerLogin($other, 'owner@other.am');

        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/users/{$owner->id}", ['password' => 'new-secret-pass'])
            ->assertNotFound();
        $this->deleteJson("/api/v1/admin/tenants/{$shop->id}/users/{$owner->id}")->assertNotFound();
        $this->assertModelExists($owner);
    }

    public function test_a_new_business_starts_with_optional_modules_off(): void
    {
        $shop = $this->createTenant('test-shop');

        $this->assertFalse(Feature::for($shop)->active('market'));
        $this->assertFalse(Feature::for($shop)->active('auto-care'));
        $this->assertTrue(Feature::for(Tenant::default())->active('market'));

        Sanctum::actingAs($this->createOwnerLogin($shop));
        $this->getJson('/api/v1/admin/feature-flags')
            ->assertOk()
            ->assertJsonPath('data.0.active', false)
            ->assertJsonPath('data.1.active', false);
    }

    public function test_a_super_admin_turns_a_module_on_for_one_business_from_the_platform(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->superAdmin()->create()); // works on Servio

        $this->getJson("/api/v1/admin/tenants/{$shop->id}/features")
            ->assertOk()
            ->assertJsonPath('data.1.key', 'market')
            ->assertJsonPath('data.1.active', false);

        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/features/market", ['active' => true])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.lastChangedBy', User::query()->where('is_super_admin', true)->value('name'));

        $this->assertTrue(Feature::for($shop)->active('market'));
        $this->assertFalse(Feature::for($shop)->active('auto-care'));
        $this->assertSame($shop->id, FeatureFlagLog::withoutTenancy()->sole()->tenant_id);

        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/features/unknown", ['active' => true])
            ->assertUnprocessable();
    }

    public function test_the_super_admin_command_promotes_an_existing_login(): void
    {
        $user = User::factory()->create(['email' => 'boss@servio.am']);

        $this->artisan('platform:super-admin', ['email' => 'boss@servio.am'])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_super_admin);

        $this->artisan('platform:super-admin', ['email' => 'nobody@servio.am'])->assertFailed();
    }
}

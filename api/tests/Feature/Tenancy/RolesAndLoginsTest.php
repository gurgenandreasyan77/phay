<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Roles inside a business (owner, admin, moderator) and on the platform
 * (super admin, moderator), and how logins are handed over again.
 */
class RolesAndLoginsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private function member(Tenant $tenant, string $role): User
    {
        return User::factory()->forTenant($tenant, $role)->create();
    }

    public function test_a_super_admin_creates_logins_with_a_role_and_changes_it(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $id = $this->postJson("/api/v1/admin/tenants/{$shop->id}/users", [
            'name' => 'Ani', 'email' => 'ani@shop.am', 'role' => 'moderator',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'moderator')
            ->assertJsonPath('data.email', 'ani@shop.am')
            ->json('data.id');

        $this->postJson("/api/v1/admin/tenants/{$shop->id}/users", [
            'name' => 'X', 'email' => 'x@shop.am', 'role' => 'cashier',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/users/{$id}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        // The list shows every login (the email they sign in with) and role.
        $this->getJson("/api/v1/admin/tenants/{$shop->id}/users")
            ->assertOk()
            ->assertJsonPath('data.0.email', 'ani@shop.am')
            ->assertJsonPath('data.0.role', 'admin');
    }

    public function test_staff_send_a_reset_link_instead_of_setting_a_password(): void
    {
        $shop = $this->createTenant('test-shop');
        $owner = $this->member($shop, 'owner');
        $hash = $owner->password;
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson("/api/v1/admin/tenants/{$shop->id}/users/{$owner->id}/password-reset")
            ->assertOk()
            ->assertJsonPath('data.emailed', true);

        // The old password keeps working until the owner uses the link.
        $this->assertSame($hash, $owner->fresh()->password);
        $this->assertDatabaseHas('security_events', ['user_id' => $owner->id, 'event' => 'password_reset_link_created']);
    }

    public function test_an_owner_has_every_business_section(): void
    {
        $shop = $this->withServices($this->createTenant('test-shop'));
        Sanctum::actingAs($this->member($shop, 'owner'));

        $this->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.businessRole', 'owner')
            ->assertJsonPath('data.permissions', ['orders', 'catalog', 'settings', 'insights', 'expenses']);
        $this->getJson('/api/v1/admin/leads')->assertOk();
        $this->getJson('/api/v1/admin/services')->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertOk();
        $this->getJson('/api/v1/admin/analytics')->assertOk();
        $this->getJson('/api/v1/admin/assistant')->assertOk();
        $this->getJson('/api/v1/admin/expense-categories')->assertOk();
    }

    public function test_an_admin_runs_the_business_but_does_not_see_revenue_or_the_ai_assistant(): void
    {
        $shop = $this->withServices($this->createTenant('test-shop'));
        Sanctum::actingAs($this->member($shop, 'admin'));

        $this->getJson('/api/v1/admin/me')
            ->assertJsonPath('data.businessRole', 'admin')
            ->assertJsonPath('data.permissions', ['orders', 'catalog', 'settings']);
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/leads')->assertOk();
        $this->getJson('/api/v1/admin/services')->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertOk();
        $this->getJson('/api/v1/admin/analytics')->assertForbidden();
        $this->getJson('/api/v1/admin/assistant')->assertForbidden();
        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Hi']]])->assertForbidden();
        $this->getJson("/api/v1/admin/tenants/{$shop->id}/users")->assertForbidden();
    }

    public function test_a_moderator_works_with_orders_only(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs($this->member($shop, 'moderator'));

        $this->getJson('/api/v1/admin/me')
            ->assertJsonPath('data.businessRole', 'moderator')
            ->assertJsonPath('data.permissions', ['orders']);
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $this->getJson('/api/v1/admin/leads')->assertOk();
        $this->getJson('/api/v1/admin/feature-flags')->assertOk();
        $this->getJson('/api/v1/admin/services')->assertForbidden();
        $this->postJson('/api/v1/admin/service-categories', [])->assertForbidden();
        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->putJson('/api/v1/admin/settings', [])->assertForbidden();
        $this->postJson('/api/v1/admin/uploads')->assertForbidden();
        $this->getJson('/api/v1/admin/analytics')->assertForbidden();
    }

    public function test_a_platform_moderator_onboards_businesses_but_cannot_change_modules_or_staff(): void
    {
        $shop = $this->createTenant('test-shop');
        $superAdmin = User::factory()->superAdmin()->create();
        $moderator = User::factory()->withoutTenant()->platformModerator()->create();
        Sanctum::actingAs($moderator);

        $this->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.platformRole', 'moderator')
            ->assertJsonPath('data.isSuperAdmin', false)
            ->assertJsonPath('data.businessRole', null);

        $this->getJson('/api/v1/admin/tenants')->assertOk();
        $this->postJson('/api/v1/admin/tenants', ['name' => 'New Shop'])->assertCreated();
        $this->getJson('/api/v1/admin/sites')->assertOk();
        $this->postJson("/api/v1/admin/tenants/{$shop->id}/users", [
            'name' => 'Owner', 'email' => 'owner@shop.am',
        ])->assertCreated()->assertJsonPath('data.role', 'owner');
        // Looks at the business from the platform level, never inside it.
        $this->getJson("/api/v1/admin/tenants/{$shop->id}/overview")->assertOk();
        $this->postJson('/api/v1/admin/switch-tenant', ['tenant_id' => $shop->id])->assertForbidden();

        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/features/market", ['active' => true])->assertForbidden();
        $this->getJson('/api/v1/admin/platform-users')->assertForbidden();

        // The super admin's account is out of a moderator's reach.
        $superAdmin->tenants()->syncWithoutDetaching([$shop->id => ['role' => 'owner']]);
        $this->patchJson("/api/v1/admin/tenants/{$shop->id}/users/{$superAdmin->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
        $this->postJson("/api/v1/admin/tenants/{$shop->id}/users/{$superAdmin->id}/password-reset")->assertForbidden();
        $this->deleteJson("/api/v1/admin/tenants/{$shop->id}/users/{$superAdmin->id}/two-factor")->assertForbidden();
        $this->deleteJson("/api/v1/admin/tenants/{$shop->id}/users/{$superAdmin->id}")->assertForbidden();
    }

    public function test_the_super_admin_manages_the_platform_team(): void
    {
        $me = User::factory()->superAdmin()->create();
        Sanctum::actingAs($me);

        $id = $this->postJson('/api/v1/admin/platform-users', [
            'name' => 'Narek', 'email' => 'narek@phay.am', 'role' => 'moderator',
        ])->assertCreated()
            ->assertJsonPath('data.role', 'moderator')
            ->assertJsonPath('data.activated', false)
            ->assertJson(fn ($json) => $json->where('invitation.url', fn ($url) => str_contains($url, '/admin/invite#'))->etc())
            ->json('data.id');

        $this->assertTrue(User::find($id)->is_platform_moderator);
        $this->assertFalse(User::find($id)->is_super_admin);

        $this->getJson('/api/v1/admin/platform-users')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->patchJson("/api/v1/admin/platform-users/{$id}", ['role' => 'super_admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'super_admin');
        // Not activated yet: a fresh invitation, not a reset link.
        $this->postJson("/api/v1/admin/platform-users/{$id}/password-reset")->assertUnprocessable();
        $this->postJson("/api/v1/admin/platform-users/{$id}/invitation")->assertOk();

        // Can't lock themselves out.
        $this->patchJson("/api/v1/admin/platform-users/{$me->id}", ['role' => 'moderator'])->assertUnprocessable();
        $this->deleteJson("/api/v1/admin/platform-users/{$me->id}")->assertUnprocessable();

        $this->deleteJson("/api/v1/admin/platform-users/{$id}")->assertNoContent();
        $this->assertNull(User::find($id));

        // A business login isn't platform staff.
        $owner = User::factory()->create();
        $this->patchJson("/api/v1/admin/platform-users/{$owner->id}", ['name' => 'x'])->assertNotFound();
    }

    public function test_removing_staff_who_also_own_a_business_keeps_their_business_login(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $moderator = User::factory()->forTenant($shop)->platformModerator()->create();

        $this->deleteJson("/api/v1/admin/platform-users/{$moderator->id}")->assertNoContent();

        $moderator->refresh();
        $this->assertFalse($moderator->isPlatformStaff());
        $this->assertSame('owner', $moderator->businessRole($shop)?->value);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Features\FeatureFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_feature_flags(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super_admin' => true]));

        $response = $this->getJson('/api/v1/admin/feature-flags')->assertOk();

        $response->assertJsonFragment(['key' => FeatureFlag::Market->value, 'active' => true]);
    }

    public function test_regular_admin_can_view_but_not_change_flags(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super_admin' => false]));

        $this->getJson('/api/v1/admin/feature-flags')->assertOk();

        $this->patchJson('/api/v1/admin/feature-flags/market', ['active' => false])
            ->assertForbidden();

        $this->assertTrue(Feature::active(FeatureFlag::Market->value));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/admin/feature-flags')->assertUnauthorized();
        $this->patchJson('/api/v1/admin/feature-flags/market', ['active' => false])->assertUnauthorized();
    }

    public function test_super_admin_can_disable_the_market(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/v1/admin/feature-flags/market', ['active' => false])
            ->assertOk();

        $response->assertJsonPath('data.active', false);
        $this->assertFalse(Feature::active(FeatureFlag::Market->value));

        $this->assertDatabaseHas('feature_flag_logs', [
            'feature' => 'market',
            'old_value' => 1,
            'new_value' => 0,
            'changed_by_id' => $admin->id,
        ]);
    }

    public function test_super_admin_can_re_enable_the_market(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super_admin' => true]));

        Feature::deactivate(FeatureFlag::Market->value);

        $response = $this->patchJson('/api/v1/admin/feature-flags/market', ['active' => true])
            ->assertOk();

        $response->assertJsonPath('data.active', true);
        $this->assertTrue(Feature::active(FeatureFlag::Market->value));
    }

    public function test_toggling_the_same_value_does_not_create_a_duplicate_log_entry(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->patchJson('/api/v1/admin/feature-flags/market', ['active' => true])->assertOk();

        $this->assertDatabaseCount('feature_flag_logs', 0);
    }

    public function test_unknown_flag_key_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->patchJson('/api/v1/admin/feature-flags/not-a-real-flag', ['active' => false])
            ->assertStatus(422);
    }
}

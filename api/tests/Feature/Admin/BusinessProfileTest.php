<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactChannelType;
use App\Models\ContactChannel;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Settings: the business edits its name, logo and password; the platform
 * team finds it by name, contacts or address.
 */
class BusinessProfileTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    public function test_the_owner_renames_the_business_without_touching_the_address(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->forTenant($shop)->create());

        $this->putJson('/api/v1/admin/settings', ['address' => ['hy' => 'Երևան, Աբովյան 1']])->assertOk();

        $this->putJson('/api/v1/admin/settings', ['name' => '  Candle House  '])
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Candle House')
            ->assertJsonPath('data.address.hy', 'Երևան, Աբովյան 1');

        $this->assertSame('Candle House', $shop->fresh()->name);
        $this->putJson('/api/v1/admin/settings', ['name' => ''])->assertUnprocessable();
    }

    public function test_a_moderator_cannot_change_the_settings(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->forTenant($shop, 'moderator')->create());

        $this->putJson('/api/v1/admin/settings', ['name' => 'Other'])->assertForbidden();
        $this->postJson('/api/v1/admin/settings/logo', ['file' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
    }

    public function test_the_logo_is_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->forTenant($shop)->create());

        $first = $this->postJson('/api/v1/admin/settings/logo', ['file' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertOk()
            ->json('data.business.logo');

        $this->assertStringContainsString("uploads/{$shop->id}/logo/", $first);
        $firstPath = strstr($first, 'uploads/');
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->postJson('/api/v1/admin/settings/logo', ['file' => UploadedFile::fake()->image('new.jpg')])
            ->assertOk()
            ->json('data.business.logo');

        Storage::disk('public')->assertMissing($firstPath);
        $this->assertSame($second, $shop->fresh()->logo);
        // The logo travels with the business everywhere the admin shows it.
        $this->getJson('/api/v1/admin/me')->assertJsonPath('data.currentTenant.logo', $second);

        $this->deleteJson('/api/v1/admin/settings/logo')->assertOk()->assertJsonPath('data.business.logo', null);
        Storage::disk('public')->assertMissing(strstr($second, 'uploads/'));

        $this->postJson('/api/v1/admin/settings/logo', ['file' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
            ->assertUnprocessable();
    }

    public function test_a_user_changes_their_password_only_with_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'old-secret-1']);
        $user->createToken('other-device');
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/admin/password', [
            'current_password' => 'wrong',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/admin/password', [
            'current_password' => 'old-secret-1',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->putJson('/api/v1/admin/password', [
            'current_password' => 'old-secret-1',
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertOk();

        // Devices signed in with the old password are signed out.
        $this->assertSame(0, $user->tokens()->count());

        Auth::shouldUse('web');
        $this->assertTrue(Auth::validate(['email' => $user->email, 'password' => 'new-secret-1']));
        $this->assertFalse(Auth::validate(['email' => $user->email, 'password' => 'old-secret-1']));
    }

    public function test_the_platform_list_shows_the_logo_contacts_and_address(): void
    {
        $shop = $this->createTenant('test-shop');
        $shop->update(['logo' => 'http://localhost/storage/uploads/2/logo/a.png']);
        $this->asTenant($shop, function () {
            SiteSetting::current()->update(['address' => ['hy' => 'Գյումրի, Ռիժկովի 5']]);
            ContactChannel::create(['type' => ContactChannelType::Instagram, 'value' => '@candles', 'sort_order' => 2, 'is_active' => true]);
            ContactChannel::create(['type' => ContactChannelType::Phone, 'value' => '+374 55 33-76 33', 'is_primary' => true, 'sort_order' => 5, 'is_active' => true]);
            ContactChannel::create(['type' => ContactChannelType::Email, 'value' => 'hidden@shop.am', 'sort_order' => 1, 'is_active' => false]);
        });

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $row = collect($this->getJson('/api/v1/admin/tenants')->assertOk()->json('data'))->firstWhere('slug', 'test-shop');

        $this->assertSame('http://localhost/storage/uploads/2/logo/a.png', $row['logo']);
        $this->assertSame('Գյումրի, Ռիժկովի 5', $row['address']['hy']);
        // Active ones only, the primary first.
        $this->assertSame(['+374 55 33-76 33', '@candles'], array_column($row['contacts'], 'value'));
        $this->assertSame(0, $row['unreadMessages']);
    }

    public function test_the_platform_finds_a_business_by_name_phone_contact_or_address(): void
    {
        $shop = $this->createTenant('test-shop');
        $shop->update(['name' => 'Մոմերի տուն']);
        $this->asTenant($shop, function () {
            SiteSetting::current()->update(['address' => ['ru' => 'Гюмри, Рижкова 5']]);
            ContactChannel::create(['type' => ContactChannelType::Phone, 'value' => '+374 55 33-76 33', 'is_active' => true]);
            ContactChannel::create(['type' => ContactChannelType::Instagram, 'value' => '@Candles.am', 'is_active' => true]);
        });

        Sanctum::actingAs(User::factory()->platformModerator()->create());

        foreach (['մոմերի', 'TEST-SHOP', '55337633', '55 33 76', 'candles', 'гюмри'] as $search) {
            $this->getJson('/api/v1/admin/tenants?search='.urlencode($search))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.slug', 'test-shop');
        }

        $this->getJson('/api/v1/admin/tenants?search=nothing-like-this')->assertOk()->assertJsonCount(0, 'data');
        $this->assertCount(2, $this->getJson('/api/v1/admin/tenants')->json('data'));
        $this->assertSame(Tenant::count(), 2);
    }
}

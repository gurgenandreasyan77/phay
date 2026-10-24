<?php

namespace Tests\Feature\Tenancy;

use App\Models\ServiceCategory;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Sites\SiteOrigins;
use App\Sites\SiteRevalidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * How a site, which has no code of the platform, is connected to it: CORS
 * from the site's own settings and a cache-refresh webhook per site.
 */
class SiteConnectionTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private function superAdmin(): User
    {
        $user = User::factory()->withTwoFactor()->create();
        $user->forceFill(['is_super_admin' => true])->save();

        return Sanctum::actingAs($user);
    }

    public function test_a_site_domain_is_allowed_by_cors_without_any_env_change(): void
    {
        $shop = $this->withServices($this->createTenant('test-shop'));
        $key = $this->createSite($shop, ['domain' => 'shop.am'])->public_key;

        $this->getJson('/api/v1/services', ['X-Site-Key' => $key, 'Origin' => 'https://www.shop.am'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://www.shop.am');

        $this->getJson('/api/v1/services', ['X-Site-Key' => $key, 'Origin' => 'https://evil.example'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_listed_origins_replace_the_domain_and_changes_apply_at_once(): void
    {
        $shop = $this->withServices($this->createTenant('test-shop'));
        $site = $this->createSite($shop, ['domain' => 'shop.am']);

        $this->options('/api/v1/leads', [], [
            'Origin' => 'https://shop.am',
            'Access-Control-Request-Method' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', 'https://shop.am');

        $site->update(['allowed_origins' => ['https://new.shop.am']]);

        $this->assertSame(['https://new.shop.am'], array_values(array_diff(SiteOrigins::all(), Tenant::default()->sites()->sole()->browserOrigins())));
        $this->options('/api/v1/leads', [], [
            'Origin' => 'https://shop.am',
            'Access-Control-Request-Method' => 'POST',
        ])->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_an_edit_refreshes_only_the_sites_of_that_business(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $shop = $this->createTenant('test-shop');
        $shopSite = $this->createSite($shop);
        $category = ServiceCategory::query()->create([
            'slug' => 'cleaning',
            'sort_order' => 1,
            'name' => ['hy' => 'Մաքրում', 'ru' => 'Уборка', 'en' => 'Cleaning'],
        ]);
        // Rows made by the test itself, not by an edit under test.
        app(SiteRevalidator::class)->flush();

        Tenant::default()->sites()->sole()->update([
            'revalidate_url' => 'https://site.test/api/revalidate',
            'revalidate_secret' => 'site-secret-0123456789',
        ]);
        $shopSite->update([
            'revalidate_url' => 'https://shop.test/api/revalidate',
            'revalidate_secret' => 'shop-secret-0123456789ab',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/admin/service-categories/{$category->id}", ['sort_order' => 2])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://site.test/api/revalidate'
            && $request->header('X-Revalidate-Secret') === ['site-secret-0123456789']
            && $request['tags'] === ['service-categories', 'services']);
    }

    public function test_a_site_without_a_webhook_or_a_broken_one_never_fails_the_save(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        app(SiteRevalidator::class)->flush();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/admin/settings', ['name' => 'Phay'])->assertOk();
        Http::assertNothingSent();

        Tenant::default()->sites()->sole()->update([
            'revalidate_url' => 'https://site.test/api/revalidate',
            'revalidate_secret' => 'site-secret-0123456789',
        ]);

        $this->putJson('/api/v1/admin/settings', ['name' => 'Phay'])->assertOk();
        Http::assertSentCount(1);
    }

    public function test_the_webhook_secret_is_encrypted_write_only_and_can_be_checked(): void
    {
        Http::fake(['site.test/*' => Http::response(['data' => ['revalidated' => ['site-settings']]])]);
        $this->superAdmin();
        $site = Tenant::default()->sites()->sole();

        $this->postJson("/api/v1/admin/sites/{$site->id}/check-revalidation")->assertUnprocessable();

        $this->patchJson("/api/v1/admin/sites/{$site->id}", [
            'revalidate_url' => 'https://site.test/api/revalidate',
            'revalidate_secret' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('revalidate_secret');

        $this->patchJson("/api/v1/admin/sites/{$site->id}", [
            'revalidate_url' => 'https://site.test/api/revalidate',
            'revalidate_secret' => 'site-secret-0123456789',
        ])->assertOk()
            ->assertJsonPath('data.revalidateUrl', 'https://site.test/api/revalidate')
            ->assertJsonPath('data.hasRevalidateSecret', true)
            ->assertJsonMissingPath('data.revalidateSecret');

        $stored = DB::table('sites')->where('id', $site->id)->value('revalidate_secret');
        $this->assertNotSame('site-secret-0123456789', $stored);

        // An empty secret on a later save keeps the one already set.
        $this->patchJson("/api/v1/admin/sites/{$site->id}", ['revalidate_secret' => ''])->assertOk();
        $this->assertSame('site-secret-0123456789', $site->fresh()->revalidate_secret);

        $this->postJson("/api/v1/admin/sites/{$site->id}/check-revalidation")
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.status', 200);
    }

    public function test_only_a_super_admin_checks_a_webhook(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $site = Site::query()->firstOrFail();

        $this->postJson("/api/v1/admin/sites/{$site->id}/check-revalidation")->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Models\AdminNotification;
use App\Models\MetaDataDeletion;
use App\Models\SocialAccount;
use App\Models\SocialCompetitor;
use App\Models\SocialConnection;
use App\Models\SocialPost;
use App\Models\Tenant;
use App\Models\User;
use App\Social\MetaAuthException;
use App\Social\MetaClient;
use App\Social\MetaRateLimited;
use App\Social\PostStats;
use App\Social\SignedRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Fakes\FakeMetaClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The social module: a business connects its Facebook login (Pages and the
 * Instagram accounts linked to them, read only), its figures and posts are
 * kept and refreshed, it watches competitors' public Instagram figures, and
 * the AI assistant reads all of it. Off unless the super admin turns it on.
 * Meta is a fake.
 */
class SocialModuleTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    private User $owner;

    private FakeMetaClient $meta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-26 10:00:00');
        config(['services.meta.app_secret' => 'app-secret']);
        $this->salon = $this->createTenant('test-salon');
        $this->owner = User::factory()->forTenant($this->salon, 'owner')->create();
        Feature::for($this->salon)->activate('social');
        Sanctum::actingAs($this->owner);

        $this->meta = new FakeMetaClient;
        $this->meta->pages = [$this->page('page-1', 'Salon Page', 'ig-1')];
        $this->meta->profiles['ig-1'] = $this->profile('ig-1', 'salon.am', 2400);
        $this->meta->media['ig-1'] = [
            $this->metaPost('m1', '2026-10-25T09:00:00+0000', 120, 14, 'REELS', 'Before / after sofa cleaning'),
            $this->metaPost('m2', '2026-10-22T16:00:00+0000', 40, 2, 'IMAGE', 'Autumn discount'),
            $this->metaPost('m3', '2026-10-18T09:30:00+0000', 95, 9, 'REELS', 'Mattress cleaning'),
        ];
        $this->meta->media['page-1'] = [$this->metaPost('p1', '2026-10-24T08:00:00+0000', 12, 1, 'post', 'Facebook post')];
        $this->meta->discovery['rival.am'] = [
            'profile' => $this->profile('ig-9', 'rival.am', 5000),
            'posts' => [
                $this->metaPost('r1', '2026-10-25T08:00:00+0000', 300, 30, 'REELS'),
                $this->metaPost('r2', '2026-10-23T08:00:00+0000', 200, 10, 'IMAGE'),
                $this->metaPost('r3', '2026-10-20T08:00:00+0000', 250, 20, 'REELS'),
            ],
        ];
        $this->app->instance(MetaClient::class, $this->meta);
    }

    private function page(string $id, string $name, ?string $instagram): array
    {
        return [
            'id' => $id, 'name' => $name, 'token' => 'page-token-'.$id, 'category' => 'Cleaning service',
            'link' => "https://facebook.com/{$id}", 'picture' => null, 'followers' => 800,
            'instagram' => $instagram ? $this->profile($instagram, 'salon.am', 2400) : null,
        ];
    }

    private function profile(string $id, string $username, int $followers): array
    {
        return ['id' => $id, 'username' => $username, 'name' => ucfirst($username), 'picture' => null, 'followers' => $followers, 'follows' => 100, 'mediaCount' => 210, 'biography' => 'Cleaning in Yerevan', 'website' => 'https://example.am'];
    }

    private function metaPost(string $id, string $at, int $likes, int $comments, string $kind, ?string $caption = null): array
    {
        return ['id' => $id, 'kind' => $kind, 'caption' => $caption, 'permalink' => "https://instagram.com/p/{$id}", 'postedAt' => $at, 'likes' => $likes, 'comments' => $comments, 'reach' => $likes * 10];
    }

    /** Connects the owner's Facebook login (one Page: chosen and read at once). */
    private function connect(): array
    {
        $url = $this->postJson('/api/v1/admin/social/connect')->assertOk()->json('data.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $this->postJson('/api/v1/admin/social/callback', ['code' => 'c1', 'state' => $query['state']])->assertOk()->json('data');
    }

    public function test_the_module_is_off_until_the_super_admin_turns_it_on(): void
    {
        Feature::for($this->salon)->deactivate('social');

        $this->getJson('/api/v1/admin/social')->assertNotFound();
        $this->postJson('/api/v1/admin/social/connect')->assertNotFound();

        // The assistant doesn't get the social tools either.
        $client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $client);
        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Instagram?']]])->assertOk();
        $this->assertNotContains('social_profile', array_column($client->toolDefinitions, 'name'));
        $this->assertStringNotContainsString('social_competitors', $client->system);

        // The super admin turns it on for this business.
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->salon->id}/features/social", ['active' => true])->assertOk();
        $this->assertTrue(Feature::for($this->salon)->active('social'));
    }

    public function test_without_keys_it_says_it_is_not_set_up(): void
    {
        $this->meta->configured = false;

        $this->getJson('/api/v1/admin/social')->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.connection', null);
        $this->postJson('/api/v1/admin/social/connect')->assertStatus(503)->assertJsonPath('code', 'meta_not_configured');
    }

    public function test_connecting_keeps_the_token_encrypted_and_reads_the_only_page(): void
    {
        $state = $this->connect();

        $this->assertTrue($state['configured']);
        $this->assertSame('Anna Owner', $state['connection']['name']);
        $this->assertSame([], $state['connection']['missingPermissions']);
        $this->assertSame(['facebook', 'instagram'], array_column($state['connection']['accounts'], 'network'));
        $this->assertSame([true, true], array_column($state['connection']['accounts'], 'selected'));
        $this->assertStringNotContainsString('user-token', json_encode($state));
        $this->assertStringNotContainsString('page-token', json_encode($state));

        $raw = DB::table('social_connections')->first();
        $this->assertNotSame('user-token-c1', $raw->access_token);
        $this->assertNotSame('page-token-page-1', DB::table('social_accounts')->value('page_token'));
        $this->assertSame('user-token-c1', SocialConnection::withoutTenancy()->first()->access_token);
        $this->assertSame('2026-12-25', SocialConnection::withoutTenancy()->first()->expires_at->toDateString());

        // Read right away with the Page's token.
        $instagram = collect($state['insights'])->firstWhere('network', 'instagram');
        $this->assertSame('@salon.am', $instagram['handle']);
        $this->assertSame(2400, $instagram['followers']);
        $this->assertSame(1200, $instagram['last28Days']['totals']['reach']);
        $this->assertEquals(20, $instagram['last28Days']['changePercent']['reach']);
        $this->assertSame(['25-34' => 120, '18-24' => 60], $instagram['followersAudience']['age']);
        $this->assertSame(3, $instagram['postsLast90Days']['posts']);
        $this->assertSame('m1', SocialPost::withoutTenancy()->orderByDesc('likes')->first()->external_id);
        $this->assertSame('page-token-page-1', $this->meta->callsOf('instagramProfile')[0][1]);
        $this->assertSame(4, SocialPost::withoutTenancy()->count());
    }

    public function test_a_state_of_someone_else_or_too_old_is_refused(): void
    {
        $url = $this->postJson('/api/v1/admin/social/connect')->json('data.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'owner')->create());
        $this->postJson('/api/v1/admin/social/callback', ['code' => 'x', 'state' => $query['state']])
            ->assertStatus(422)->assertJsonPath('code', 'meta_state');

        Sanctum::actingAs($this->owner);
        $this->travel(16)->minutes();
        $this->postJson('/api/v1/admin/social/callback', ['code' => 'x', 'state' => $query['state']])->assertStatus(422);
        $this->postJson('/api/v1/admin/social/callback', ['code' => 'x', 'state' => 'forged'])->assertStatus(422);

        $this->assertSame(0, SocialConnection::withoutTenancy()->count());
    }

    public function test_with_several_pages_the_owner_chooses_which_are_the_business(): void
    {
        $this->meta->pages[] = $this->page('page-2', 'Personal blog', null);

        $state = $this->connect();
        $this->assertSame([false, false, false], array_column($state['connection']['accounts'], 'selected'));
        $this->assertSame([], $state['insights']);
        $this->assertSame([], $this->meta->callsOf('instagramProfile'));

        $ids = collect($state['connection']['accounts'])->where('pageId', 'page-1')->pluck('id')->all();
        $state = $this->putJson('/api/v1/admin/social/accounts', ['accounts' => $ids])->assertOk()->json('data');

        $this->assertCount(2, $state['insights']);
        $this->assertSame(['page-1'], collect($this->meta->callsOf('pageProfile'))->pluck(2)->all());

        // Dropping an account drops its posts and figures.
        $instagram = collect($state['connection']['accounts'])->firstWhere('network', 'instagram')['id'];
        $this->travel(11)->minutes();
        $this->putJson('/api/v1/admin/social/accounts', ['accounts' => [$instagram]])->assertOk();
        $this->assertSame(0, SocialPost::withoutTenancy()->whereIn('social_account_id', SocialAccount::withoutTenancy()->where('network', 'facebook')->pluck('id'))->count());
    }

    public function test_refresh_waits_between_calls_and_reports_meta_limits(): void
    {
        $this->connect();

        $this->postJson('/api/v1/admin/social/sync')->assertStatus(429)->assertJsonPath('code', 'social_cooldown');

        $this->travel(11)->minutes();
        $this->meta->throw['instagramMedia'] = new MetaRateLimited('limit', 4);
        $this->postJson('/api/v1/admin/social/sync')->assertStatus(429)->assertJsonPath('code', 'meta_rate_limited');

        // The figures read before stay.
        $this->assertSame(4, SocialPost::withoutTenancy()->count());
        $this->assertSame('rate_limited', SocialConnection::withoutTenancy()->first()->sync_error);

        $this->travel(11)->minutes();
        unset($this->meta->throw['instagramMedia']);
        $this->postJson('/api/v1/admin/social/sync')->assertOk()->assertJsonPath('data.result.accounts', 2);
    }

    public function test_a_refused_token_asks_to_connect_again_and_tells_the_owner(): void
    {
        $this->connect();
        $this->travel(11)->minutes();
        $this->meta->throw['pages'] = new MetaAuthException('Error validating access token', 190);

        $this->postJson('/api/v1/admin/social/sync')->assertStatus(409)->assertJsonPath('code', 'meta_reconnect');

        $this->getJson('/api/v1/admin/social')->assertJsonPath('data.connection.needsReconnect', true);
        $this->assertSame(1, AdminNotification::query()->where('user_id', $this->owner->id)->where('type', 'social')->count());

        // Connecting again clears it.
        unset($this->meta->throw['pages']);
        $this->assertFalse($this->connect()['connection']['needsReconnect']);
    }

    public function test_the_schedule_reads_only_businesses_with_the_module_and_warns_before_the_token_ends(): void
    {
        $this->connect();
        $other = $this->createTenant('test-other');
        $this->asTenant($other, fn () => SocialConnection::query()->create(['meta_user_id' => 'x', 'access_token' => 't', 'expires_at' => now()->addDays(30)]));

        $this->travel(6)->hours();
        $before = count($this->meta->callsOf('pages'));
        $this->artisan('platform:social:sync')->assertSuccessful()->expectsOutputToContain('test-other: module off');
        $this->assertSame($before + 1, count($this->meta->callsOf('pages')));

        // Not read again within the interval.
        $this->artisan('platform:social:sync')->assertSuccessful();
        $this->assertSame($before + 1, count($this->meta->callsOf('pages')));

        // A week before the token ends: one notice.
        $this->travelTo('2026-12-20 10:00:00');
        $this->artisan('platform:social:sync')->assertSuccessful();
        $this->artisan('platform:social:sync --force')->assertSuccessful();
        $this->assertSame(1, AdminNotification::query()->where('user_id', $this->owner->id)->where('type', 'social')->count());
        $this->assertNotNull(SocialConnection::withoutTenancy()->where('tenant_id', $this->salon->id)->value('expiry_warned_at'));
    }

    public function test_competitors_are_read_by_username_and_compared(): void
    {
        $this->connect();

        $this->postJson('/api/v1/admin/social/competitors', ['username' => 'https://www.instagram.com/Rival.am/'])
            ->assertCreated()
            ->assertJsonPath('data.username', 'rival.am')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.followers', 5000)
            ->assertJsonPath('data.stats.posts', 3)
            ->assertJsonPath('data.stats.avgLikes', 250);

        $this->postJson('/api/v1/admin/social/competitors', ['username' => '@nobody.here'])->assertCreated()->assertJsonPath('data.status', 'not_found');
        $this->postJson('/api/v1/admin/social/competitors', ['username' => 'rival.am'])->assertStatus(422)->assertJsonPath('code', 'competitor_exists');
        $this->postJson('/api/v1/admin/social/competitors', ['username' => 'salon.am'])->assertStatus(422)->assertJsonPath('code', 'competitor_own');
        $this->postJson('/api/v1/admin/social/competitors', ['username' => 'not valid!'])->assertStatus(422)->assertJsonPath('code', 'competitor_invalid');

        $comparison = $this->getJson('/api/v1/admin/social')->json('data.comparison');
        $this->assertSame(['salon.am', 'rival.am'], array_column($comparison, 'username'));
        $this->assertTrue($comparison[0]['own']);
        $this->assertEquals(5.4, $comparison[1]['engagementRate']);

        $competitor = SocialCompetitor::withoutTenancy()->where('username', 'rival.am')->first();
        $this->assertStringContainsString('ads/library', $this->getJson("/api/v1/admin/social/competitors/{$competitor->id}")->assertOk()->json('data.adLibraryUrl'));

        foreach (range(1, 8) as $i) {
            $this->asTenant($this->salon, fn () => SocialCompetitor::query()->create(['username' => "extra{$i}"]));
        }
        $this->postJson('/api/v1/admin/social/competitors', ['username' => 'one.more'])->assertStatus(422)->assertJsonPath('code', 'competitor_limit');

        $this->deleteJson("/api/v1/admin/social/competitors/{$competitor->id}")->assertNoContent();
        $this->assertNull(SocialCompetitor::withoutTenancy()->find($competitor->id));
    }

    public function test_disconnect_deletes_what_was_read_from_meta(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/social/competitors', ['username' => 'rival.am'])->assertCreated();

        $this->deleteJson('/api/v1/admin/social')->assertNoContent();

        $this->assertSame(0, SocialConnection::withoutTenancy()->count());
        $this->assertSame(0, SocialAccount::withoutTenancy()->count());
        $this->assertSame(0, SocialPost::withoutTenancy()->count());
        $competitor = SocialCompetitor::withoutTenancy()->first();
        $this->assertSame('rival.am', $competitor->username);
        $this->assertNull($competitor->followers);
        $this->assertSame('pending', $competitor->status);
        $this->assertSame([['revoke', 'user-token-c1', 'fb-user-1']], $this->meta->callsOf('revoke'));
    }

    public function test_meta_deletion_callback_deletes_the_login_in_every_business(): void
    {
        $this->connect();
        $other = $this->createTenant('test-other');
        $this->asTenant($other, fn () => SocialConnection::query()->create(['meta_user_id' => 'fb-user-1', 'access_token' => 't']));
        $this->asTenant($other, fn () => SocialConnection::query()->first());

        $this->postJson('/api/v1/meta/data-deletion', ['signed_request' => SignedRequest::make(['user_id' => 'fb-user-1'], 'wrong-secret')])->assertStatus(400);
        $this->assertSame(2, SocialConnection::withoutTenancy()->count());

        $response = $this->post('/api/v1/meta/data-deletion', ['signed_request' => SignedRequest::make(['user_id' => 'fb-user-1'], 'app-secret')])->assertOk();
        $code = $response->json('confirmation_code');
        $this->assertStringEndsWith('/api/v1/meta/data-deletion/'.$code, $response->json('url'));
        $this->assertSame(0, SocialConnection::withoutTenancy()->count());
        $this->assertSame(0, SocialPost::withoutTenancy()->count());

        $deletion = MetaDataDeletion::query()->first();
        $this->assertSame(2, $deletion->connections);
        $this->assertSame(hash('sha256', 'fb-user-1'), $deletion->meta_user_hash);
        $this->getJson('/api/v1/meta/data-deletion/'.$code)->assertOk()->assertJsonPath('status', 'completed');

        $this->post('/api/v1/meta/deauthorize', ['signed_request' => SignedRequest::make(['user_id' => 'fb-user-2'], 'app-secret')])->assertOk();
        $this->assertSame('deauthorize', MetaDataDeletion::query()->latest('id')->first()->kind);
    }

    public function test_the_assistant_reads_the_profile_posts_and_competitors(): void
    {
        $this->connect();
        $this->postJson('/api/v1/admin/social/competitors', ['username' => 'rival.am'])->assertCreated();
        $this->meta->discovery['third.am'] = $this->meta->discovery['rival.am'];

        $client = new FakeAssistantClient;
        $client->toolCalls = [
            ['social_profile', ['network' => 'instagram']],
            ['social_posts', ['sort' => 'engagement', 'limit' => 2]],
            ['social_competitors', []],
            ['social_competitors', ['username' => '@third.am']],
            ['social_competitors', ['username' => 'third.am']],
        ];
        $this->app->instance(AssistantClient::class, $client);

        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'How is our Instagram vs rivals?']]])->assertOk();

        $names = array_column($client->toolDefinitions, 'name');
        $this->assertContains('social_profile', $names);
        $this->assertContains('social_competitors', $names);
        $this->assertStringContainsString('"watchedCompetitors"', $client->system);
        $this->assertStringContainsString('rival.am', $client->system);

        [$profile, $posts, $list, $lookup] = $client->toolResults;
        $this->assertSame('@salon.am', $profile['accounts'][0]['handle']);
        $this->assertSame('m1', SocialPost::withoutTenancy()->where('permalink', $posts['posts'][0]['permalink'])->value('external_id'));
        $this->assertSame(['salon.am', 'rival.am'], array_column($list['comparison'], 'username'));
        $this->assertSame('ok', $lookup['account']['status']);
        $this->assertFalse($lookup['account']['watched']);
        // The second question about the same account is kept, not read again.
        $this->assertCount(1, array_filter($this->meta->callsOf('businessDiscovery'), fn ($c) => $c[3] === 'third.am'));
    }

    public function test_the_analytics_screen_shows_the_connected_accounts(): void
    {
        $this->getJson('/api/v1/admin/analytics/audience')->assertOk()->assertJsonPath('data.social', []);

        $this->connect();

        $social = $this->getJson('/api/v1/admin/analytics/audience?period=30')->assertOk()->json('data.social');
        $this->assertSame(['instagram', 'facebook'], array_column($social, 'network'));
        $this->assertSame('@salon.am', $social[0]['handle']);
        $this->assertSame(1200, $social[0]['reach']);
    }

    public function test_only_people_who_see_insights_open_it_and_only_settings_connect(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'moderator')->create());
        $this->getJson('/api/v1/admin/social')->assertForbidden();

        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'admin')->create());
        $this->getJson('/api/v1/admin/social')->assertForbidden();
    }

    public function test_post_stats_compare_accounts_the_same_way(): void
    {
        $stats = PostStats::of([
            ['kind' => 'REELS', 'postedAt' => '2026-10-25T06:00:00+0000', 'likes' => 100, 'comments' => 10],
            ['kind' => 'REELS', 'postedAt' => '2026-10-18T06:00:00+0000', 'likes' => 80, 'comments' => 10],
            ['kind' => 'IMAGE', 'postedAt' => '2026-10-11T15:00:00+0000', 'likes' => 20, 'comments' => 0],
        ], 1000, 'Asia/Yerevan');

        $this->assertSame(3, $stats['posts']);
        $this->assertSame(66.7, $stats['avgLikes']);
        $this->assertSame(7.33, $stats['engagementRate']);
        $this->assertSame('REELS', $stats['byKind'][0]['kind']);
        $this->assertSame('Sunday', $stats['bestWeekdays'][0]['group']);
        $this->assertSame('10:00', $stats['bestHours'][0]['group']);
        $this->assertSame(['posts' => 0], PostStats::of([], 10, 'UTC'));
    }
}

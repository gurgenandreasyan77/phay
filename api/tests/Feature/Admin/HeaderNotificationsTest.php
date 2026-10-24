<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/** The bell in the admin's header. */
class HeaderNotificationsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private function bell(): array
    {
        return $this->getJson('/api/v1/admin/notifications')->assertOk()->json();
    }

    public function test_an_order_from_the_site_notifies_everyone_who_works_with_orders(): void
    {
        $servio = Tenant::default();
        $owner = User::factory()->forTenant($servio, 'owner')->create();
        $moderator = User::factory()->forTenant($servio, 'moderator')->create();
        $other = User::factory()->forTenant($this->createTenant('test-shop'))->create();

        $this->postJson('/api/v1/leads', [
            'name' => 'Անի', 'phone' => '+374 91 123456', 'property_type' => 'apartment',
            'preferred_locale' => 'hy', 'consent' => true,
        ])->assertCreated();

        foreach ([$owner, $moderator] as $user) {
            Sanctum::actingAs($user);
            $bell = $this->bell();
            $this->assertSame(1, $bell['meta']['unread']);
            $this->assertSame('new_lead', $bell['data'][0]['type']);
            $this->assertSame('Նոր պատվեր կայքից', $bell['data'][0]['title']);
            $this->assertStringContainsString('Անի', $bell['data'][0]['body']);
            $this->assertStringStartsWith('/admin/orders/site?search=', $bell['data'][0]['url']);
        }

        // Read one, then all.
        $id = $this->bell()['data'][0]['id'];
        $this->postJson("/api/v1/admin/notifications/{$id}/read")->assertNoContent();
        $this->assertSame(0, $this->bell()['meta']['unread']);
        $this->assertTrue($this->bell()['data'][0]['read']);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/admin/notifications/read-all')->assertNoContent();
        $this->assertSame(0, $this->bell()['meta']['unread']);

        // Someone else's notification can't be touched.
        Sanctum::actingAs($other);
        $this->assertSame([], $this->bell()['data']);
        $this->postJson("/api/v1/admin/notifications/{$id}/read")->assertNotFound();
    }

    public function test_chat_messages_notify_the_other_side_and_opening_the_chat_reads_them(): void
    {
        $shop = $this->createTenant('test-shop');
        $owner = User::factory()->forTenant($shop)->create();
        $moderator = User::factory()->forTenant($shop, 'moderator')->create();
        $staff = User::factory()->superAdmin()->create();

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/admin/support/messages', ['body' => 'Բարև'])->assertCreated();

        Sanctum::actingAs($staff);
        $bell = $this->bell();
        $this->assertSame(1, $bell['meta']['unread']);
        $this->assertSame('platform', $bell['data'][0]['scope']);
        $this->assertSame("/admin/platform/support?tenant={$shop->id}", $bell['data'][0]['url']);
        $this->assertSame('Test Shop', $bell['data'][0]['tenant']['name']);

        $this->getJson("/api/v1/admin/tenants/{$shop->id}/messages")->assertOk();
        $this->assertSame(0, $this->bell()['meta']['unread']);
        $this->postJson("/api/v1/admin/tenants/{$shop->id}/messages", ['body' => 'Ողջույն'])->assertCreated();

        Sanctum::actingAs($owner);
        $bell = $this->bell();
        $this->assertSame(1, $bell['meta']['unread']);
        $this->assertSame('Նոր հաղորդագրություն Phay-ից', $bell['data'][0]['title']);
        $this->assertSame('/admin/support', $bell['data'][0]['url']);

        // The chat isn't the moderator's (orders only).
        Sanctum::actingAs($moderator);
        $this->assertSame(0, $this->bell()['meta']['unread']);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/support/messages')->assertOk();
        $this->assertSame(0, $this->bell()['meta']['unread']);
    }

    public function test_the_subscription_ending_is_in_the_bell_even_after_the_lock(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $shop = $this->createTenant('test-shop');
        $owner = User::factory()->forTenant($shop)->create();
        Sanctum::actingAs($owner);

        $this->travel(5)->days();
        $bell = $this->bell();
        $this->assertSame(1, $bell['meta']['unread']);
        $this->assertSame('subscription', $bell['data'][0]['type']);
        $this->assertSame('Բաժանորդագրությունն ավարտվում է 9 օր հետո', $bell['data'][0]['title']);
        $this->assertSame('/admin/subscription', $bell['data'][0]['url']);

        $this->travel(10)->days();
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(402);
        $bell = $this->bell();
        $this->assertSame(2, $bell['meta']['unread']);
        $this->assertSame('Բաժանորդագրությունն ավարտվել է', $bell['data'][0]['title']);
    }

    public function test_todays_orders_come_once_a_day_to_people_who_work_with_orders(): void
    {
        $this->travelTo(now()->setTimezone('Asia/Yerevan')->setTime(9, 0));
        $salon = $this->createTenant('test-salon');
        $owner = User::factory()->forTenant($salon)->create();
        Sanctum::actingAs($owner);

        // Nothing today, nothing in the bell.
        $this->assertSame([], $this->bell()['data']);

        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Այցեր',
            'columns' => [
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Ամսաթիվ', 'type' => 'datetime', 'role' => 'date'],
            ],
        ])->assertCreated()->json('data');
        [$item, $date] = array_column($table['columns'], 'key');
        $today = now('Asia/Yerevan')->toDateString();
        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$item => 'Բազմոց', $date => "{$today} 14:30"]])->assertCreated();
        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$item => 'Վաղը', $date => now('Asia/Yerevan')->addDay()->toDateString().' 10:00']])->assertCreated();
        $this->asTenant($salon, fn () => Lead::create([
            'name' => 'Անի', 'phone' => '+37491000000', 'preferred_locale' => 'hy', 'consent' => true,
            'source' => 'website', 'preferred_date' => $today,
        ]));

        $bell = $this->bell();
        $this->assertCount(1, $bell['data']);
        $this->assertSame('today_orders', $bell['data'][0]['type']);
        $this->assertSame('Այսօրվա պատվերներ՝ 2', $bell['data'][0]['title']);
        $this->assertSame('14:30 · Բազմոց; Անի', $bell['data'][0]['body']);
        $this->assertSame('/admin/calendar', $bell['data'][0]['url']);

        // Once a day.
        $this->assertCount(1, $this->bell()['data']);
        $this->travel(1)->days();
        $this->assertCount(2, $this->bell()['data']);
    }
}

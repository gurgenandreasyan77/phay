<?php

namespace Tests\Feature\Admin;

use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/** The chat between a business and the platform team. */
class SupportChatTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create(['name' => 'Anna']);
        $this->staff = User::factory()->superAdmin()->create(['name' => 'Gurgen']);
    }

    public function test_a_business_and_the_platform_talk_with_unread_counts_on_both_sides(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/admin/support/messages', ['body' => '  Բարև, օգնեք կայքի հարցով  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Բարև, օգնեք կայքի հարցով')
            ->assertJsonPath('data.fromPlatform', false)
            ->assertJsonPath('data.authorName', 'Anna');
        $this->postJson('/api/v1/admin/support/messages', ['body' => '   '])->assertUnprocessable();

        Sanctum::actingAs($this->staff);
        $this->getJson('/api/v1/admin/support/unread')->assertJsonPath('data.platform', 1)->assertJsonPath('data.business', null);
        $this->getJson('/api/v1/admin/support/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant.id', $this->shop->id)
            ->assertJsonPath('data.0.unread', 1);
        $this->assertSame(1, collect($this->getJson('/api/v1/admin/tenants')->json('data'))->firstWhere('id', $this->shop->id)['unreadMessages']);

        // Opening the conversation reads it.
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/messages")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/support/unread')->assertJsonPath('data.platform', 0);

        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/messages", ['body' => 'Իհարկե'])
            ->assertCreated()
            ->assertJsonPath('data.fromPlatform', true);

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/support/unread')->assertJsonPath('data.business', 1)->assertJsonPath('data.platform', null);
        $this->getJson('/api/v1/admin/support/messages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.body', 'Իհարկե')
            ->assertJsonPath('data.1.authorName', 'Gurgen');
        $this->getJson('/api/v1/admin/support/unread')->assertJsonPath('data.business', 0);
    }

    public function test_a_business_sees_only_its_own_conversation(): void
    {
        $other = $this->createTenant('other-shop');
        $this->asTenant($other, fn () => SupportMessage::create(['from_platform' => true, 'body' => 'Secret']));

        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/admin/support/messages')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/support/unread')->assertJsonPath('data.business', 0);
        // The platform side is closed to a business.
        $this->getJson('/api/v1/admin/support/conversations')->assertForbidden();
        $this->getJson("/api/v1/admin/tenants/{$other->id}/messages")->assertForbidden();
    }

    public function test_moderators_and_platform_staff_do_not_write_from_the_business_side(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->getJson('/api/v1/admin/support/messages')->assertForbidden();
        $this->getJson('/api/v1/admin/support/unread')->assertJsonPath('data.business', null);

        Sanctum::actingAs($this->staff);
        $this->postJson('/api/v1/admin/support/messages', ['body' => 'Hi'])->assertForbidden();

        // A platform moderator answers businesses too.
        Sanctum::actingAs(User::factory()->platformModerator()->create());
        $this->postJson("/api/v1/admin/tenants/{$this->shop->id}/messages", ['body' => 'Hi'])->assertCreated();
    }
}

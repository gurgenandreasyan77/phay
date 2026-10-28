<?php

namespace Tests\Feature\Admin;

use App\Agents\AgentCatalog;
use App\Assistant\AssistantClient;
use App\Models\AdminNotification;
use App\Models\AssistantConversation;
use App\Models\AssistantUsage;
use App\Models\BrandProfile;
use App\Models\Plan;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\TenantAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * AI agents: virtual staff a business gets like a module (the super admin
 * turns them on, their price adds to the subscription), who learn the
 * business's brand in the chat and keep their own notes, each with its own
 * role and look-ups, and nothing of one business reaching another's.
 */
class AgentsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private FakeAssistantClient $client;

    private Tenant $shop;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function turnOn(string $agent, bool $active = true): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/{$agent}", ['active' => $active])
            ->assertOk()
            ->assertJsonPath('data.key', $agent)
            ->assertJsonPath('data.active', $active);
        Sanctum::actingAs($this->owner);
    }

    public function test_the_team_lists_every_agent_and_a_new_business_has_none(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/admin/agents')->assertOk()
            ->assertJsonCount(count(AgentCatalog::all()), 'data.agents')
            ->assertJsonPath('data.agents.0.key', 'analyst')
            ->assertJsonPath('data.agents.0.persona.hy', 'Արամ')
            ->assertJsonPath('data.agents.0.active', false)
            ->assertJsonPath('data.agents.0.price', 2900)
            ->assertJsonPath('data.profile.complete', false)
            ->assertJsonPath('data.canChat', true)
            ->assertJsonPath('data.canRequest', true);

        // The admin sees the team on the employees page but doesn't chat.
        $manager = User::factory()->forTenant($this->shop, 'admin')->create();
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/admin/agents')->assertOk()->assertJsonPath('data.canChat', false);
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Բարև', 'agent' => 'analyst'])->assertForbidden();

        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->getJson('/api/v1/admin/agents')->assertForbidden();
    }

    public function test_an_agent_the_business_lacks_does_not_answer_until_the_super_admin_turns_it_on(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Գրիր գրառում', 'agent' => 'content'])
            ->assertForbidden()
            ->assertJsonPath('code', 'agent_inactive');
        $this->assertNull($this->client->system);

        // Only the super admin turns agents on.
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/content", ['active' => true])->assertForbidden();
        $this->turnOn('content');

        // The business is told in its chat and the bell.
        $this->assertTrue(SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('body', 'like', '%Լիլիթ%')->exists());
        $this->assertTrue(AdminNotification::query()->where('type', 'agent')->where('url', '/admin/assistant?agent=content')->exists());

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Գրիր գրառում', 'agent' => 'content'])
            ->assertOk()
            ->assertJsonPath('data.conversation.agent', 'content');

        // Turned off again: its conversation stops too.
        $id = AssistantConversation::withoutTenancy()->where('tenant_id', $this->shop->id)->value('id');
        $this->turnOn('content', false);
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Եվս մեկը', 'conversation_id' => $id])
            ->assertForbidden()
            ->assertJsonPath('code', 'agent_inactive');

        $this->postJson('/api/v1/admin/assistant', ['question' => 'x', 'agent' => 'nobody'])->assertUnprocessable();
    }

    public function test_a_creative_agent_learns_the_brand_first_and_has_only_its_own_look_ups(): void
    {
        $this->turnOn('content');

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Գրիր գրառում', 'agent' => 'content'])->assertOk();

        $this->assertStringContainsString('You are Lilit, the content creator', $this->client->system);
        $this->assertStringContainsString('The brand profile is empty', $this->client->system);
        $this->assertStringContainsString('get to know the business like a new employee', $this->client->system);
        // No figures snapshot for a creative agent: its prompt stays short.
        $this->assertStringNotContainsString('How the data works', $this->client->system);

        $tools = array_column($this->client->toolDefinitions, 'name');
        $this->assertContains('save_brand_profile', $tools);
        $this->assertContains('remember', $tools);
        $this->assertContains('group_rows', $tools);
        $this->assertNotContains('finance_report', $tools);
        // The social look-ups only with the social module.
        $this->assertNotContains('social_posts', $tools);
    }

    public function test_the_agent_saves_what_it_learns_and_every_agent_reads_it(): void
    {
        $this->turnOn('designer');
        $this->turnOn('reels');

        $this->client->toolCalls = [
            ['save_brand_profile', [
                'about' => 'Քիմմաքրման ծառայություն Երևանում',
                'audience' => 'Ընտանիքներ երեխաներով',
                'tone' => 'Ջերմ, պարզ',
                'colors' => [['hex' => '1f3a5f', 'name' => 'Մուգ կապույտ'], ['hex' => '#2E7D32'], ['hex' => 'not a colour']],
            ]],
            ['remember', ['note' => 'Գրառումները՝ ժամը 19:00-ին']],
        ];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Ահա մեր մասին…', 'agent' => 'designer'])->assertOk();

        $this->assertSame(['saved' => ['about', 'audience', 'tone', 'colors']], ['saved' => $this->client->toolResults[0]['saved']]);
        $profile = BrandProfile::withoutTenancy()->where('tenant_id', $this->shop->id)->sole();
        $this->assertSame('Քիմմաքրման ծառայություն Երևանում', $profile->about);
        $this->assertSame([['hex' => '#1F3A5F', 'name' => 'Մուգ կապույտ'], ['hex' => '#2E7D32', 'name' => null]], $profile->colors);
        $this->assertTrue($profile->isComplete());
        $this->assertSame($this->owner->id, $profile->updated_by);
        $this->assertSame('- Գրառումները՝ ժամը 19:00-ին', TenantAgent::withoutTenancy()->where('tenant_id', $this->shop->id)->where('agent_key', 'designer')->value('notes'));

        // Another agent of the team works with the same profile; the notes stay the designer's.
        $this->client->toolCalls = [];
        $this->client->toolResults = [];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Գաղափար ռիլսի համար', 'agent' => 'reels'])->assertOk();
        $this->assertStringContainsString('Քիմմաքրման ծառայություն Երևանում', $this->client->system);
        $this->assertStringContainsString('#1F3A5F', $this->client->system);
        $this->assertStringContainsString('Use it in everything you make', $this->client->system);
        $this->assertStringNotContainsString('19:00', $this->client->system);
        // Its teammates, to send a request to the right one.
        $this->assertStringContainsString('Mariam · Visual designer', $this->client->system);

        $this->getJson('/api/v1/admin/agents')->assertOk()
            ->assertJsonPath('data.profile.complete', true)
            ->assertJsonPath('data.profile.colors.0.hex', '#1F3A5F');

        // Nothing of it reaches another business.
        $other = $this->createTenant('other-shop');
        $this->asTenant($other, fn () => $this->assertNull(BrandProfile::query()->first()));
        Sanctum::actingAs(User::factory()->forTenant($other)->create());
        $this->getJson('/api/v1/admin/agents')->assertOk()
            ->assertJsonPath('data.profile.about', null)
            ->assertJsonPath('data.agents.4.active', false);
    }

    public function test_the_owner_edits_the_profile_and_the_notes(): void
    {
        Sanctum::actingAs($this->owner);

        $this->putJson('/api/v1/admin/agents/profile', [
            'about' => '  Ծաղիկների խանութ  ',
            'colors' => [['hex' => '#abc', 'name' => 'Բաց']],
        ])->assertOk()
            ->assertJsonPath('data.about', 'Ծաղիկների խանութ')
            ->assertJsonPath('data.colors.0.hex', '#AABBCC')
            ->assertJsonPath('data.filled', 2);

        // Fields not sent stay; an empty one clears.
        $this->putJson('/api/v1/admin/agents/profile', ['tone' => 'Ընկերական', 'about' => ''])->assertOk()
            ->assertJsonPath('data.about', null)
            ->assertJsonPath('data.tone', 'Ընկերական')
            ->assertJsonPath('data.colors.0.hex', '#AABBCC');

        $this->putJson('/api/v1/admin/agents/profile', ['colors' => [['hex' => 'red']]])->assertUnprocessable();
        $this->putJson('/api/v1/admin/agents/profile', ['about' => str_repeat('ա', BrandProfile::MAX_TEXT + 1)])->assertUnprocessable();

        // Notes only of an agent on the team.
        $this->putJson('/api/v1/admin/agents/accountant/notes', ['notes' => 'ԱԱՀ չենք վճարում'])->assertForbidden();
        $this->turnOn('accountant');
        $this->putJson('/api/v1/admin/agents/accountant/notes', ['notes' => 'ԱԱՀ չենք վճարում'])->assertOk()
            ->assertJsonPath('data.notes', 'ԱԱՀ չենք վճարում');
        $this->putJson('/api/v1/admin/agents/nobody/notes', ['notes' => 'x'])->assertNotFound();

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Շահույթ', 'agent' => 'accountant'])->assertOk();
        $this->assertStringContainsString('ԱԱՀ չենք վճարում', $this->client->system);
        // The accountant works on the figures, like the assistant.
        $this->assertStringContainsString('You are Ani, the accountant', $this->client->system);
        $this->assertStringContainsString('How the data works', $this->client->system);
        // It doesn't push the owner for the brand.
        $this->assertStringContainsString("Don't interrogate the owner", $this->client->system);
    }

    public function test_agent_conversations_are_kept_apart_and_cost_one_request_each(): void
    {
        $this->turnOn('analyst');

        $general = $this->postJson('/api/v1/admin/assistant', ['question' => 'Ընդհանուր հարց'])->assertOk()->json('data.conversation');
        $withAram = $this->postJson('/api/v1/admin/assistant', ['question' => 'Արամ, ինչպե՞ս ենք', 'agent' => 'analyst'])->assertOk()->json('data.conversation');

        // Continuing follows the conversation's agent, whatever is sent.
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Իսկ նախորդ ամի՞սը', 'conversation_id' => $withAram['id']])->assertOk();
        $this->assertStringContainsString('You are Aram', $this->client->system);
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Եվ հետո', 'conversation_id' => $general['id'], 'agent' => 'analyst'])->assertOk();
        $this->assertStringNotContainsString('You are Aram', $this->client->system);

        $this->getJson('/api/v1/admin/assistant/conversations')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $general['id']);
        $this->getJson('/api/v1/admin/assistant/conversations?agent=analyst')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.agent', 'analyst')
            ->assertJsonPath('data.0.messages', 4);
        $this->getJson('/api/v1/admin/assistant/conversations?agent=nobody')->assertNotFound();

        $this->assertSame(4, (int) AssistantUsage::withoutTenancy()->where('tenant_id', $this->shop->id)->sum('requests'));
    }

    public function test_an_agent_on_adds_its_price_unless_the_plan_includes_it(): void
    {
        Sanctum::actingAs($this->admin);
        $base = $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/subscription")->assertOk()->json('data.subscription.pricing.total');

        $this->getJson('/api/v1/admin/module-prices')->assertOk()
            ->assertJsonFragment(['key' => 'agent:reels', 'kind' => 'agent', 'price' => 3900]);
        $this->putJson('/api/v1/admin/module-prices', ['prices' => ['agent:reels' => 4500]])->assertOk()
            ->assertJsonFragment(['key' => 'agent:reels', 'price' => 4500]);
        $this->putJson('/api/v1/admin/module-prices', ['prices' => ['agent:nobody' => 1]])->assertUnprocessable();

        $this->turnOn('reels');
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/subscription")->assertOk()
            ->assertJsonPath('data.subscription.pricing.total', $base + 4500)
            ->assertJsonFragment(['key' => 'agent:reels', 'kind' => 'agent', 'active' => true, 'included' => false]);

        // A plan may include an agent, like a module.
        $plan = Plan::default();
        $this->patchJson("/api/v1/admin/plans/{$plan->id}", ['included_modules' => ['agent:reels']])->assertOk();
        $this->getJson("/api/v1/admin/tenants/{$this->shop->id}/subscription")->assertOk()
            ->assertJsonPath('data.subscription.pricing.total', $base);
        $this->patchJson("/api/v1/admin/plans/{$plan->id}", ['included_modules' => ['agent:nobody']])->assertUnprocessable();

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/admin/agents')->assertOk()
            ->assertJsonPath('data.agents.6.key', 'reels')
            ->assertJsonPath('data.agents.6.included', true)
            ->assertJsonPath('data.agents.6.price', 4500);
    }

    public function test_the_owner_asks_for_an_agent_in_the_chat_with_the_platform(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'agent', 'agent' => 'accountant'])->assertCreated();
        $this->assertTrue(SupportMessage::withoutTenancy()->where('tenant_id', $this->shop->id)->where('body', 'like', '%Անի · Հաշվապահ%')->exists());

        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'agent'])->assertUnprocessable();
        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'agent', 'agent' => 'nobody'])->assertUnprocessable();
    }
}

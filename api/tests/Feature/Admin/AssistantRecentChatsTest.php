<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\RecentChats;
use App\Models\AssistantConversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Coming back to a chat: the latest chats with what was asked and answered,
 * answers that came while the person was elsewhere (unread until opened),
 * and the question being answered right now.
 */
class AssistantRecentChatsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private FakeAssistantClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);
    }

    private function turnOnAnalyst(Tenant $tenant, User $owner): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$tenant->id}/agents/analyst", ['active' => true])->assertOk();
        Sanctum::actingAs($owner);
    }

    private function signInTo(Tenant $tenant): User
    {
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_an_answer_is_new_until_the_chat_is_opened_or_read(): void
    {
        $this->signInTo($this->createTenant('test-shop'));

        $id = $this->postJson('/api/v1/admin/assistant', ['question' => 'Որքա՞ն ենք **վաճառել** մարտին'])
            ->assertOk()
            ->json('data.conversation.id');

        $this->getJson('/api/v1/admin/assistant/recent')->assertOk()
            ->assertJsonPath('data.unread', 1)
            ->assertJsonPath('data.asking', [])
            ->assertJsonPath('data.chats.0.id', $id)
            ->assertJsonPath('data.chats.0.agent', null)
            ->assertJsonPath('data.chats.0.name', 'AI օգնական')
            // Plain words, without the markdown marks.
            ->assertJsonPath('data.chats.0.question', 'Որքա՞ն ենք վաճառել մարտին')
            ->assertJsonPath('data.chats.0.answer', 'Fake answer')
            ->assertJsonPath('data.chats.0.unread', true);

        // Seen as it came: the chat was open.
        $this->postJson("/api/v1/admin/assistant/conversations/{$id}/read")->assertNoContent();
        $this->getJson('/api/v1/admin/assistant/recent')
            ->assertJsonPath('data.unread', 0)
            ->assertJsonPath('data.chats.0.unread', false);

        // A later answer is new again; opening the chat reads it.
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Իսկ ապրիլին', 'conversation_id' => $id])->assertOk();
        $this->getJson('/api/v1/admin/assistant/recent')->assertJsonPath('data.chats.0.unread', true);
        $this->travel(1)->minutes();
        $this->getJson("/api/v1/admin/assistant/conversations/{$id}")->assertOk();
        $this->getJson('/api/v1/admin/assistant/recent')
            ->assertJsonPath('data.unread', 0)
            ->assertJsonPath('data.chats.0.question', 'Իսկ ապրիլին');
    }

    public function test_the_latest_chat_is_the_one_answered_or_opened_last(): void
    {
        $tenant = $this->createTenant('test-shop');
        $this->turnOnAnalyst($tenant, $this->signInTo($tenant));

        $this->travelTo('2026-10-27 10:00');
        $first = $this->postJson('/api/v1/admin/assistant', ['question' => 'Առաջին', 'agent' => 'analyst'])->json('data.conversation.id');
        $this->travelTo('2026-10-27 10:05');
        $second = $this->postJson('/api/v1/admin/assistant', ['question' => 'Երկրորդ'])->json('data.conversation.id');

        $this->getJson('/api/v1/admin/assistant/recent')
            ->assertJsonPath('data.chats.0.id', $second)
            ->assertJsonPath('data.chats.1.id', $first)
            ->assertJsonPath('data.chats.1.agent', 'analyst')
            ->assertJsonPath('data.chats.1.name', 'Արամ')
            ->assertJsonPath('data.unread', 2);

        // Reading the older chat again makes it the one to come back to.
        $this->travelTo('2026-10-27 10:10');
        $this->getJson("/api/v1/admin/assistant/conversations/{$first}")->assertOk();
        $this->getJson('/api/v1/admin/assistant/recent')
            ->assertJsonPath('data.chats.0.id', $first)
            ->assertJsonPath('data.chats.0.unread', false)
            ->assertJsonPath('data.unread', 1);

        // Opening doesn't reorder the history (by questions).
        $this->getJson('/api/v1/admin/assistant/conversations')->assertJsonPath('data.0.id', $second);
    }

    public function test_a_question_being_answered_is_asking_until_its_answer_is_kept(): void
    {
        $tenant = $this->createTenant('test-shop');
        $user = $this->signInTo($tenant);
        $this->turnOnAnalyst($tenant, $user);
        $seen = null;
        $this->client->whileAnswering = function () use (&$seen, $user) {
            $seen = app(RecentChats::class)->for($user);
        };

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Ինչ պատվերներ ունենք վաղը', 'agent' => 'analyst'])->assertOk();

        $this->assertCount(1, $seen['asking']);
        $this->assertSame('analyst', $seen['asking'][0]['agent']);
        $this->assertSame('Արամ', $seen['asking'][0]['name']);
        $this->assertNull($seen['asking'][0]['conversationId']);
        $this->assertSame('Ինչ պատվերներ ունենք վաղը', $seen['asking'][0]['question']);
        $this->assertArrayNotHasKey('token', $seen['asking'][0]);

        $this->getJson('/api/v1/admin/assistant/recent')->assertJsonPath('data.asking', []);
    }

    public function test_a_failed_question_stops_asking_and_saves_nothing(): void
    {
        $this->signInTo($this->createTenant('test-shop'));
        $this->client->throw = new \RuntimeException('boom');

        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/v1/admin/assistant', ['question' => 'Հարց']);
        } catch (\RuntimeException) {
        }

        $this->getJson('/api/v1/admin/assistant/recent')
            ->assertJsonPath('data.asking', [])
            ->assertJsonPath('data.chats', []);
    }

    public function test_nobody_else_sees_or_reads_a_persons_chats(): void
    {
        $tenant = $this->createTenant('test-shop');
        $this->signInTo($tenant);
        $id = $this->postJson('/api/v1/admin/assistant', ['question' => 'Գաղտնի'])->json('data.conversation.id');

        $this->signInTo($tenant);
        $this->getJson('/api/v1/admin/assistant/recent')->assertJsonPath('data.chats', [])->assertJsonPath('data.unread', 0);
        $this->postJson("/api/v1/admin/assistant/conversations/{$id}/read")->assertNotFound();
        $this->assertNull(AssistantConversation::query()->withoutGlobalScopes()->find($id)->read_at);
    }
}

<?php

namespace Tests\Feature\Admin;

use Anthropic\Core\Exceptions\APIStatusException;
use App\Assistant\AssistantClient;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Tenant;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The AI chat's history: each person's conversations in a business are kept,
 * can be read again, continued, renamed and deleted, and nobody else sees them.
 */
class AssistantHistoryTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private FakeAssistantClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);
    }

    private function signInTo(Tenant $tenant): User
    {
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_a_question_starts_a_conversation_that_can_be_continued(): void
    {
        $this->signInTo($this->createTenant('test-shop'));

        $first = $this->postJson('/api/v1/admin/assistant', ['question' => 'Որքա՞ն ենք վաճառել մարտին'])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Fake answer')
            ->assertJsonPath('data.conversation.title', 'Որքա՞ն ենք վաճառել մարտին')
            ->assertJsonPath('data.conversation.messages', 2)
            ->json('data.conversation');

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Իսկ ապրիլին', 'conversation_id' => $first['id']])
            ->assertOk()
            ->assertJsonPath('data.conversation.id', $first['id'])
            ->assertJsonPath('data.conversation.messages', 4);

        // The earlier turns come from the kept conversation.
        $this->assertSame([
            ['role' => 'user', 'content' => 'Որքա՞ն ենք վաճառել մարտին'],
            ['role' => 'assistant', 'content' => 'Fake answer'],
            ['role' => 'user', 'content' => 'Իսկ ապրիլին'],
        ], $this->client->messages);

        $this->getJson("/api/v1/admin/assistant/conversations/{$first['id']}")->assertOk()
            ->assertJsonPath('data.messages.0.content', 'Որքա՞ն ենք վաճառել մարտին')
            ->assertJsonPath('data.messages.1.role', 'assistant')
            ->assertJsonPath('data.messages.3.content', 'Fake answer')
            ->assertJsonCount(4, 'data.messages');
    }

    public function test_the_list_is_the_latest_first_and_can_be_renamed_and_deleted(): void
    {
        $this->signInTo($this->createTenant('test-shop'));

        $this->travelTo('2026-10-20 10:00');
        $old = $this->postJson('/api/v1/admin/assistant', ['question' => 'Առաջին'])->json('data.conversation.id');
        $this->travelTo('2026-10-21 10:00');
        $new = $this->postJson('/api/v1/admin/assistant', ['question' => str_repeat('Շատ երկար հարց վաճառքների մասին ', 6)])->json('data.conversation.id');

        $list = $this->getJson('/api/v1/admin/assistant/conversations')->assertOk()->json('data');
        $this->assertSame([$new, $old], array_column($list, 'id'));
        $this->assertLessThanOrEqual(81, mb_strlen($list[0]['title']));
        $this->assertStringEndsWith('…', $list[0]['title']);

        // A continued conversation goes back to the top.
        $this->travelTo('2026-10-22 10:00');
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Եվ՞', 'conversation_id' => $old])->assertOk();
        $this->assertSame([$old, $new], array_column($this->getJson('/api/v1/admin/assistant/conversations')->json('data'), 'id'));

        $this->patchJson("/api/v1/admin/assistant/conversations/{$old}", ['title' => '  Մարտի վաճառքներ '])
            ->assertOk()->assertJsonPath('data.title', 'Մարտի վաճառքներ');

        $this->deleteJson("/api/v1/admin/assistant/conversations/{$old}")->assertNoContent();
        $this->assertSame([$new], array_column($this->getJson('/api/v1/admin/assistant/conversations')->json('data'), 'id'));
        $this->assertSame(0, AssistantMessage::withoutTenancy()->where('assistant_conversation_id', $old)->count());
    }

    public function test_nobody_else_sees_or_continues_a_conversation(): void
    {
        $shop = $this->createTenant('test-shop');
        $this->signInTo($shop);
        $id = $this->postJson('/api/v1/admin/assistant', ['question' => 'Գաղտնի հարց'])->json('data.conversation.id');

        // A second owner of the same business.
        $this->signInTo($shop);
        $this->getJson('/api/v1/admin/assistant/conversations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/admin/assistant/conversations/{$id}")->assertNotFound();
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Hi', 'conversation_id' => $id])->assertNotFound();
        $this->patchJson("/api/v1/admin/assistant/conversations/{$id}", ['title' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/admin/assistant/conversations/{$id}")->assertNotFound();

        // Another business.
        $this->signInTo($this->createTenant('other-shop'));
        $this->getJson("/api/v1/admin/assistant/conversations/{$id}")->assertNotFound();

        $this->assertSame(2, AssistantMessage::withoutTenancy()->where('assistant_conversation_id', $id)->count());
    }

    public function test_a_failed_question_keeps_nothing(): void
    {
        $this->signInTo($this->createTenant('test-shop'));
        $this->client->throw = APIStatusException::from(
            new Request('POST', 'https://api.anthropic.com/v1/messages'),
            new Response(529, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'x', 'message' => 'Overloaded']])),
        );

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Hi'])->assertStatus(503);

        $this->assertSame(0, AssistantConversation::withoutTenancy()->count());
        $this->getJson('/api/v1/admin/assistant/conversations')->assertOk()->assertJsonCount(0, 'data');
    }
}

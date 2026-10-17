<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\AssistantReply;
use App\Models\AssistantUsage;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

class AdminAssistantTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private FakeAssistantClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);
    }

    private function signInTo(Tenant $tenant): void
    {
        $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id, ['role' => 'owner']);
        Sanctum::actingAs($user);
    }

    public function test_the_owner_asks_about_their_own_sales_only(): void
    {
        $shop = $this->createTenant('test-shop');
        $this->signInTo($shop);
        $this->asTenant($shop, fn () => $this->createService('cake', 'Տորթ'));
        $this->asTenant(Tenant::default(), fn () => $this->createService('sofa', 'Բազմոցի քիմմաքրում'));

        $this->postJson('/api/v1/admin/assistant', ['messages' => [
            ['role' => 'assistant', 'content' => 'Բարև'],
            ['role' => 'user', 'content' => 'Ինչպե՞ս ավելացնել վաճառքները'],
        ]])->assertOk()
            ->assertJsonPath('data.reply', 'Fake answer')
            ->assertJsonPath('data.used', 1)
            ->assertJsonPath('data.limit', 100);

        // The conversation starts with the owner's question.
        $this->assertSame([['role' => 'user', 'content' => 'Ինչպե՞ս ավելացնել վաճառքները']], $this->client->messages);
        $this->assertStringContainsString('Տորթ', $this->client->system);
        $this->assertStringNotContainsString('Բազմոցի', $this->client->system);

        $usage = $this->asTenant($shop, fn () => AssistantUsage::query()->sole());
        $this->assertSame([1, 120, 30], [$usage->requests, $usage->input_tokens, $usage->output_tokens]);
    }

    public function test_client_contacts_never_reach_the_model(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Lead::create(['name' => 'Anna Petrosyan', 'phone' => '+37491123456', 'preferred_locale' => 'hy', 'consent' => true]);

        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Hi']]])->assertOk();

        $this->assertStringNotContainsString('Anna', $this->client->system);
        $this->assertStringNotContainsString('37491123456', $this->client->system);
    }

    public function test_the_monthly_limit_stops_new_questions(): void
    {
        config(['services.anthropic.monthly_limit' => 1]);
        Sanctum::actingAs(User::factory()->create());

        $ask = fn () => $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Hi']]]);
        $ask()->assertOk();
        $ask()->assertStatus(429)->assertJsonPath('code', 'limit_reached');

        $this->getJson('/api/v1/admin/assistant')->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.used', 1)
            ->assertJsonPath('data.limit', 1);
    }

    public function test_without_an_api_key_the_assistant_says_it_is_not_connected(): void
    {
        $this->client->configured = false;
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/assistant')->assertOk()->assertJsonPath('data.configured', false);
        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Hi']]])
            ->assertStatus(503)
            ->assertJsonPath('code', 'not_configured');
    }

    public function test_the_last_message_must_be_a_question(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'assistant', 'content' => 'Hi']]])
            ->assertUnprocessable();
        $this->assertNull($this->client->system);
    }
}

class FakeAssistantClient implements AssistantClient
{
    public bool $configured = true;

    public ?string $system = null;

    public array $messages = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function reply(string $system, array $messages): AssistantReply
    {
        $this->system = $system;
        $this->messages = $messages;

        return new AssistantReply('Fake answer', 120, 30);
    }
}

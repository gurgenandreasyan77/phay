<?php

namespace Tests\Feature\Admin;

use App\Agents\AgentCatalog;
use App\Agents\AgentTools;
use App\Agents\Colleagues;
use App\Assistant\AssistantClient;
use App\Assistant\BusinessAssistant;
use App\Assistant\BusinessTools;
use App\Models\AssistantMessage;
use App\Models\AssistantUsage;
use App\Models\Employee;
use App\Models\HrChange;
use App\Models\Tenant;
use App\Models\User;
use App\Team\HrTools;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Work of a teammate the business has is handed over in the background
 * (delegate_to_colleague): the teammate does it with all its tools, what
 * it makes lands under the asker's answer, and the asker reports it. Only
 * an agent the business doesn't have is offered to request, and once it
 * is on the team the owner goes on in the same chat.
 */
class AgentDelegationTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private FakeAssistantClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-27 10:00:00');
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop, 'owner')->create();
        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);

        // Sona, Anya and Mariam on the team; no content creator.
        $this->turnOn('tables', 'hr', 'designer');
        app(TenantContext::class)->set($this->shop);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function turnOn(string ...$keys): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        foreach ($keys as $key) {
            $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/{$key}", ['active' => true])->assertOk();
        }
        Sanctum::actingAs($this->owner);
    }

    private function ask(array $calls, string $question, ?string $agent = 'tables'): array
    {
        $this->client->toolCalls = $calls;
        $this->client->toolResults = [];
        $this->client->calls = [];

        return $this->postJson('/api/v1/admin/assistant', array_filter(['question' => $question, 'agent' => $agent]))->assertOk()->json('data');
    }

    public function test_sona_hands_a_vacation_to_anya_whose_card_comes_under_her_answer(): void
    {
        Employee::create(['name' => 'Արման Սարգսյան', 'position' => 'Վարպետ']);
        $this->client->colleagueToolCalls = [[HrTools::ADD_ABSENCE, ['employee' => 'Արման', 'kind' => 'vacation', 'from' => '2026-11-03', 'to' => '2026-11-14']]];
        $this->client->colleagueAnswers = ['Պատրաստեցի քարտը՝ Արմանի արձակուրդը նոյեմբերի 3–14։'];
        $question = 'Արմանը նոյեմբերի 3-ից 14-ը արձակուրդում է, նշիր';

        $reply = $this->ask([[Colleagues::DELEGATE, ['colleague' => 'hr', 'task' => 'Գրանցիր Արմանի արձակուրդը 2026-11-03-ից 2026-11-14']]], $question);

        // Anya worked as herself, with her own tools (not just look-ups), asking and handing on no one.
        $anya = $this->client->calls[0];
        $this->assertStringContainsString('You are Anya', $anya['system']);
        $this->assertStringContainsString('hands you a piece of the owner\'s request', $anya['system']);
        $this->assertStringContainsString('You can\'t ask the owner anything now', $anya['system']);
        $this->assertContains(HrTools::ADD_ABSENCE, $anya['tools']);
        $this->assertContains(AgentTools::REMEMBER, $anya['tools']);
        foreach ([Colleagues::TOOL, Colleagues::DELEGATE, Colleagues::REFER] as $tool) {
            $this->assertNotContains($tool, $anya['tools']);
        }
        $this->assertLessThanOrEqual(45, $anya['timeBudget']);
        // She read what the owner wrote and the task.
        $this->assertStringContainsString($question, $anya['messages'][0]['content']);
        $this->assertStringContainsString('Sona · Data manager hands you this work', $anya['messages'][0]['content']);

        // Sona got the report back, to pass on.
        $result = $this->client->toolResults[0];
        $this->assertTrue($result['done']);
        $this->assertSame('Պատրաստեցի քարտը՝ Արմանի արձակուրդը նոյեմբերի 3–14։', $result['report']);
        $this->assertSame(['cards' => 1], $result['made']);

        // Under the answer: Anya's card, and above it the handover.
        $change = HrChange::query()->sole();
        $delegate = [
            'type' => 'delegate',
            'agent' => 'hr',
            'name' => 'Անյա',
            'role' => AgentCatalog::find('hr')->role()['hy'],
            'task' => 'Գրանցիր Արմանի արձակուրդը 2026-11-03-ից 2026-11-14',
            'answer' => 'Պատրաստեցի քարտը՝ Արմանի արձակուրդը նոյեմբերի 3–14։',
        ];
        $this->assertSame([['type' => 'hr', 'id' => $change->id], $delegate], $reply['actions']);
        $this->assertSame([['type' => 'hr', 'id' => $change->id], $delegate], AssistantMessage::query()->where('role', 'assistant')->sole()->actions);
        $this->assertNull($change->applied_at ?? null, 'The owner applies the card.');

        // One request of the month, with both answers' tokens.
        $usage = AssistantUsage::withoutTenancy()->where('tenant_id', $this->shop->id)->sole();
        $this->assertSame(1, $usage->requests);
        $this->assertSame(240, $usage->input_tokens);
    }

    public function test_a_teammate_given_work_sees_the_owners_files(): void
    {
        $colleagues = app(Colleagues::class);
        $picture = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'AAAA']];
        $colleagues->newQuestion([['type' => 'text', 'text' => 'Ահա բազմոցը'], $picture]);
        $tools = $colleagues->around(app(AgentTools::class)->for(AgentCatalog::find('tables'), $this->owner), AgentCatalog::find('tables'), app(BusinessAssistant::class), $this->owner);

        $tools->run(Colleagues::DELEGATE, ['colleague' => 'designer', 'task' => 'Նկար այս բազմոցով']);

        $content = $this->client->calls[0]['messages'][0]['content'];
        $this->assertContains($picture, $content);
        $this->assertStringContainsString('Նկար այս բազմոցով', end($content)['text']);
        $this->assertStringContainsString('You are Mariam', $this->client->calls[0]['system']);
    }

    public function test_an_agent_the_business_has_is_never_offered_to_request(): void
    {
        $colleagues = app(Colleagues::class);
        $colleagues->newQuestion();
        $tools = $colleagues->around(app(BusinessTools::class), null, app(BusinessAssistant::class), $this->owner);

        try {
            $tools->run(Colleagues::REFER, ['colleague' => 'designer']);
            $this->fail('Not refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString(Colleagues::DELEGATE, $e->getMessage());
        }

        // Handing work to one it lacks: nobody works, the owner gets the card to request it.
        $result = $tools->run(Colleagues::DELEGATE, ['colleague' => 'content', 'task' => 'Գրիր փոստ']);
        $this->assertFalse($result['done']);
        $this->assertFalse($result['available']);
        $this->assertStringContainsString('come back to this chat', $result['note']);
        $this->assertSame([], $this->client->calls);
    }

    public function test_at_most_two_handovers_per_answer(): void
    {
        $colleagues = app(Colleagues::class);
        $colleagues->newQuestion('Նկար և արձակուրդ');
        $tools = $colleagues->around(app(BusinessTools::class), null, app(BusinessAssistant::class), $this->owner);

        $tools->run(Colleagues::DELEGATE, ['colleague' => 'designer', 'task' => 'Նկար']);
        $tools->run(Colleagues::DELEGATE, ['colleague' => 'hr', 'task' => 'Ով է բացակա']);
        $this->expectExceptionMessage('enough');
        $tools->run(Colleagues::DELEGATE, ['colleague' => 'tables', 'task' => 'Աղյուսակ']);
    }

    public function test_once_the_agent_is_added_the_owner_goes_on_and_it_is_handed_the_work(): void
    {
        // Lilit isn't on the team: offered, not handed.
        $this->ask([], 'Գրիր փոստ Instagram-ի համար');
        $this->assertSame(['hr', 'designer'], collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::DELEGATE)['inputSchema']['properties']['colleague']['enum']);
        $this->assertContains('content', collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::REFER)['inputSchema']['properties']['colleague']['enum']);

        $this->turnOn('content');
        $this->ask([], 'Լիլիթը արդեն մեր թիմում է։ Շարունակիր։');
        $this->assertContains('content', collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::DELEGATE)['inputSchema']['properties']['colleague']['enum']);
        $this->assertNotContains('content', collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::REFER)['inputSchema']['properties']['colleague']['enum']);
    }
}

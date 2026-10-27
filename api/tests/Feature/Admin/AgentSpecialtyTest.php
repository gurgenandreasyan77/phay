<?php

namespace Tests\Feature\Admin;

use App\Agents\AgentCatalog;
use App\Agents\Colleagues;
use App\Assistant\AssistantClient;
use App\Models\AssistantMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Every agent keeps to its specialty: work of another one it doesn't do
 * but sends the owner to that agent (refer_to_colleague), a card with a
 * button to its chat, or, when the business doesn't have that agent, to
 * request it. Asked to check with an agent the business doesn't have, it
 * says the specialist isn't available instead of guessing.
 */
class AgentSpecialtyTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private FakeAssistantClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop, 'owner')->create();
        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);

        // Sona and Mariam on the team; no HR, no content creator.
        $this->turnOn('tables', 'designer');
        app(TenantContext::class)->set($this->shop);
    }

    private function turnOn(string ...$keys): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        foreach ($keys as $key) {
            $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/{$key}", ['active' => true])->assertOk();
        }
        Sanctum::actingAs($this->owner);
    }

    private function ask(array $calls, ?string $agent = 'tables', string $question = 'Գրիր պրոմպտ բազմոցի «առաջ-հետո» նկարի համար'): array
    {
        $this->client->toolCalls = $calls;
        $this->client->toolResults = [];
        $this->client->calls = [];

        return $this->postJson('/api/v1/admin/assistant', array_filter(['question' => $question, 'agent' => $agent]))->assertOk()->json('data');
    }

    public function test_every_agent_has_a_specialty(): void
    {
        foreach (AgentCatalog::all() as $agent) {
            $this->assertNotSame('', trim($agent->specialty()), $agent->key());
        }
    }

    public function test_an_agent_is_told_to_keep_to_its_specialty_and_knows_who_does_the_rest(): void
    {
        $this->ask([]);

        $system = $this->client->system;
        $this->assertStringContainsString('Your specialty: '.AgentCatalog::find('tables')->specialty(), $system);
        $this->assertStringContainsString('Answer only within it', $system);
        // The whole platform's team, with what this business has.
        $this->assertMatchesRegularExpression('/Mariam · Visual designer.*image-generation.*On this business\'s team\./u', $system);
        $this->assertMatchesRegularExpression('/Anya · HR recruiter.*NOT on this business\'s team/u', $system);
        $this->assertMatchesRegularExpression('/Lilit .*NOT on this business\'s team/u', $system);
        $this->assertStringNotContainsString('Sona · Data manager', $system);
        // Sona: pictures and prompts are never hers.
        $this->assertStringContainsString('Pictures, image prompts, post texts and scripts are never yours', $system);

        $refer = collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::REFER);
        $this->assertNotNull($refer);
        $this->assertSame(
            array_values(array_diff(AgentCatalog::keys(), ['tables'])),
            $refer['inputSchema']['properties']['colleague']['enum'],
        );
        // Only teammates the business has can be asked.
        $this->assertSame(['designer'], collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::TOOL)['inputSchema']['properties']['colleague']['enum']);
    }

    public function test_sona_sends_an_image_prompt_to_mariam_with_a_button_to_her_chat(): void
    {
        $reply = $this->ask([[Colleagues::REFER, ['colleague' => 'designer', 'task' => 'Բազմոցի «առաջ-հետո» նկար']]]);

        $this->assertTrue($this->client->toolResults[0]['available']);
        $this->assertSame('Mariam · Visual designer', $this->client->toolResults[0]['colleague']);

        $card = ['type' => 'refer', 'agent' => 'designer', 'name' => 'Մարիամ', 'role' => 'Վիզուալ դիզայներ', 'task' => 'Բազմոցի «առաջ-հետո» նկար'];
        $this->assertSame([$card], $reply['actions']);
        $this->assertSame([$card], AssistantMessage::query()->where('role', 'assistant')->sole()->actions);
    }

    public function test_an_agent_the_business_lacks_is_offered_to_request(): void
    {
        $reply = $this->ask([[Colleagues::REFER, ['colleague' => 'content']]], question: 'Գրիր փոստ Instagram-ի համար');

        $result = $this->client->toolResults[0];
        $this->assertFalse($result['available']);
        $this->assertStringContainsString('not available', $result['note']);
        $this->assertStringContainsString('monthly fee', $result['note']);
        $this->assertSame([['type' => 'refer', 'agent' => 'content', 'name' => 'Լիլիթ', 'role' => AgentCatalog::find('content')->role()['hy']]], $reply['actions']);

        // The card's button asks Phay for her, in the business's chat.
        $this->postJson('/api/v1/admin/subscription/request', ['kind' => 'agent', 'agent' => 'content'])->assertSuccessful();
    }

    public function test_asked_to_check_with_hr_the_business_lacks_sona_says_it_is_not_available(): void
    {
        $question = 'Ավելացրու պատվեր Արմանի համար և ճշտիր Անյայից՝ ազատ է արդյոք';
        $this->ask([], question: $question);
        $this->assertStringContainsString('the HR person is not on your team', $this->client->system);
        $this->assertStringContainsString("say the HR specialist isn't available", $this->client->system);

        // Even if she tries to ask Anya, nobody is asked: the owner gets the card to request HR.
        $reply = $this->ask([[Colleagues::TOOL, ['colleague' => 'hr', 'question' => 'Is Arman free on 2026-11-05?']]], question: $question);

        $this->assertNull($this->client->toolResults[0]['answer']);
        $this->assertFalse($this->client->toolResults[0]['available']);
        $this->assertCount(1, $this->client->calls, 'No teammate was asked.');
        $this->assertSame('refer', $reply['actions'][0]['type']);
        $this->assertSame('hr', $reply['actions'][0]['agent']);
    }

    public function test_the_business_assistant_keeps_the_figures_and_routes_the_rest(): void
    {
        $reply = $this->ask([[Colleagues::REFER, ['colleague' => 'designer']]], agent: null);

        $this->assertStringContainsString('Your own work is the business\'s figures', $this->client->system);
        $this->assertStringContainsString('The AI team of the platform', $this->client->system);
        $this->assertSame(AgentCatalog::keys(), collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::REFER)['inputSchema']['properties']['colleague']['enum']);
        $this->assertSame('designer', $reply['actions'][0]['agent']);
    }

    public function test_a_consulted_agent_says_when_a_question_is_not_its_work(): void
    {
        $this->client->colleagueAnswers = ['Սա իմ գործը չէ։'];
        $this->ask([[Colleagues::TOOL, ['colleague' => 'designer', 'question' => 'How many orders in October?']]]);

        $this->assertStringContainsString('If the question is not about your specialty ('.AgentCatalog::find('designer')->specialty().')', $this->client->calls[0]['system']);
    }
}

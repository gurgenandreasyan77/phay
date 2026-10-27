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
use App\Models\DataTable;
use App\Models\DataTableRow;
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
 * The AI team works together: an agent asks a teammate (ask_colleague),
 * e.g. Sona asks Anya whether the performer of an order is away. The
 * teammate answers from its own look-ups, read-only and asking no one;
 * the chat shows who was consulted, and it is one request of the month.
 */
class AgentTeamworkTest extends TestCase
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

        $this->turnOn('tables', 'hr');
        Sanctum::actingAs($this->owner);
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

    private function ask(array $calls, string $agent = 'tables'): array
    {
        $this->client->toolCalls = $calls;
        $this->client->toolResults = [];
        $this->client->calls = [];

        return $this->postJson('/api/v1/admin/assistant', ['question' => 'Ավելացրու պատվեր Արմանի համար նոյեմբերի 5-ին', 'agent' => $agent])->assertOk()->json('data');
    }

    public function test_an_agent_knows_its_teammates_and_can_ask_them(): void
    {
        $this->ask([]);

        $ask = collect($this->client->toolDefinitions)->firstWhere('name', Colleagues::TOOL);
        $this->assertNotNull($ask);
        $this->assertSame(['hr'], $ask['inputSchema']['properties']['colleague']['enum']);
        $this->assertStringContainsString('Anya', $ask['description']);
        $this->assertStringContainsString('Your teammates in this business', $this->client->system);
        $this->assertStringContainsString('ask_colleague', $this->client->system);
        // Sona is told to check the performer with the HR person.
        $this->assertStringContainsString('whether that performer is away', $this->client->system);
    }

    public function test_alone_on_the_team_there_is_no_one_to_ask(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/hr", ['active' => false])->assertOk();
        Sanctum::actingAs($this->owner);

        $this->ask([]);

        $this->assertNotContains(Colleagues::TOOL, array_column($this->client->toolDefinitions, 'name'));
        $this->assertStringNotContainsString('Your teammates', $this->client->system);
    }

    public function test_sona_asks_anya_who_answers_from_her_records_and_the_chat_shows_it(): void
    {
        $arman = Employee::create(['name' => 'Արման Սարգսյան', 'position' => 'Վարպետ']);
        $this->postJson('/api/v1/admin/team/absences', [
            'employeeId' => $arman->id, 'kind' => 'vacation', 'from' => '2026-11-03', 'to' => '2026-11-14',
        ])->assertCreated();
        $this->client->colleagueToolCalls = [['list_absences', ['from' => '2026-11-05', 'to' => '2026-11-05']]];
        $this->client->colleagueAnswers = ['Արմանը արձակուրդում է նոյեմբերի 3–14-ը։'];

        $reply = $this->ask([[Colleagues::TOOL, ['colleague' => 'hr', 'question' => 'Is Arman away on 2026-11-05?']]]);

        // Sona got Anya's answer.
        $this->assertSame(['colleague' => 'Anya · HR recruiter', 'answer' => 'Արմանը արձակուրդում է նոյեմբերի 3–14-ը։'], $this->client->toolResults[0]);

        // Anya answered as herself, to her teammate, with only her look-ups.
        $anya = $this->client->calls[0];
        $this->assertStringContainsString('You are Anya', $anya['system']);
        $this->assertStringContainsString('your teammate Sona', $anya['system']);
        $this->assertSame([['role' => 'user', 'content' => 'Is Arman away on 2026-11-05?']], $anya['messages']);
        $this->assertContains('list_absences', $anya['tools']);
        $this->assertContains('team_overview', $anya['tools']);
        foreach (['add_absence', 'save_employee', 'hire_candidate', 'save_brand_profile', 'remember', Colleagues::TOOL] as $tool) {
            $this->assertNotContains($tool, $anya['tools']);
        }
        $this->assertLessThanOrEqual(30, $anya['timeBudget']);

        // The card under the answer: who was consulted, the question, the answer.
        $consult = [
            'type' => 'consult',
            'agent' => 'hr',
            'name' => 'Անյա',
            'role' => AgentCatalog::find('hr')->role()['hy'],
            'question' => 'Is Arman away on 2026-11-05?',
            'answer' => 'Արմանը արձակուրդում է նոյեմբերի 3–14-ը։',
        ];
        $this->assertSame([$consult], $reply['actions']);
        $this->assertSame([$consult], AssistantMessage::query()->where('role', 'assistant')->sole()->actions);

        // One request of the month, with both answers' tokens.
        $usage = AssistantUsage::withoutTenancy()->where('tenant_id', $this->shop->id)->sole();
        $this->assertSame(1, $usage->requests);
        $this->assertSame(240, $usage->input_tokens);
        $this->assertSame(60, $usage->output_tokens);
    }

    public function test_a_consulted_teammate_only_looks_things_up(): void
    {
        $tools = app(AgentTools::class)->for(AgentCatalog::find('hr'), $this->owner)->readOnly();

        $this->assertSame([], array_diff(array_column($tools->definitions(), 'name'), AgentTools::READS));
        $this->assertArrayHasKey('employees', $tools->run(HrTools::TEAM, []));

        $this->expectException(InvalidArgumentException::class);
        try {
            $tools->run(HrTools::ADD_ABSENCE, ['employee' => 'Արման', 'kind' => 'vacation', 'from' => '2026-11-03', 'to' => '2026-11-04']);
        } finally {
            $this->assertSame(0, HrChange::query()->count());
        }
    }

    public function test_at_most_two_teammates_per_answer_and_only_the_business_own(): void
    {
        $colleagues = app(Colleagues::class);
        $colleagues->newQuestion();
        $tools = $colleagues->around(app(BusinessTools::class), null, app(BusinessAssistant::class), $this->owner);

        $this->assertSame(['tables', 'hr'], collect($tools->definitions())->firstWhere('name', Colleagues::TOOL)['inputSchema']['properties']['colleague']['enum']);

        $refused = function (array $input) use ($tools) {
            try {
                $tools->run(Colleagues::TOOL, $input);
            } catch (InvalidArgumentException $e) {
                return $e->getMessage();
            }
            $this->fail('Not refused.');
        };
        // The analyst isn't on this team: nobody is asked, the owner gets the card to request him.
        $missing = $tools->run(Colleagues::TOOL, ['colleague' => 'analyst', 'question' => 'Revenue?']);
        $this->assertFalse($missing['available']);
        $this->assertSame([], $this->client->calls);
        $this->assertStringContainsString('No such teammate', $refused(['colleague' => 'nobody', 'question' => 'Revenue?']));

        $tools->run(Colleagues::TOOL, ['colleague' => 'hr', 'question' => 'Who is away today?']);
        $tools->run(Colleagues::TOOL, ['colleague' => 'tables', 'question' => 'Which tables are there?']);
        $this->assertStringContainsString('enough', $refused(['colleague' => 'hr', 'question' => 'And tomorrow?']));
        // The assistant asking: the teammate is told who asks.
        $this->assertStringContainsString('your teammate the business assistant', $this->client->calls[0]['system']);
    }

    public function test_the_business_assistant_may_ask_the_team_too(): void
    {
        $this->client->toolCalls = [];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Ո՞վ է բացակա այսօր'])->assertOk();

        $this->assertContains(Colleagues::TOOL, array_column($this->client->toolDefinitions, 'name'));
        $this->assertStringContainsString('The AI team of the platform', $this->client->system);
    }

    private function ordersTable(): DataTable
    {
        $id = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Պատվերներ',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
                ['label' => 'Կատարող', 'type' => 'text', 'role' => 'performer'],
            ],
        ])->assertCreated()->json('data.id');

        return DataTable::query()->findOrFail($id);
    }

    public function test_sona_saves_every_detail_adding_the_columns_the_table_lacks(): void
    {
        $table = $this->ordersTable();

        $reply = $this->ask([['add_rows', [
            'table' => $table->id,
            'new_columns' => [['label' => 'Հասցե', 'type' => 'text'], ['label' => 'Հեռախոս', 'type' => 'text']],
            'rows' => [['Ամսաթիվ' => '2026-11-20', 'Ծառայություն' => 'Բազմոցի մաքրում', 'Գումար' => 9900, 'Հասցե' => 'Կոմիտաս 5, բն. 12', 'Հեռախոս' => '055 33 76 33']],
        ]]]);

        $card = $this->getJson("/api/v1/admin/table-changes/{$reply['actions'][0]['id']}")->assertOk()->json('data');
        $this->assertSame(['Նոր սյունակ «Հասցե»՝ տեքստ, հասցե', 'Նոր սյունակ «Հեռախոս»՝ տեքստ, հեռախոս'], $card['changes']);
        $this->assertCount(4, $table->fresh()->columns);
        $this->assertSame(0, $table->rows()->count());

        $this->postJson("/api/v1/admin/table-changes/{$card['id']}/apply")->assertOk();
        $table->refresh();
        $this->assertSame(['Ամսաթիվ', 'Ծառայություն', 'Գումար', 'Կատարող', 'Հասցե', 'Հեռախոս'], array_column($table->columns, 'label'));
        $labels = array_column($table->columns, 'key', 'label');
        $row = $table->rows()->sole();
        $this->assertSame('Կոմիտաս 5, բն. 12', $row->data[$labels['Հասցե']]);
        $this->assertSame('055 33 76 33', $row->data[$labels['Հեռախոս']]);

        // Undo takes the row and the columns it added.
        $this->postJson("/api/v1/admin/table-changes/{$card['id']}/undo")->assertOk();
        $this->assertSame(0, $table->rows()->count());
        $this->assertCount(4, $table->fresh()->columns);
    }

    public function test_a_card_warns_when_the_performer_is_away_that_day(): void
    {
        $table = $this->ordersTable();
        $arman = Employee::create(['name' => 'Արման Սարգսյան', 'position' => 'Վարպետ']);
        Employee::create(['name' => 'Գոռ Հակոբյան', 'position' => 'Վարպետ']);
        $vardan = Employee::create(['name' => 'Վարդան Պետրոսյան', 'position' => 'Վարպետ']);
        $this->postJson('/api/v1/admin/team/absences', ['employeeId' => $vardan->id, 'kind' => 'sick', 'from' => '2026-11-05', 'to' => '2026-11-05'])->assertCreated();
        $this->postJson('/api/v1/admin/team/absences', ['employeeId' => $arman->id, 'kind' => 'vacation', 'from' => '2026-11-03', 'to' => '2026-11-14'])->assertCreated();

        $reply = $this->ask([['add_rows', ['table' => $table->id, 'rows' => [
            ['Ամսաթիվ' => '2026-11-05', 'Ծառայություն' => 'Բազմոցի մաքրում', 'Կատարող' => 'Արման'],
            ['Ամսաթիվ' => '2026-11-20', 'Ծառայություն' => 'Ներքնակի մաքրում', 'Կատարող' => 'Արման'],
        ]]]]);

        $away = ['Արման Սարգսյան 05.11.2026-ին բացակա է՝ արձակուրդ (03.11.2026–14.11.2026)։ Այդ օրը ազատ են՝ Գոռ Հակոբյան։'];
        $this->assertSame($away, $this->client->toolResults[0]['performersAway']);
        $id = $reply['actions'][0]['id'];
        $this->assertSame($away, $this->getJson("/api/v1/admin/table-changes/{$id}")->json('data.warnings'));

        // The owner keeps him anyway: once applied, no more warning.
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertOk();
        $this->assertSame([], $this->getJson("/api/v1/admin/table-changes/{$id}")->json('data.warnings'));
        $this->assertSame(2, DataTableRow::query()->where('data_table_id', $table->id)->count());
    }
}

<?php

namespace Tests\Feature\Admin;

use Anthropic\Core\Exceptions\APIStatusException;
use App\Assistant\AssistantClient;
use App\Assistant\BusinessTools;
use App\Models\AssistantUsage;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
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
        $shop = $this->withServices($this->createTenant('test-shop'));
        $this->signInTo($shop);
        $this->asTenant($shop, fn () => $this->createService('cake', 'Տորթ'));
        $this->asTenant(Tenant::default(), fn () => $this->createService('sofa', 'Բազմոցի քիմմաքրում'));

        $this->postJson('/api/v1/admin/assistant', ['messages' => [
            ['role' => 'assistant', 'content' => 'Բարև'],
            ['role' => 'user', 'content' => 'Ինչպե՞ս ավելացնել վաճառքները'],
        ]])->assertOk()
            ->assertJsonPath('data.reply', 'Fake answer')
            ->assertJsonPath('data.used', 1)
            ->assertJsonPath('data.limit', 30)
            ->assertJsonPath('data.remaining', 29);

        // The conversation starts with the owner's question.
        $this->assertSame([['role' => 'user', 'content' => 'Ինչպե՞ս ավելացնել վաճառքները']], $this->client->messages);
        $this->assertStringContainsString('Տորթ', $this->client->system);
        $this->assertStringNotContainsString('Բազմոցի', $this->client->system);

        $usage = AssistantUsage::withoutTenancy()->where('tenant_id', $shop->id)->sole();
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

    public function test_the_plan_limit_stops_new_questions(): void
    {
        $shop = $this->createTenant('test-shop');
        Plan::default()->update(['assistant_requests' => 1]);
        $this->signInTo($shop);

        $ask = fn () => $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Hi']]]);
        $ask()->assertOk()->assertJsonPath('data.remaining', 0);
        $ask()->assertStatus(429)
            ->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('usage.remaining', 0);

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

    public function test_the_assistant_looks_up_its_own_rows_without_buyer_contacts(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        $shop = $this->createTenant('test-shop');
        $this->signInTo($shop);
        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Instagram',
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text'],
                ['label' => 'Քանակ', 'type' => 'number'],
                ['label' => 'Գին', 'type' => 'money'],
                ['label' => 'Ամսաթիվ', 'type' => 'date'],
                ['label' => 'Հեռախոս', 'type' => 'text'],
            ],
        ])->assertCreated()->json('data');
        foreach ([
            ['Մոմ', 2, 4500, '2026-10-16', '+37491111111'],
            ['Մոմ', 1, 4500, '2026-10-10', '+37491111111'],
            ['Օճառ', 3, 1000, '2026-10-01', null],
            ['Օճառ', 1, 1000, '2026-09-10', null],
        ] as $row) {
            $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => array_combine(array_column($table['columns'], 'key'), $row)])->assertCreated();
        }

        $this->client->toolCalls = [
            ['list_tables', []],
            ['find_rows', ['from' => '2026-10-01', 'to' => '2026-10-31', 'search' => 'մոմ']],
            ['group_rows', ['from' => '2026-09-01', 'to' => '2026-10-31', 'by' => 'month']],
            ['group_rows', ['from' => '2026-09-01', 'to' => '2026-10-31', 'by' => 'Ապրանք']],
            ['finance_report', ['by' => 'month', 'count' => 2]],
            ['site_orders', ['from' => '2026-10-01']],
        ];
        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Որքա՞ն մոմ ենք վաճառել հոկտեմբերին']]])->assertOk();

        [$tables, $found, $byMonth, $byItem, $finance, $site] = $this->client->toolResults;
        $this->assertCount(5, $this->client->toolDefinitions);
        $this->assertSame(['Ապրանք', 'Քանակ', 'Գին', 'Ամսաթիվ'], array_column($tables['tables'][0]['columns'], 'label'));
        $this->assertSame(2, $found['matched']);
        $this->assertSame(['Ապրանք' => 'Մոմ', 'Քանակ' => 2, 'Գին' => 4500], $found['rows'][0]['values']);
        $this->assertSame([['group' => '2026-09', 'rows' => 1], ['group' => '2026-10', 'rows' => 3]], array_map(fn ($g) => ['group' => $g['group'], 'rows' => $g['rows']], $byMonth['groups']));
        $this->assertSame('Մոմ', $byItem['groups'][0]['group']);
        $this->assertCount(2, $finance['periods']);
        $this->assertSame(0, $site['orders']);

        // Buyers' phones never come back from a look-up.
        $this->assertStringNotContainsString('37491111111', json_encode($this->client->toolResults, JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('find_rows', $this->client->system);

        // Another business sees none of it.
        $this->signInTo($this->createTenant('other-shop'));
        $this->client->toolCalls = [['find_rows', []]];
        $this->client->toolResults = [];
        $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Hi']]])->assertOk();
        $this->assertSame(0, $this->client->toolResults[0]['matched']);

        Carbon::setTestNow();
    }

    public function test_a_wrong_look_up_is_an_error_for_the_model(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(BusinessTools::class)->run('group_rows', ['from' => '2026-10-01', 'to' => '2026-10-31', 'by' => 'Հեռախոս']);
    }

    public function test_a_failed_api_call_says_what_is_wrong_and_costs_no_request(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $fail = fn (int $status, string $message) => APIStatusException::from(
            new Request('POST', 'https://api.anthropic.com/v1/messages'),
            new Response($status, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'x', 'message' => $message]])),
        );
        $ask = fn () => $this->postJson('/api/v1/admin/assistant', ['messages' => [['role' => 'user', 'content' => 'Hi']]]);

        $this->client->throw = $fail(401, 'invalid x-api-key');
        $ask()->assertStatus(502)->assertJsonPath('code', 'invalid_key')->assertJsonPath('detail', 'invalid x-api-key');

        $this->client->throw = $fail(400, 'Your credit balance is too low to access the Anthropic API.');
        $ask()->assertStatus(502)->assertJsonPath('code', 'no_credit');

        $this->client->throw = $fail(404, 'model: claude-x');
        $ask()->assertStatus(502)->assertJsonPath('code', 'bad_model');

        $this->client->throw = $fail(529, 'Overloaded');
        $ask()->assertStatus(503)->assertJsonPath('code', 'unavailable');

        $this->getJson('/api/v1/admin/assistant')->assertJsonPath('data.used', 0);
    }
}

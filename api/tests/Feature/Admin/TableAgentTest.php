<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\TableEditTools;
use App\DataTables\Changes\ChangeRefused;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\TableChange;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Sona, the table agent: she prepares new tables, column changes, rows
 * added, edited or deleted, tables deleted, and the owner applies each one
 * from its card (nothing is written before), discards it, or undoes it while
 * nobody touched what it changed. The accountant no longer reshapes tables.
 */
class TableAgentTest extends TestCase
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

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/tables", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
        app(TenantContext::class)->set($this->shop);
    }

    private function sales(): DataTable
    {
        $id = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Վաճառքներ',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Ապրանք', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
                ['label' => 'Հաճախորդ', 'type' => 'text', 'role' => 'customer'],
            ],
        ])->assertCreated()->json('data.id');

        $table = DataTable::query()->findOrFail($id);
        [$date, $item, $amount, $customer] = array_column($table->columns, 'key');
        foreach ([['2026-10-01', 'Մոմ', 1000, 'Աննա'], ['2026-10-02', 'Օճառ', 700, 'Գոռ'], ['2026-10-03', 'Մոմ', 1500, 'Լիլիթ']] as [$at, $what, $money, $who]) {
            DataTableRow::create(['data_table_id' => $table->id, 'data' => [$date => $at, $item => $what, $amount => $money, $customer => $who]]);
        }

        return $table;
    }

    /** Asks Sona with the given tool calls; returns the answer. */
    private function ask(array $calls, string $question = 'Արա'): array
    {
        $this->client->toolCalls = $calls;
        $this->client->toolResults = [];

        return $this->postJson('/api/v1/admin/assistant', ['question' => $question, 'agent' => 'tables'])->assertOk()->json('data');
    }

    public function test_sona_has_the_table_tools_and_the_accountant_no_longer_reshapes(): void
    {
        $this->ask([]);
        $names = array_column($this->client->toolDefinitions, 'name');
        foreach (['table_rows', 'create_table', 'edit_table', 'add_rows', 'update_rows', 'delete_rows', 'delete_tables', 'reshape_tables', 'download_file'] as $tool) {
            $this->assertContains($tool, $names);
        }
        $this->assertStringContainsString('You are Sona', $this->client->system);
        $this->assertStringContainsString('create_table, edit_table', $this->client->system);

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/accountant", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Բարև', 'agent' => 'accountant'])->assertOk();
        $names = array_column($this->client->toolDefinitions, 'name');
        $this->assertNotContains('reshape_tables', $names);
        $this->assertNotContains('add_rows', $names);
        $this->assertContains('download_file', $names);
    }

    public function test_rows_are_read_with_ids_and_without_the_buyers(): void
    {
        $table = $this->sales();
        $this->ask([['table_rows', ['table' => $table->id, 'search' => 'մոմ']]]);
        $result = $this->client->toolResults[0];

        $this->assertSame(2, $result['matched']);
        $this->assertSame(['Ամսաթիվ' => '2026-10-03', 'Ապրանք' => 'Մոմ', 'Գումար' => 1500], $result['rows'][0]['values']);
        $this->assertIsInt($result['rows'][0]['id']);
        $this->assertTrue(collect($result['columns'])->firstWhere('label', 'Հաճախորդ')['hidden']);

        // A buyer's name finds nothing.
        $this->ask([['table_rows', ['table' => $table->id, 'search' => 'Աննա']]]);
        $this->assertSame(0, $this->client->toolResults[0]['matched']);
    }

    public function test_added_rows_wait_for_the_press_and_can_be_undone(): void
    {
        $table = $this->sales();
        $reply = $this->ask([['add_rows', ['table' => $table->id, 'rows' => [
            ['Ամսաթիվ' => '27.10.2026', 'Ապրանք' => 'Մոմ', 'Գումար' => '2 500 դր.'],
            ['ապրանք' => 'Օճառ', 'Գումար' => 900],
        ]]]]);

        $this->assertSame('change', $reply['actions'][0]['type']);
        $id = $reply['actions'][0]['id'];
        $this->assertSame(3, $table->rows()->count());
        $this->assertSame('Ավելացնել 2 տող «Վաճառքներ»-ում', $this->client->toolResults[0]['card']['title']);

        $card = $this->getJson("/api/v1/admin/table-changes/{$id}")->assertOk()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.rowsTotal', 2)
            ->json('data');
        [$date, $item, $amount] = array_column($table->columns, 'key');
        $this->assertSame(['2026-10-27', 'Մոմ', 2500], [$card['rows'][0]['values'][$date], $card['rows'][0]['values'][$item], $card['rows'][0]['values'][$amount]]);
        // A row without a date happened today.
        $this->assertSame(now()->toDateString(), $card['rows'][1]['values'][$date]);

        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertOk()->assertJsonPath('data.status', 'applied');
        $this->assertSame(5, $table->rows()->count());
        $this->assertSame(1000.0 + 700 + 1500 + 2500 + 900, (float) $table->rows()->sum('amount'));
        // Twice is once.
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertUnprocessable()->assertJsonPath('code', 'change_refused');
        $this->assertSame(5, $table->rows()->count());

        $this->postJson("/api/v1/admin/table-changes/{$id}/undo")->assertOk()->assertJsonPath('data.status', 'undone');
        $this->assertSame(3, $table->rows()->count());
    }

    public function test_wrong_values_are_refused_before_anything_is_planned(): void
    {
        $table = $this->sales();
        try {
            app(TableEditTools::class)->run('add_rows', ['table' => $table->id, 'rows' => [['Ամսաթիվ' => 'երեկ', 'Գույն' => 'կարմիր']]]);
            $this->fail('A wrong row was planned.');
        } catch (ChangeRefused $e) {
            $this->assertStringContainsString('«Գույն» սյունակ չկա', $e->getMessage());
            $this->assertStringContainsString('Ամսաթիվը չի ճանաչվել', $e->getMessage());
        }
        $this->assertSame(0, TableChange::query()->count());
    }

    public function test_edited_rows_show_old_and_new_values_and_stale_cards_are_refused(): void
    {
        $table = $this->sales();
        [$date, $item, $amount] = array_column($table->columns, 'key');
        $row = $table->rows()->where("data->{$item}", 'Օճառ')->first();

        $reply = $this->ask([['update_rows', ['table' => $table->id, 'rows' => [['id' => $row->id, 'values' => ['Գումար' => 750]]]]]]);
        $id = $reply['actions'][0]['id'];
        $this->getJson("/api/v1/admin/table-changes/{$id}")->assertOk()
            ->assertJsonPath('data.rows.0.before.'.$amount, 700)
            ->assertJsonPath('data.rows.0.values.'.$amount, 750)
            ->assertJsonPath('data.rows.0.changed', [$amount]);

        // Someone changed the row in the meantime: the card no longer shows the truth.
        $this->patchJson("/api/v1/admin/data-tables/{$table->id}/rows/{$row->id}", ['values' => [...$row->data, $amount => 710]])->assertOk();
        $this->getJson("/api/v1/admin/table-changes/{$id}")->assertJsonPath('data.stale', 'Այս տողերն արդեն փոխվել կամ ջնջվել են։ Խնդրեք նորից։');
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertUnprocessable();
        $this->assertSame(710, $row->fresh()->data[$amount]);

        $reply = $this->ask([['update_rows', ['table' => $table->id, 'rows' => [['id' => $row->id, 'values' => ['Գումար' => 750]]]]]]);
        $id = $reply['actions'][0]['id'];
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertOk();
        $this->assertSame(750.0, $row->fresh()->amount);
        $this->postJson("/api/v1/admin/table-changes/{$id}/undo")->assertOk();
        $this->assertSame(710.0, $row->fresh()->amount);
    }

    public function test_deleted_rows_come_back_on_undo(): void
    {
        $table = $this->sales();
        $ids = $table->rows()->orderBy('id')->limit(2)->pluck('id')->all();

        $reply = $this->ask([['delete_rows', ['table' => $table->id, 'ids' => $ids]]]);
        $id = $reply['actions'][0]['id'];
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertOk();
        $this->assertSame(1, $table->rows()->count());
        $this->postJson("/api/v1/admin/table-changes/{$id}/undo")->assertOk();
        $this->assertSame(3, $table->rows()->count());
        $this->assertSame(3200.0, (float) $table->rows()->sum('amount'));
    }

    public function test_a_new_table_with_rows_and_its_undo(): void
    {
        $reply = $this->ask([['create_table', [
            'section' => 'expenses',
            'name' => 'Ծախսեր Հոկտեմբեր 2026',
            'period' => 'month',
            'period_date' => '2026-10-15',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Ինչի համար', 'type' => 'text'],
                ['label' => 'Գումար', 'type' => 'money'],
            ],
            'rows' => [['Ամսաթիվ' => '2026-10-05', 'Ինչի համար' => 'Վարձ', 'Գումար' => 150000]],
        ]]]);
        $id = $reply['actions'][0]['id'];

        $card = $this->getJson("/api/v1/admin/table-changes/{$id}")->assertOk()->json('data');
        $this->assertSame('Նոր աղյուսակ՝ «Ծախսեր Հոկտեմբեր 2026», 1 տողով', $card['title']);
        $this->assertContains('Բաժին՝ Ծախսեր', $card['changes']);
        $this->assertContains('Սյունակ «Գումար»՝ գումար, գումար', $card['changes']);
        $this->assertSame(0, DataTable::query()->count());

        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertOk();
        $table = DataTable::query()->sole();
        $this->assertSame('month', $table->period->value);
        $this->assertSame('2026-10-01', $table->period_start->toDateString());
        $this->assertSame(150000.0, (float) $table->rows()->sum('amount'));

        $this->postJson("/api/v1/admin/table-changes/{$id}/undo")->assertOk();
        $this->assertSame(0, DataTable::query()->count());
    }

    public function test_columns_are_renamed_given_meanings_added_and_removed(): void
    {
        $table = $this->sales();
        $reply = $this->ask([['edit_table', [
            'table' => $table->id,
            'name' => 'Վաճառքներ 2026',
            'rename_columns' => [['column' => 'Ապրանք', 'label' => 'Ապրանքի անուն']],
            'add_columns' => [['label' => 'Ինքնարժեք', 'type' => 'money', 'role' => 'cost']],
            'set_roles' => [['column' => 'Գումար', 'role' => null]],
            'remove_columns' => ['Հաճախորդ'],
        ]]]);
        $id = $reply['actions'][0]['id'];

        $changes = $this->getJson("/api/v1/admin/table-changes/{$id}")->assertOk()->json('data.changes');
        $this->assertSame([
            'Անունը՝ «Վաճառքներ» → «Վաճառքներ 2026»',
            'Սյունակ «Ապրանք» → «Ապրանքի անուն»',
            '«Գումար» սյունակի իմաստը՝ առանց իմաստի',
            'Նոր սյունակ «Ինքնարժեք»՝ գումար, ինքնարժեք',
            'Ջնջել «Հաճախորդ» սյունակը (դրա արժեքները 3 տողում կկորչեն)',
        ], $changes);
        $this->assertSame('Վաճառքներ', $table->fresh()->name);

        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertOk();
        $fresh = $table->fresh();
        $this->assertSame(['Ամսաթիվ', 'Ապրանքի անուն', 'Գումար', 'Ինքնարժեք'], array_column($fresh->columns, 'label'));
        // The amount lost its meaning: the rows' amounts follow at once.
        $this->assertSame(0.0, (float) $fresh->rows()->sum('amount'));

        $this->postJson("/api/v1/admin/table-changes/{$id}/undo")->assertOk();
        $this->assertSame(['Ամսաթիվ', 'Ապրանք', 'Գումար', 'Հաճախորդ'], array_column($table->fresh()->columns, 'label'));
        $this->assertSame(3200.0, (float) $table->rows()->sum('amount'));
    }

    public function test_deleting_tables_cant_be_undone_and_discarded_cards_do_nothing(): void
    {
        $table = $this->sales();

        $reply = $this->ask([['delete_tables', ['tables' => [$table->id]]]]);
        $id = $reply['actions'][0]['id'];
        $this->getJson("/api/v1/admin/table-changes/{$id}")->assertJsonPath('data.undoable', false)->assertJsonPath('data.changes', ['«Վաճառքներ»՝ 3 տող']);
        $this->postJson("/api/v1/admin/table-changes/{$id}/discard")->assertOk()->assertJsonPath('data.status', 'discarded');
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertUnprocessable();
        $this->assertSame(1, DataTable::query()->count());

        $reply = $this->ask([['delete_tables', ['tables' => [$table->id]]]]);
        $id = $reply['actions'][0]['id'];
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertOk();
        $this->assertSame(0, DataTable::withArchived()->count());
        $this->assertSame(0, DataTableRow::query()->count());
        $this->postJson("/api/v1/admin/table-changes/{$id}/undo")->assertUnprocessable()->assertJsonPath('message', 'Ջնջված աղյուսակները հետ բերել հնարավոր չէ։');
    }

    public function test_only_those_who_design_tables_get_the_cards_and_other_businesses_never(): void
    {
        $table = $this->sales();
        $reply = $this->ask([['add_rows', ['table' => $table->id, 'rows' => [['Ապրանք' => 'Մոմ', 'Գումար' => 1]]]]]);
        $id = $reply['actions'][0]['id'];

        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->postJson("/api/v1/admin/table-changes/{$id}/apply")->assertForbidden();

        $other = $this->createTenant('other-shop');
        Sanctum::actingAs(User::factory()->forTenant($other, 'owner')->create());
        $this->getJson("/api/v1/admin/table-changes/{$id}")->assertNotFound();
        $this->assertSame(3, $table->rows()->count());
    }
}

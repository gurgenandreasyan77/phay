<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\TableReshape;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Splitting tables into periods and putting them together: a year's sales
 * into the months that have rows, months into a quarter, a preview first,
 * the old tables archived and the reshape undone while nothing changed.
 */
class TableReshapeTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop, 'owner')->create();
        Sanctum::actingAs($this->owner);
        // The test reads the shop's tables, as its requests do.
        app(TenantContext::class)->set($this->shop);
    }

    /**
     * @param  list<array{0: ?string, 1: string, 2: int}>  $rows  date, item, amount
     * @param  list<array<string, mixed>>  $extra  more columns
     */
    private function table(string $name, array $rows, array $options = [], array $extra = []): DataTable
    {
        $data = $this->postJson('/api/v1/admin/data-tables', [
            'section' => $options['section'] ?? 'sales',
            'name' => $name,
            ...(isset($options['period']) ? ['period' => $options['period'], 'periodDate' => $options['periodDate'] ?? null] : []),
            'columns' => [
                ['label' => $options['dateLabel'] ?? 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => $options['itemLabel'] ?? 'Ապրանք', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
                ...$extra,
            ],
        ])->assertCreated()->json('data');

        $table = DataTable::query()->findOrFail($data['id']);
        [$date, $item, $amount] = array_column($table->columns, 'key');
        foreach ($rows as [$at, $what, $money]) {
            DataTableRow::create(['data_table_id' => $table->id, 'data' => [$date => $at, $item => $what, $amount => $money]]);
        }

        return $table;
    }

    private function year(): DataTable
    {
        return $this->table('Վաճառքներ 2026', [
            ['2026-01-05', 'Մոմ', 1000],
            ['2026-01-20', 'Մոմ', 1500],
            ['2026-03-02', 'Օճառ', 700],
            ['2026-04-15', 'Մոմ', 2000],
            ['2026-10-30', 'Օճառ', 900],
        ], ['period' => 'year', 'periodDate' => '2026-01-01']);
    }

    public function test_a_year_splits_into_the_months_that_have_rows_only(): void
    {
        $year = $this->year();

        $preview = $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$year->id], 'period' => 'month'])
            ->assertOk()
            ->json('data');

        $this->assertSame(['Վաճառքներ · Հունվար 2026', 'Վաճառքներ · Մարտ 2026', 'Վաճառքներ · Ապրիլ 2026', 'Վաճառքներ · Հոկտեմբեր 2026'], array_column($preview['targets'], 'name'));
        $this->assertSame([2, 1, 1, 1], array_column($preview['targets'], 'rows'));
        $this->assertSame(2500, $preview['targets'][0]['amount']);
        $this->assertSame(0, $preview['undatedRows']);
        // A preview changes nothing.
        $this->assertSame(1, DataTable::query()->count());
        $this->assertSame(0, TableReshape::query()->count());

        $reshape = $this->postJson('/api/v1/admin/table-reshapes', ['ids' => [$year->id], 'period' => 'month'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonCount(4, 'data.results')
            ->assertJsonPath('data.undoBlocked', null)
            ->json('data');

        // The year is archived (kept, out of the analytics), the months are current.
        $year->refresh();
        $this->assertNotNull($year->archived_at);
        $this->assertSame($reshape['id'], $year->reshape_id);
        $current = DataTable::query()->orderBy('period_start')->get();
        $this->assertSame(['2026-01-01', '2026-03-01', '2026-04-01', '2026-10-01'], $current->map(fn ($t) => $t->period_start->toDateString())->all());
        $this->assertTrue($current->every(fn ($t) => $t->period->value === 'month' && $t->columns === $year->columns));
        $this->assertSame(5, DataTableRow::query()->whereIn('data_table_id', $current->modelKeys())->count());
        $this->assertSame(6100.0, (float) DataTableRow::query()->whereIn('data_table_id', $current->modelKeys())->sum('amount'));
        // The old rows stay with the old table.
        $this->assertSame(5, $year->rows()->count());

        // Listed: the new ones, then the archived year pointing at its reshape.
        $this->getJson('/api/v1/admin/data-tables?section=sales')->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.4.id', $year->id)
            ->assertJsonPath('data.4.reshapeId', $reshape['id']);
    }

    public function test_months_with_other_columns_join_into_a_quarter(): void
    {
        $october = $this->table('Հոկտեմբեր 2026', [['2026-10-03', 'Մոմ', 1000]], ['period' => 'month', 'periodDate' => '2026-10-01']);
        // Other names for the same meanings, and a column of its own.
        $november = $this->table('Նոյեմբեր 2026', [['2026-11-11', 'Օճառ', 500], ['2026-11-12', 'Մոմ', 800]], [
            'period' => 'month', 'periodDate' => '2026-11-01', 'dateLabel' => 'Օր', 'itemLabel' => 'Անվանում',
        ], [['label' => 'Զեղչ', 'type' => 'money', 'role' => 'discount']]);
        $december = $this->table('Դեկտեմբեր 2026', [['2026-12-24', 'Մոմ', 1200], [null, 'Նվեր', 300]], ['period' => 'month', 'periodDate' => '2026-12-01']);

        $preview = $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$december->id, $october->id, $november->id], 'period' => 'quarter'])
            ->assertOk()
            ->assertJsonPath('data.undatedRows', 1)
            ->json('data');
        $this->assertSame(['4-րդ եռամսյակ 2026', 'Առանց ամսաթվի'], array_column($preview['targets'], 'name'));
        $this->assertSame(['Ամսաթիվ', 'Ապրանք', 'Գումար', 'Զեղչ'], $preview['columns']);
        $this->assertSame(['Զեղչ'], $preview['addedColumns']);

        // Rows without a date into the newest table instead.
        $this->postJson('/api/v1/admin/table-reshapes', ['ids' => [$october->id, $november->id, $december->id], 'period' => 'quarter', 'undated' => 'latest'])
            ->assertCreated()
            ->assertJsonCount(1, 'data.results');

        $quarter = DataTable::query()->sole();
        $this->assertSame('4-րդ եռամսյակ 2026', $quarter->name);
        $this->assertSame('quarter', $quarter->period->value);
        $keys = array_column($quarter->columns, 'key', 'label');
        $rows = $quarter->rows()->orderBy('id')->get();
        $this->assertCount(5, $rows);
        $november = $rows->first(fn ($r) => ($r->data[$keys['Ապրանք']] ?? null) === 'Օճառ');
        $this->assertSame('2026-11-11', $november->data[$keys['Ամսաթիվ']]);
        $this->assertSame('2026-11-11 00:00:00', $november->getRawOriginal('occurred_at'));
        $this->assertSame(3800.0, (float) $quarter->rows()->sum('amount'));
        $this->assertSame(1, $quarter->rows()->whereNull('occurred_at')->count());
    }

    public function test_undo_puts_the_tables_back_until_the_new_ones_change(): void
    {
        $year = $this->year();
        $id = $this->postJson('/api/v1/admin/table-reshapes', ['ids' => [$year->id], 'period' => 'quarter'])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/admin/table-reshapes/{$id}/undo")->assertOk()->assertJsonPath('data.status', 'undone');
        $this->assertSame([$year->id], DataTable::query()->pluck('id')->all());
        $this->assertNull($year->fresh()->archived_at);
        $this->assertSame(5, DataTableRow::query()->count());

        // Once a row of a new table is edited, undoing would lose it.
        $id = $this->postJson('/api/v1/admin/table-reshapes', ['ids' => [$year->id], 'period' => 'quarter'])->assertCreated()->json('data.id');
        $row = DataTableRow::query()->whereIn('data_table_id', DataTable::query()->pluck('id'))->first();
        $this->travel(2)->seconds();
        $item = array_column($row->table->columns, 'key')[1];
        $this->patchJson("/api/v1/admin/data-tables/{$row->data_table_id}/rows/{$row->id}", ['values' => [...$row->data, $item => 'Փոխված']])->assertOk();

        $this->getJson("/api/v1/admin/table-reshapes/{$id}")->assertOk()->assertJsonPath('data.undoBlocked', fn ($why) => is_string($why));
        $this->postJson("/api/v1/admin/table-reshapes/{$id}/undo")->assertUnprocessable()->assertJsonPath('code', 'reshape_refused');
        $this->assertNotNull($year->fresh()->archived_at);
    }

    public function test_what_cant_be_reshaped_is_refused(): void
    {
        $year = $this->year();
        $orders = $this->table('Պատվերներ', [['2026-02-01', 'Լվացում', 5000]], ['section' => 'orders']);

        // Another section.
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$year->id, $orders->id], 'period' => 'year'])
            ->assertUnprocessable()->assertJsonPath('code', 'reshape_refused');
        // Already kept like this.
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$year->id], 'period' => 'year'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'արդեն այդպես'));
        // Unknown period, or a table of another business.
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$year->id], 'period' => 'decade'])->assertUnprocessable();
        $other = $this->createTenant('other-shop');
        $foreign = $this->asTenant($other, fn () => DataTable::create(['section' => 'sales', 'name' => 'X', 'columns' => []]));
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$foreign->id], 'period' => 'custom'])->assertNotFound();

        // A table without a date column only joins into one table of any dates.
        $plain = DataTable::create(['section' => 'sales', 'name' => 'Ցուցակ', 'columns' => [['key' => 'c_a', 'label' => 'Անուն', 'type' => 'text', 'required' => false, 'options' => [], 'role' => null]]]);
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$plain->id, $year->id], 'period' => 'month'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'Ցուցակ'));
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$plain->id, $year->id], 'period' => 'custom'])
            ->assertOk()->assertJsonPath('data.targets.0.rows', 5);

        // Moderators don't design tables; an admin doesn't see expenses.
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$year->id], 'period' => 'month'])->assertForbidden();
        Sanctum::actingAs($this->owner);
        $costs = $this->table('Ծախսեր', [['2026-02-01', 'Վարձ', 100000]], ['section' => 'expenses']);
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'admin')->create());
        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$costs->id], 'period' => 'month'])->assertNotFound();
    }

    public function test_tables_already_kept_for_a_period_are_pointed_out(): void
    {
        $year = $this->year();
        $this->table('Ապրիլ 2026', [['2026-04-02', 'Մոմ', 100]], ['period' => 'month', 'periodDate' => '2026-04-01']);

        $this->postJson('/api/v1/admin/table-reshapes/preview', ['ids' => [$year->id], 'period' => 'month'])
            ->assertOk()
            ->assertJsonCount(1, 'data.conflicts')
            ->assertJsonPath('data.conflicts.0.name', 'Ապրիլ 2026');
    }

    public function test_the_table_agent_only_plans_and_the_owner_applies_from_the_card(): void
    {
        $client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $client);
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/tables", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
        $year = $this->year();

        $client->toolCalls = [
            ['reshape_tables', ['tables' => [$year->id], 'period' => 'month']],
            ['download_file', ['what' => 'tables', 'tables' => [$year->id]]],
        ];
        $reply = $this->postJson('/api/v1/admin/assistant', ['question' => 'Բաժանիր ըստ ամիսների', 'agent' => 'tables'])
            ->assertOk()
            ->assertJsonCount(2, 'data.actions')
            ->assertJsonPath('data.actions.0.type', 'reshape')
            ->assertJsonPath('data.actions.1', ['type' => 'download', 'kind' => 'tables', 'ids' => [$year->id], 'format' => 'xlsx', 'label' => 'Վաճառքներ 2026'])
            ->json('data');
        $this->assertContains('reshape_tables', array_column($client->toolDefinitions, 'name'));
        $this->assertStringContainsString('reshape_tables', $client->system);
        $this->assertSame(4, count($client->toolResults[0]['preview']['targets']));

        // Nothing changed yet; the card is kept with the answer.
        $this->assertSame(1, DataTable::query()->count());
        $id = $reply['actions'][0]['id'];
        $this->getJson("/api/v1/admin/assistant/conversations/{$reply['conversation']['id']}")->assertOk()
            ->assertJsonPath('data.messages.1.actions.0', ['type' => 'reshape', 'id' => $id]);
        $this->getJson("/api/v1/admin/table-reshapes/{$id}")->assertOk()->assertJsonPath('data.status', 'planned')->assertJsonCount(4, 'data.targets');

        $this->postJson("/api/v1/admin/table-reshapes/{$id}/apply")->assertOk()->assertJsonPath('data.status', 'applied');
        $this->assertSame(4, DataTable::query()->count());
        // Twice is once.
        $this->postJson("/api/v1/admin/table-reshapes/{$id}/apply")->assertUnprocessable();
        $this->assertSame(4, DataTable::query()->count());
    }

    public function test_the_general_assistant_offers_the_tools_to_owners_only(): void
    {
        $client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $client);
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Բարև'])->assertOk();
        $this->assertContains('download_file', array_column($client->toolDefinitions, 'name'));
        $this->assertContains('reshape_tables', array_column($client->toolDefinitions, 'name'));
    }
}

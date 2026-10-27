<?php

namespace Tests\Feature\Admin;

use App\DataTables\Changes\ChangeRefused;
use App\DataTables\Changes\TableChanges;
use App\DataTables\OrderNumbers;
use App\Models\DataTable;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Adding an order or a sale from the calendar, and by the AI (Sona's
 * cards), follows the same rules: the table of the date's period (a new
 * one when missing), the next order number when none is typed, and a
 * performer who isn't free then (away, or on another order) is told.
 */
class CalendarAddTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-27 10:00:00');
        $this->salon = $this->createTenant('test-salon');
        $this->owner = User::factory()->forTenant($this->salon, 'owner')->create();
        Sanctum::actingAs($this->owner);
        app(TenantContext::class)->set($this->salon);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An October table of orders: number, service, date and time, performer, duration, amount. */
    private function october(): DataTable
    {
        $id = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Հոկտեմբեր',
            'period' => 'month',
            'periodDate' => '2026-10-01',
            'columns' => [
                ['label' => 'Պատվերի համար', 'type' => 'text', 'role' => 'order_number'],
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Ամսաթիվ', 'type' => 'datetime', 'role' => 'date'],
                ['label' => 'Կատարող', 'type' => 'text', 'role' => 'performer'],
                ['label' => 'Տևողություն', 'type' => 'number', 'role' => 'duration'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
            ],
        ])->assertCreated()->json('data.id');

        return DataTable::query()->findOrFail($id);
    }

    /** @return array<string, string> column key by role */
    private function keys(DataTable $table): array
    {
        return collect($table->columnDefinitions())->mapWithKeys(fn ($c) => [$c->role->value => $c->key])->all();
    }

    private function add(DataTable $table, array $byRole, bool $anyway = false)
    {
        $keys = $this->keys($table);
        $values = collect($byRole)->mapWithKeys(fn ($v, $role) => [$keys[$role] => $v])->all();

        return $this->postJson('/api/v1/admin/calendar/rows', ['table' => $table->id, 'values' => $values, 'anyway' => $anyway]);
    }

    public function test_an_empty_order_number_gets_the_next_one_in_its_style(): void
    {
        $october = $this->october();
        $this->add($october, ['order_number' => 'SR-0041', 'item' => 'Բազմոց', 'date' => '2026-10-05 10:00'])->assertCreated();
        $this->add($october, ['order_number' => 'SR-0009', 'item' => 'Աթոռ', 'date' => '2026-10-06 10:00'])->assertCreated();

        $row = $this->add($october, ['item' => 'Ներքնակ', 'date' => '2026-10-07 10:00'])->assertCreated()->json('data.row');
        $this->assertSame('SR-0042', $row['values'][$this->keys($october)['order_number']]);

        // A number typed in is kept, and the biggest one goes on; the form is told the next one.
        $this->postJson('/api/v1/admin/calendar/rows/check', ['table' => $october->id, 'values' => []])
            ->assertOk()->assertJsonPath('data.orderNumber', 'SR-0043');
        $typed = $this->add($october, ['order_number' => '777', 'item' => 'Գորգ', 'date' => '2026-10-08 10:00'])->assertCreated()->json('data.row');
        $this->assertSame('777', $typed['values'][$this->keys($october)['order_number']]);
        $this->postJson('/api/v1/admin/calendar/rows/check', ['table' => $october->id, 'values' => []])
            ->assertOk()->assertJsonPath('data.orderNumber', '778');

        $this->assertSame('1', OrderNumbers::after('0'));
        $this->assertSame('A-10', OrderNumbers::after('A-9'));
        $this->assertSame('Պ-0100', OrderNumbers::after('Պ-0099'));
    }

    public function test_a_november_order_gets_a_november_table_and_its_number_continues(): void
    {
        $october = $this->october();
        $this->add($october, ['order_number' => '12', 'item' => 'Բազմոց', 'date' => '2026-10-05 10:00'])->assertCreated();

        $response = $this->add($october, ['item' => 'Ներքնակ', 'date' => '2026-11-03 12:00'])->assertCreated();

        $this->assertTrue($response->json('data.created'));
        $this->assertSame('Նոյեմբեր', $response->json('data.table.name'));
        $november = DataTable::query()->findOrFail($response->json('data.table.id'));
        $this->assertSame('13', $november->rows()->first()->data[$this->keys($november)['order_number']]);
        $this->assertSame(1, $october->rows()->count());
    }

    public function test_a_performer_on_another_order_at_that_time_is_told_and_may_be_kept_anyway(): void
    {
        $october = $this->october();
        Employee::create(['name' => 'Արման Սարգսյան']);
        Employee::create(['name' => 'Գոռ Հակոբյան']);
        $this->add($october, ['item' => 'Բազմոց', 'date' => '2026-10-29 14:00', 'performer' => 'Արման Սարգսյան', 'duration' => 90])->assertCreated();

        // 15:00 is inside 14:00–15:30.
        $busy = $this->add($october, ['item' => 'Ներքնակ', 'date' => '2026-10-29 15:00', 'performer' => 'Արման'])
            ->assertStatus(409)->assertJsonPath('code', 'performer_busy');
        $this->assertSame(
            'Արման Սարգսյան 29.10.2026-ին 15:00–16:00 ազատ չէ՝ արդեն ունի պատվեր «Բազմոց» (14:00–15:30)։ Այդ ժամին ազատ են՝ Գոռ Հակոբյան։',
            $busy->json('conflicts.0.message'),
        );
        $this->assertSame(['Գոռ Հակոբյան'], $busy->json('conflicts.0.free'));
        $this->assertSame(1, $october->rows()->count());

        // The form says it while typing, too.
        $keys = $this->keys($october);
        $this->postJson('/api/v1/admin/calendar/rows/check', ['table' => $october->id, 'values' => [
            $keys['date'] => '2026-10-29 15:00', $keys['performer'] => 'Արման',
        ]])->assertOk()->assertJsonPath('data.conflicts.0.kind', 'busy');

        // After it is free; and the owner may keep him anyway.
        $this->add($october, ['item' => 'Աթոռ', 'date' => '2026-10-29 15:30', 'performer' => 'Արման'])->assertCreated();
        $this->add($october, ['item' => 'Ներքնակ', 'date' => '2026-10-29 15:00', 'performer' => 'Արման'], anyway: true)->assertCreated();
        $this->assertSame(3, $october->rows()->count());
    }

    public function test_an_away_performer_is_told_and_nothing_is_made(): void
    {
        $october = $this->october();
        $arman = Employee::create(['name' => 'Արման Սարգսյան']);
        EmployeeAbsence::create(['employee_id' => $arman->id, 'kind' => 'vacation', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-06']);

        $this->add($october, ['item' => 'Բազմոց', 'date' => '2026-11-04 10:00', 'performer' => 'Արման Սարգսյան'])
            ->assertStatus(409)
            ->assertJsonPath('conflicts.0.kind', 'away');
        // Not even November's table.
        $this->assertSame(1, DataTable::query()->count());

        // Someone from outside isn't checked.
        $this->add($october, ['item' => 'Բազմոց', 'date' => '2026-11-04 10:00', 'performer' => 'Արտաքին վարպետ'])->assertCreated();
    }

    public function test_sona_puts_a_november_order_in_a_new_november_table_and_undo_removes_it(): void
    {
        $october = $this->october();
        $this->add($october, ['order_number' => '5', 'item' => 'Բազմոց', 'date' => '2026-10-05 10:00'])->assertCreated();
        $changes = app(TableChanges::class);

        $change = $changes->planAdd($october, [['Ծառայություն' => 'Ներքնակ', 'Ամսաթիվ' => '2026-11-10 11:00']], $this->owner);

        $this->assertSame('Ավելացնել 1 տող նոր «Նոյեմբեր» աղյուսակում', $changes->title($change));
        $this->assertSame('6', $change->payload['rows'][0][$this->keys($october)['order_number']]);
        $this->assertSame(1, DataTable::query()->count());

        $changes->apply($change);
        $november = DataTable::query()->where('name', 'Նոյեմբեր')->firstOrFail();
        $this->assertSame(1, $november->rows()->count());
        $this->assertSame(1, $october->rows()->count());
        $this->assertSame($november->id, $change->fresh()->data_table_id);

        $changes->undo($change->fresh());
        $this->assertNull(DataTable::query()->find($november->id));

        // A table of November there already: the rows go to it.
        $again = $changes->planAdd($october, [['Ծառայություն' => 'Ներքնակ', 'Ամսաթիվ' => '2026-11-10 11:00']], $this->owner);
        $changes->apply($again);
        $november = DataTable::query()->where('name', 'Նոյեմբեր')->firstOrFail();
        $third = $changes->planAdd($october, [['Ծառայություն' => 'Գորգ', 'Ամսաթիվ' => '2026-11-12 11:00']], $this->owner);
        $this->assertSame($november->id, $third->data_table_id);
        $this->assertArrayNotHasKey('new_table', $third->payload);
    }

    public function test_sona_refuses_rows_of_two_periods_in_one_card_and_warns_of_a_busy_performer(): void
    {
        $october = $this->october();
        Employee::create(['name' => 'Արման Սարգսյան']);
        $changes = app(TableChanges::class);

        try {
            $changes->planAdd($october, [
                ['Ծառայություն' => 'Բազմոց', 'Ամսաթիվ' => '2026-10-28 10:00'],
                ['Ծառայություն' => 'Ներքնակ', 'Ամսաթիվ' => '2026-11-10 11:00'],
            ], $this->owner);
            $this->fail('Rows of two months were planned in one card.');
        } catch (ChangeRefused $e) {
            $this->assertStringContainsString('«Հոկտեմբեր»՝ 1 տող', $e->getMessage());
        }

        // Two orders of his at once, in the same card.
        $change = $changes->planAdd($october, [
            ['Ծառայություն' => 'Բազմոց', 'Ամսաթիվ' => '2026-10-28 10:00', 'Կատարող' => 'Արման'],
            ['Ծառայություն' => 'Ներքնակ', 'Ամսաթիվ' => '2026-10-28 10:30', 'Կատարող' => 'Արման'],
        ], $this->owner);
        $warnings = $changes->performersAway($change);
        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('ազատ չէ՝ արդեն ունի պատվեր «Ներքնակ»', $warnings[0]);
        // Numbers one after another.
        $number = $this->keys($october)['order_number'];
        $this->assertSame(['1', '2'], array_column($change->payload['rows'], $number));
    }
}

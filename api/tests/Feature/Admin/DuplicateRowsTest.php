<?php

namespace Tests\Feature\Admin;

use App\Assistant\TableEditTools;
use App\Models\DataTableRow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * The same order written in several tables is one order: the calendar shows
 * it once and analytics count it once; a row that may be the same (a field
 * missing) is shown to the owner, who keeps both or deletes a copy. Another
 * performer, price or time is another order.
 */
class DuplicateRowsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-21 10:00:00');
        $this->salon = $this->createTenant('test-salon');
        Sanctum::actingAs(User::factory()->forTenant($this->salon, 'owner')->create());
    }

    /** @return array{id: int, keys: array<string, string>} keys by label */
    private function table(string $name, bool $address = true, string $section = 'orders'): array
    {
        $columns = [
            ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
            ['label' => 'Ամսաթիվ', 'type' => 'datetime', 'role' => 'date'],
            ['label' => 'Կատարող', 'type' => 'text', 'role' => 'performer'],
            ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
        ];
        if ($address) {
            $columns[] = ['label' => 'Հասցե', 'type' => 'text', 'role' => 'address'];
        }
        $data = $this->postJson('/api/v1/admin/data-tables', ['section' => $section, 'name' => $name, 'columns' => $columns])
            ->assertCreated()->json('data');

        return ['id' => $data['id'], 'keys' => array_column($data['columns'], 'key', 'label')];
    }

    private function row(array $table, array $values): int
    {
        $data = [];
        foreach ($values as $label => $value) {
            $data[$table['keys'][$label]] = $value;
        }

        return $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => $data])->assertCreated()->json('data.id');
    }

    private function order(array $table, string $at = '2026-10-19 10:00', string $performer = 'Արամ', int $amount = 9900, ?string $address = 'Կոմիտասի 5'): int
    {
        return $this->row($table, array_filter([
            'Ծառայություն' => 'Բազմոցի մաքրում',
            'Ամսաթիվ' => $at,
            'Կատարող' => $performer,
            'Գումար' => $amount,
            'Հասցե' => isset($table['keys']['Հասցե']) ? $address : null,
        ], fn ($v) => $v !== null));
    }

    private function find(int $id): ?DataTableRow
    {
        return $this->asTenant($this->salon, fn () => DataTableRow::query()->find($id));
    }

    private function calendarRows(): array
    {
        return $this->getJson('/api/v1/admin/calendar?from=2026-10-19&to=2026-10-19')->assertOk()->json('data.rows');
    }

    private function revenue(): float
    {
        return (float) $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.finance.revenue');
    }

    public function test_the_same_order_in_three_tables_shows_and_counts_once(): void
    {
        $a = $this->table('Պատվերներ');
        $b = $this->table('Պատճեն');
        $c = $this->table('Հոկտեմբեր');
        $first = $this->order($a);
        $this->order($b);
        $this->order($c);

        $rows = $this->calendarRows();
        $this->assertCount(1, $rows);
        $this->assertSame($first, $rows[0]['id']);
        $this->assertSame(2, $rows[0]['copies']);
        $this->assertEquals(9900, $this->revenue());

        $groups = $this->getJson('/api/v1/admin/duplicates')->assertOk()->json('data.groups');
        $this->assertCount(1, $groups);
        $this->assertSame('exact', $groups[0]['kind']);
        $this->assertSame([null, $first, $first], array_column($groups[0]['rows'], 'copyOf'));

        // The table's own page still shows its row, marked as a copy.
        $this->assertSame($first, $this->getJson("/api/v1/admin/data-tables/{$b['id']}/rows")->json('data.0.duplicateOf'));
    }

    public function test_another_performer_or_time_is_another_order(): void
    {
        $a = $this->table('Պատվերներ');
        $b = $this->table('Պատճեն');
        $this->order($a);
        $this->order($b, performer: 'Գոռ');
        $this->order($b, at: '2026-10-19 15:00');

        $this->assertCount(3, $this->calendarRows());
        $this->assertEquals(29700, $this->revenue());
        $this->assertSame([], $this->getJson('/api/v1/admin/duplicates')->json('data.groups'));
    }

    public function test_two_equal_rows_of_one_table_are_two_orders(): void
    {
        $a = $this->table('Պատվերներ');
        $this->order($a);
        $this->order($a);

        $this->assertCount(2, $this->calendarRows());
        $this->assertSame([], $this->getJson('/api/v1/admin/duplicates')->json('data.groups'));
    }

    public function test_a_row_missing_the_address_is_asked_about_and_kept_on_request(): void
    {
        $a = $this->table('Պատվերներ');
        $b = $this->table('Առանց հասցեի', address: false);
        $c = $this->table('Դատարկ հասցե');
        $one = $this->order($a);
        $two = $this->order($b);
        $three = $this->order($c, at: '2026-10-19 12:00', address: null);
        $this->order($a, at: '2026-10-19 12:00');

        // Both count until the owner decides.
        $rows = $this->calendarRows();
        $this->assertCount(4, $rows);
        $this->assertEquals(39600, $this->revenue());
        $this->assertNotNull(collect($rows)->firstWhere('id', $one)['possibleDuplicate']);
        $this->assertNotNull(collect($rows)->firstWhere('id', $three)['possibleDuplicate']);
        $this->assertSame(2, $this->getJson('/api/v1/admin/calendar?from=2026-10-19&to=2026-10-19')->json('data.duplicates'));

        $groups = collect($this->getJson('/api/v1/admin/duplicates')->json('data.groups'));
        $this->assertSame(['near', 'near'], $groups->pluck('kind')->all());
        $group = $groups->first(fn ($g) => in_array($two, array_column($g['rows'], 'id'), true));
        $this->assertSame(['Հասցե'], collect($group['rows'])->firstWhere('id', $two)['missing']);

        $this->postJson('/api/v1/admin/duplicates/keep', ['ids' => [$one, $two]])->assertOk();
        $this->assertCount(1, $this->getJson('/api/v1/admin/duplicates')->json('data.groups'));
        $this->assertEquals(39600, $this->revenue());

        // Deleting the copy: the other stays, nothing more to ask.
        $this->postJson('/api/v1/admin/duplicates/delete', ['ids' => [$three]])->assertOk();
        $this->assertDatabaseMissing('data_table_rows', ['id' => $three]);
        $this->assertSame([], $this->getJson('/api/v1/admin/duplicates')->json('data.groups'));
        $this->assertEquals(29700, $this->revenue());
    }

    public function test_deleting_the_table_of_the_counted_row_counts_the_copy(): void
    {
        $a = $this->table('Պատվերներ');
        $b = $this->table('Պատճեն');
        $this->order($a);
        $copy = $this->order($b);
        $this->assertEquals(9900, $this->revenue());

        $this->deleteJson("/api/v1/admin/data-tables/{$a['id']}")->assertNoContent();

        $this->assertNull($this->find($copy)->duplicate_of_id);
        $this->assertSame([$copy], array_column($this->calendarRows(), 'id'));
        $this->assertEquals(9900, $this->revenue());
    }

    public function test_editing_a_copy_so_it_differs_counts_it_again(): void
    {
        $a = $this->table('Պատվերներ');
        $b = $this->table('Պատճեն');
        $this->order($a);
        $copy = $this->order($b);

        $values = $this->find($copy)->data;
        $this->patchJson("/api/v1/admin/data-tables/{$b['id']}/rows/{$copy}", ['values' => [...$values, $b['keys']['Գումար'] => 12000]])->assertOk();

        $this->assertNull($this->find($copy)->duplicate_of_id);
        $this->assertEquals(21900, $this->revenue());
    }

    public function test_the_command_marks_rows_written_before(): void
    {
        $a = $this->table('Պատվերներ');
        $b = $this->table('Պատճեն');
        $this->order($a);
        $copy = $this->order($b);
        $this->asTenant($this->salon, fn () => DataTableRow::query()->update(['duplicate_of_id' => null]));

        $this->artisan('tables:duplicates')->assertSuccessful();

        $this->assertNotNull($this->find($copy)->duplicate_of_id);
    }

    public function test_sona_finds_duplicates_without_the_buyers_details(): void
    {
        $a = $this->table('Պատվերներ');
        $b = $this->table('Պատճեն');
        $this->order($a);
        $this->order($b);

        $found = $this->asTenant($this->salon, fn () => app(TableEditTools::class)->run(TableEditTools::DUPLICATES, []));

        $this->assertCount(1, $found['groups']);
        $values = $found['groups'][0]['rows'][0]['values'];
        $this->assertSame('Բազմոցի մաքրում', $values['Ծառայություն']);
        $this->assertArrayNotHasKey('Հասցե', $values);
    }
}

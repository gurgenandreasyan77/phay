<?php

namespace Tests\Feature\Admin;

use App\Enums\ColumnRole;
use App\Models\DataTableRow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Employees of a business and the orders they did: the performer column
 * links a row to an employee by name, and the employees page counts each
 * one's orders, revenue and costs.
 */
class AdminEmployeeTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $salon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = $this->createTenant('test-salon');
        $this->actingAsMember('owner');
    }

    private function actingAsMember(string $role): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->salon, $role)->create());
    }

    /** @return array<string, mixed> */
    private function ordersTable(): array
    {
        return $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Պատվերներ',
            'columns' => [
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Քանակ', 'type' => 'number', 'role' => 'quantity'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
                ['label' => 'Ինքնարժեք', 'type' => 'money', 'role' => 'cost'],
                ['label' => 'Կատարող', 'type' => 'text', 'role' => 'performer'],
            ],
        ])->assertCreated()->json('data');
    }

    private function addRow(array $table, string $date, ?int $amount, ?int $cost, ?string $performer, int $quantity = 1): int
    {
        [$item, $at, $qty, $sum, $unitCost, $who] = array_column($table['columns'], 'key');

        return $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [
            $item => 'Բազմոց', $at => $date, $qty => $quantity, $sum => $amount, $unitCost => $cost, $who => $performer,
        ]])->assertCreated()->json('data.id');
    }

    private function employee(string $name, ?string $position = null): int
    {
        return $this->postJson('/api/v1/admin/employees', ['name' => $name, 'position' => $position])->assertCreated()->json('data.id');
    }

    public function test_orders_revenue_and_costs_per_employee(): void
    {
        $anna = $this->employee('Աննա', 'Վարպետ');
        $gor = $this->employee('Գոռ');
        $table = $this->ordersTable();

        $this->addRow($table, '2026-11-02', 10000, 1500, 'Աննա', 2);
        $this->addRow($table, '2026-11-18', 6000, null, ' աննա ');
        $this->addRow($table, '2026-11-20', 8000, 2000, 'Գոռ');
        $this->addRow($table, '2026-10-30', 50000, null, 'Գոռ');
        $this->addRow($table, '2026-11-21', 3000, null, 'Լևոն');
        $this->addRow($table, '2026-11-22', 4000, null, null);

        $stats = $this->getJson('/api/v1/admin/employees/stats?period=month&date=2026-11-18')->assertOk()->json('data');

        $this->assertSame('2026-11', $stats['period']['key']);
        $this->assertTrue($stats['hasPerformerColumn']);
        $byName = collect($stats['employees'])->keyBy('name');
        // Costs are unit cost × quantity; profit is revenue minus costs.
        $this->assertSame(['orders' => 2, 'revenue' => 16000, 'costs' => 3000, 'profit' => 13000, 'averageCheck' => 8000], collect($byName['Աննա'])->only(['orders', 'revenue', 'costs', 'profit', 'averageCheck'])->all());
        $this->assertSame([1, 8000, 2000, 6000], [$byName['Գոռ']['orders'], $byName['Գոռ']['revenue'], $byName['Գոռ']['costs'], $byName['Գոռ']['profit']]);
        $this->assertSame([['name' => 'Լևոն', 'orders' => 1, 'revenue' => 3000, 'costs' => null, 'profit' => null, 'averageCheck' => 3000]], $stats['unknown']);
        $this->assertSame(1, $stats['unassigned']['orders']);
        $this->assertSame(5, $stats['total']['orders']);

        // The orders page asks for its own section; a sales section has no performer column here.
        $this->assertSame(5, $this->getJson('/api/v1/admin/employees/stats?section=orders&period=month&date=2026-11-18')->json('data.total.orders'));
        $this->assertFalse($this->getJson('/api/v1/admin/employees/stats?section=sales')->json('data.hasPerformerColumn'));

        // All time: October too.
        $this->assertSame(2, collect($this->getJson('/api/v1/admin/employees/stats')->json('data.employees'))->firstWhere('name', 'Գոռ')['orders']);

        // One employee: their figures and orders, newest first.
        $show = $this->getJson("/api/v1/admin/employees/{$anna}?period=month&date=2026-11-01")->assertOk()->json();
        $this->assertSame(16000, $show['data']['figures']['revenue']);
        $this->assertSame(['2026-11-18 00:00', '2026-11-02 00:00'], array_column($show['data']['rows'], 'occurredAt'));
        $this->assertSame([null, 3000], array_column($show['data']['rows'], 'cost'));
        $this->assertSame('Բազմոց', $show['data']['rows'][0]['item']);
        $this->assertSame(2, $this->getJson("/api/v1/admin/employees/{$gor}")->json('data.employee.ordersCount'));
    }

    public function test_a_new_employee_gets_the_rows_already_naming_them_and_a_rename_follows(): void
    {
        $table = $this->ordersTable();
        $row = $this->addRow($table, '2026-11-02', 10000, null, 'Լևոն');
        $this->assertNull(DataTableRow::withoutTenancy()->find($row)->employee_id);

        $levon = $this->employee('լևոն');
        $this->assertSame($levon, DataTableRow::withoutTenancy()->find($row)->employee_id);
        $this->assertSame(1, collect($this->getJson('/api/v1/admin/employees')->json('data'))->firstWhere('id', $levon)['ordersCount']);
    }

    public function test_names_are_unique_renames_rewrite_rows_and_deleting_unlinks(): void
    {
        $table = $this->ordersTable();
        $levon = $this->employee('Լևոն');
        $row = $this->addRow($table, '2026-11-02', 10000, null, 'Լևոն');
        $performer = $table['columns'][5]['key'];

        $this->postJson('/api/v1/admin/employees', ['name' => ' լևոն '])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->patchJson("/api/v1/admin/employees/{$levon}", ['name' => 'Լևոն Պետրոսյան'])->assertOk();
        $stored = DataTableRow::withoutTenancy()->find($row);
        $this->assertSame(['Լևոն Պետրոսյան', $levon], [$stored->data[$performer], $stored->employee_id]);

        $this->patchJson("/api/v1/admin/employees/{$levon}", ['isActive' => false])->assertOk()->assertJsonPath('data.isActive', false);

        $this->deleteJson("/api/v1/admin/employees/{$levon}")->assertNoContent();
        $stored->refresh();
        $this->assertSame(['Լևոն Պետրոսյան', null], [$stored->data[$performer], $stored->employee_id]);
    }

    public function test_a_performer_column_added_later_links_the_rows(): void
    {
        $anna = $this->employee('Աննա');
        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Հին',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Ով', 'type' => 'text'],
            ],
        ])->json('data');
        [$date, $who] = array_column($table['columns'], 'key');
        $row = $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$date => '2026-11-02', $who => 'Աննա']])->json('data.id');
        $this->assertNull(DataTableRow::withoutTenancy()->find($row)->employee_id);

        $columns = $table['columns'];
        $columns[1]['role'] = 'performer';
        $this->patchJson("/api/v1/admin/data-tables/{$table['id']}", ['columns' => $columns])->assertOk();

        $this->assertSame($anna, DataTableRow::withoutTenancy()->find($row)->employee_id);
        // "Исполнитель" in a file's header is the performer.
        $this->assertSame(ColumnRole::Performer, ColumnRole::guess('Исполнитель'));
        $this->assertSame(ColumnRole::Performer, ColumnRole::guess('Աշխատակից'));
    }

    public function test_who_may_see_and_keep_employees(): void
    {
        $id = $this->employee('Աննա');
        $this->addRow($this->ordersTable(), '2026-11-02', 10000, 1500, 'Աննա');

        // A moderator picks performers but doesn't keep the list or see money.
        $this->actingAsMember('moderator');
        $this->getJson('/api/v1/admin/employees')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/admin/employees', ['name' => 'Գոռ'])->assertForbidden();
        $this->getJson('/api/v1/admin/employees/stats')->assertForbidden();
        $this->getJson("/api/v1/admin/employees/{$id}")->assertForbidden();

        // An admin keeps the list and sees an employee's orders; the money is the owner's.
        $this->actingAsMember('admin');
        $this->postJson('/api/v1/admin/employees', ['name' => 'Գոռ'])->assertCreated();
        $this->getJson('/api/v1/admin/employees/stats')->assertForbidden();
        $admin = $this->getJson("/api/v1/admin/employees/{$id}")->assertOk()->json('data');
        $this->assertSame([1, null, null, null], [$admin['figures']['orders'], $admin['figures']['revenue'], $admin['rows'][0]['amount'], $admin['rows'][0]['cost']]);

        // Another business sees none of them.
        $other = $this->createTenant('test-other');
        Sanctum::actingAs(User::factory()->forTenant($other, 'owner')->create());
        $this->getJson('/api/v1/admin/employees')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/admin/employees/{$id}", ['name' => 'X'])->assertNotFound();
        $this->getJson("/api/v1/admin/employees/{$id}")->assertNotFound();
    }

    public function test_groups_with_a_manager_who_may_lead_several(): void
    {
        $manager = $this->employee('Արամ', 'Մենեջեր');
        $anna = $this->employee('Աննա');
        $gor = $this->employee('Գոռ');

        $sofas = $this->postJson('/api/v1/admin/employee-groups', ['name' => 'Բազմոցներ', 'managerId' => $manager])
            ->assertCreated()->assertJsonPath('data.manager.name', 'Արամ')->json('data.id');
        $homes = $this->postJson('/api/v1/admin/employee-groups', ['name' => 'Բնակարաններ', 'managerId' => $manager])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/employee-groups', ['name' => ' բազմոցներ '])->assertJsonValidationErrors('name');

        $this->patchJson("/api/v1/admin/employees/{$anna}", ['groupId' => $sofas])->assertOk()
            ->assertJsonPath('data.group.name', 'Բազմոցներ')
            ->assertJsonPath('data.group.manager.name', 'Արամ');
        $this->patchJson("/api/v1/admin/employees/{$gor}", ['groupId' => $homes])->assertOk();

        // The manager leads both groups.
        $list = collect($this->getJson('/api/v1/admin/employees')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(['Բազմոցներ', 'Բնակարաններ'], array_column($list[$manager]['managedGroups'], 'name'));
        $this->assertSame([], $list[$anna]['managedGroups']);

        // Figures per group: its employees' orders together.
        $table = $this->ordersTable();
        $this->addRow($table, '2026-11-02', 10000, null, 'Աննա');
        $this->addRow($table, '2026-11-03', 5000, null, 'Գոռ');
        $this->addRow($table, '2026-11-04', 7000, null, 'Մաքուր տուն ՍՊԸ');
        $stats = $this->getJson('/api/v1/admin/employees/stats')->assertOk()->json('data');
        $groups = collect($stats['groups'])->keyBy('name');
        $this->assertSame([1, 10000, 'Արամ'], [$groups['Բազմոցներ']['orders'], $groups['Բազմոցներ']['revenue'], $groups['Բազմոցներ']['manager']]);
        $this->assertSame(5000, $groups['Բնակարաններ']['revenue']);
        // An outside performer is counted by name, not as an employee.
        $this->assertSame(['Մաքուր տուն ՍՊԸ', 7000], [$stats['unknown'][0]['name'], $stats['unknown'][0]['revenue']]);

        // Deleting a group keeps its employees; deleting the manager leaves groups without one.
        $this->deleteJson("/api/v1/admin/employee-groups/{$sofas}")->assertNoContent();
        $this->assertNull($this->getJson("/api/v1/admin/employees/{$anna}")->json('data.employee.group'));
        $this->deleteJson("/api/v1/admin/employees/{$manager}")->assertNoContent();
        $this->assertNull($this->getJson('/api/v1/admin/employee-groups')->assertOk()->json('data.0.manager'));
    }

    public function test_groups_are_kept_by_owner_and_admin_and_stay_in_their_business(): void
    {
        $group = $this->postJson('/api/v1/admin/employee-groups', ['name' => 'Բազմոցներ'])->assertCreated()->json('data.id');

        $this->actingAsMember('moderator');
        $this->getJson('/api/v1/admin/employee-groups')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/admin/employee-groups', ['name' => 'X'])->assertForbidden();

        $this->actingAsMember('admin');
        $this->patchJson("/api/v1/admin/employee-groups/{$group}", ['name' => 'Սրահ'])->assertOk()->assertJsonPath('data.name', 'Սրահ');

        $other = $this->createTenant('test-other');
        Sanctum::actingAs(User::factory()->forTenant($other, 'owner')->create());
        $this->getJson('/api/v1/admin/employee-groups')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/admin/employee-groups/{$group}", ['name' => 'X'])->assertNotFound();
        $mine = $this->postJson('/api/v1/admin/employees', ['name' => 'Լևոն'])->json('data.id');
        $this->patchJson("/api/v1/admin/employees/{$mine}", ['groupId' => $group])->assertJsonValidationErrors('groupId');
    }
}

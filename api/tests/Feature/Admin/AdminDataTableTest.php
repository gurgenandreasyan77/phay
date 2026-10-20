<?php

namespace Tests\Feature\Admin;

use App\Models\DataTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Orders and sales as modules, and the tables a business designs itself.
 */
class AdminDataTableTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = $this->createTenant('test-shop');
    }

    private function actingAsMember(string $role = 'owner'): User
    {
        $user = User::factory()->forTenant($this->shop, $role)->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function salesTablePayload(): array
    {
        return [
            'section' => 'sales',
            'name' => 'Instagram վաճառքներ',
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text', 'required' => true],
                ['label' => 'Քանակ', 'type' => 'number'],
                ['label' => 'Գումար', 'type' => 'money'],
                ['label' => 'Ամսաթիվ', 'type' => 'date'],
                ['label' => 'Վճարում', 'type' => 'select', 'options' => ['Կանխիկ', 'Քարտ']],
                ['label' => 'Առաքված', 'type' => 'boolean'],
            ],
        ];
    }

    private function createSalesTable(): array
    {
        return $this->postJson('/api/v1/admin/data-tables', $this->salesTablePayload())->assertCreated()->json('data');
    }

    public function test_a_new_business_has_orders_and_sales_without_any_module(): void
    {
        $this->shop = $this->createTenant('new-salon');
        $this->actingAsMember();

        $this->getJson('/api/v1/admin/leads')->assertOk();
        $this->postJson('/api/v1/admin/data-tables', $this->salesTablePayload())->assertCreated();
        $this->postJson('/api/v1/admin/data-tables', ['section' => 'orders', 'name' => 'Պատվերներ', 'columns' => [
            ['label' => 'Ծառայություն', 'type' => 'text'],
        ]])->assertCreated();
        $this->getJson('/api/v1/admin/data-tables')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_owner_designs_a_table_with_typed_columns(): void
    {
        $this->actingAsMember();

        $table = $this->createSalesTable();

        $this->assertSame('sales', $table['section']);
        $this->assertCount(6, $table['columns']);
        $this->assertSame(0, $table['rowsCount']);
        $this->assertMatchesRegularExpression('/^c_[a-z0-9]{8}$/', $table['columns'][0]['key']);
        $this->assertTrue($table['columns'][0]['required']);
        $this->assertSame(['Կանխիկ', 'Քարտ'], $table['columns'][4]['options']);

        $this->getJson('/api/v1/admin/data-tables?section=sales')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Instagram վաճառքներ');
        $this->getJson('/api/v1/admin/data-tables?section=orders')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_column_definitions_are_validated(): void
    {
        $this->actingAsMember();
        $payload = $this->salesTablePayload();

        $this->postJson('/api/v1/admin/data-tables', ['columns' => []] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('columns');

        $payload['columns'][1]['label'] = 'ապրանք';
        $this->postJson('/api/v1/admin/data-tables', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('columns.1.label');

        $payload = $this->salesTablePayload();
        $payload['columns'][4]['options'] = [];
        $this->postJson('/api/v1/admin/data-tables', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('columns.4.options');

        $payload = $this->salesTablePayload();
        $payload['columns'][0]['type'] = 'formula';
        $this->postJson('/api/v1/admin/data-tables', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('columns.0.type');
    }

    public function test_rows_are_parsed_by_column_type(): void
    {
        $this->actingAsMember();
        $table = $this->createSalesTable();
        [$product, $qty, $amount, $date, $payment, $delivered] = array_column($table['columns'], 'key');

        $row = $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [
            $product => ' Մոմ ',
            $qty => '3',
            $amount => '12 500 ֏',
            $date => '17.10.2026',
            $payment => 'քարտ',
            $delivered => 'այո',
            'not-a-column' => 'dropped',
        ]])->assertCreated()->json('data');

        $this->assertSame([
            $product => 'Մոմ',
            $qty => 3,
            $amount => 12500,
            $date => '2026-10-17',
            $payment => 'Քարտ',
            $delivered => true,
        ], $row['values']);
        $this->assertSame('manual', $row['source']);

        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [
            $qty => 'շատ',
            $date => '32.13.2026',
            $payment => 'Bitcoin',
        ]])->assertUnprocessable()->assertJsonValidationErrors([
            "values.{$product}", "values.{$qty}", "values.{$date}", "values.{$payment}",
        ]);

        $this->patchJson("/api/v1/admin/data-tables/{$table['id']}/rows/{$row['id']}", ['values' => [
            $product => 'Մոմ մեծ', $amount => '1.234.567,50', $date => '2026-10-17',
        ]])->assertOk()
            ->assertJsonPath("data.values.{$product}", 'Մոմ մեծ')
            ->assertJsonPath("data.values.{$amount}", 1234567.5)
            ->assertJsonPath("data.values.{$qty}", null);
    }

    public function test_rows_are_listed_newest_first_and_searched(): void
    {
        $this->actingAsMember();
        $table = $this->createSalesTable();
        $product = $table['columns'][0]['key'];

        foreach (['Candle', 'Soap', 'Candle XL'] as $name) {
            $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$product => $name]])->assertCreated();
        }

        $this->getJson("/api/v1/admin/data-tables/{$table['id']}/rows")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath("data.0.values.{$product}", 'Candle XL')
            ->assertJsonPath('meta.total', 3);

        $this->getJson("/api/v1/admin/data-tables/{$table['id']}/rows?search=candle")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/admin/data-tables/{$table['id']}")->assertOk()->assertJsonPath('data.rowsCount', 3);
    }

    public function test_renaming_a_column_keeps_its_data_and_removing_one_drops_it_from_new_rows(): void
    {
        $this->actingAsMember();
        $table = $this->createSalesTable();
        $columns = $table['columns'];
        $product = $columns[0]['key'];

        $rowId = $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$product => 'Մոմ']])->json('data.id');

        $columns[0]['label'] = 'Անվանում';
        $columns[] = ['label' => 'Նշում', 'type' => 'text'];
        unset($columns[5]);

        $updated = $this->patchJson("/api/v1/admin/data-tables/{$table['id']}", [
            'name' => 'Վաճառքներ 2026',
            'columns' => array_values($columns),
        ])->assertOk()->json('data');

        $this->assertSame('Վաճառքներ 2026', $updated['name']);
        $this->assertSame($product, $updated['columns'][0]['key']);
        $this->assertSame('Անվանում', $updated['columns'][0]['label']);
        $this->assertSame('Նշում', $updated['columns'][5]['label']);
        $this->assertNotContains($table['columns'][5]['key'], array_column($updated['columns'], 'key'));

        $this->getJson("/api/v1/admin/data-tables/{$table['id']}/rows")->assertJsonPath("data.0.values.{$product}", 'Մոմ');

        // A key the client invents is not taken: new columns get fresh keys.
        $hacked = $this->patchJson("/api/v1/admin/data-tables/{$table['id']}", [
            'columns' => [['key' => 'c_custom01', 'label' => 'X', 'type' => 'text']],
        ])->assertOk()->json('data.columns.0.key');
        $this->assertNotSame('c_custom01', $hacked);

        $this->deleteJson("/api/v1/admin/data-tables/{$table['id']}/rows/{$rowId}")->assertNoContent();
        $this->deleteJson("/api/v1/admin/data-tables/{$table['id']}")->assertNoContent();
        $this->assertSame(0, DataTable::withoutTenancy()->count());
    }

    public function test_a_moderator_keeps_rows_but_cannot_change_the_table(): void
    {
        $this->actingAsMember('owner');
        $table = $this->createSalesTable();
        $product = $table['columns'][0]['key'];

        $this->actingAsMember('moderator');

        $this->getJson('/api/v1/admin/data-tables')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$product => 'Մոմ']])->assertCreated();
        $this->postJson('/api/v1/admin/data-tables', $this->salesTablePayload())->assertForbidden();
        $this->patchJson("/api/v1/admin/data-tables/{$table['id']}", ['name' => 'X'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/data-tables/{$table['id']}")->assertForbidden();
    }

    public function test_another_business_cannot_reach_the_table_or_its_rows(): void
    {
        $this->actingAsMember();
        $table = $this->createSalesTable();
        $product = $table['columns'][0]['key'];
        $rowId = $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => [$product => 'Մոմ']])->json('data.id');

        $other = $this->createTenant('other-shop');
        Sanctum::actingAs(User::factory()->forTenant($other)->create());

        $this->getJson('/api/v1/admin/data-tables')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/admin/data-tables/{$table['id']}")->assertNotFound();
        $this->getJson("/api/v1/admin/data-tables/{$table['id']}/rows")->assertNotFound();
        $this->patchJson("/api/v1/admin/data-tables/{$table['id']}/rows/{$rowId}", ['values' => [$product => 'X']])->assertNotFound();

        // A row id of one table can't be reached through another table.
        $ownTable = $this->createSalesTable();
        $this->deleteJson("/api/v1/admin/data-tables/{$ownTable['id']}/rows/{$rowId}")->assertNotFound();
    }
}

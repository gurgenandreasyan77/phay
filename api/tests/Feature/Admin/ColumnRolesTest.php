<?php

namespace Tests\Feature\Admin;

use App\Assistant\BusinessTools;
use App\DataTables\ColumnValues;
use App\DataTables\Import\ColumnMatcher;
use App\Enums\ColumnRole;
use App\Enums\DataTableSection;
use App\Models\DataTableRow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * What the columns of many kinds of businesses mean (a phone case shop's
 * model and mentor, a delivery's address and fee, a hairdresser's kind of
 * service), how they're recognised in a file and what they change in the
 * figures.
 */
class ColumnRolesTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-17 12:00:00');

        $this->shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->forTenant($this->shop)->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function headers(): array
    {
        return [
            ['Модель', ColumnRole::Model],
            ['Модель телефона', ColumnRole::Model],
            ['Phone model', ColumnRole::Model],
            ['Մոդել', ColumnRole::Model],
            ['Наставник', ColumnRole::Intermediary],
            ['Посредник', ColumnRole::Intermediary],
            ['Միջնորդ', ColumnRole::Intermediary],
            ['Комиссия посредника', ColumnRole::Commission],
            ['Страна', ColumnRole::Country],
            ['Страна производитель', ColumnRole::Country],
            ['Импорт из', ColumnRole::Country],
            ['Бренд', ColumnRole::Brand],
            ['Материал', ColumnRole::Material],
            ['Из чего сделан', ColumnRole::Material],
            ['Цвет', ColumnRole::Color],
            ['Размер', ColumnRole::Size],
            ['Артикул', ColumnRole::Sku],
            ['Поставщик', ColumnRole::Supplier],
            ['Итоговая цена', ColumnRole::Amount],
            ['Окончательная цена', ColumnRole::Amount],
            ['Цена со скидкой', ColumnRole::Amount],
            ['Վերջնական գին', ColumnRole::Amount],
            ['Скидка', ColumnRole::Discount],
            ['Զեղչ', ColumnRole::Discount],
            ['Скидка %', ColumnRole::DiscountPercent],
            ['Адрес доставки', ColumnRole::Address],
            ['Стоимость доставки', ColumnRole::DeliveryFee],
            ['Доставка', ColumnRole::DeliveryMethod],
            ['Вид услуги', ColumnRole::Category],
            ['Ծառայության տեսակ', ColumnRole::Category],
            ['Услуга', ColumnRole::Item],
            ['Время', ColumnRole::Time],
            ['Дата и время', ColumnRole::Date],
            ['Длительность, мин', ColumnRole::Duration],
            ['Номер телефона', ColumnRole::Phone],
            ['Номер заказа', ColumnRole::OrderNumber],
            ['№', ColumnRole::OrderNumber],
            ['Email', ColumnRole::Email],
            ['Ապրանքի անուն', ColumnRole::Item],
            ['Город', ColumnRole::City],
            ['Филиал', ColumnRole::Branch],
            ['НДС', ColumnRole::Tax],
            ['Продавец', ColumnRole::Performer],
            ['Цена', ColumnRole::UnitPrice],
            ['Գին', ColumnRole::UnitPrice],
            ['Сумма', ColumnRole::Amount],
            ['Количество', ColumnRole::Quantity],
            ['Объект', ColumnRole::Project],
        ];
    }

    #[DataProvider('headers')]
    public function test_a_header_is_read_in_armenian_russian_and_english(string $header, ColumnRole $role): void
    {
        $this->assertSame($role, ColumnRole::guess($header), $header);
    }

    public function test_values_tell_the_role_when_the_header_says_nothing(): void
    {
        $this->assertSame(ColumnRole::Model, ColumnValues::guess(['iPhone 15 Pro', 'Galaxy A54', 'Redmi Note 12', 'iPhone 13']));
        $this->assertSame(ColumnRole::Brand, ColumnValues::guess(['Apple', 'Samsung', 'Xiaomi']));
        $this->assertSame(ColumnRole::Country, ColumnValues::guess(['Китай', 'Турция', 'Китай', 'Россия']));
        $this->assertSame(ColumnRole::Time, ColumnValues::guess(['14:30', '09:00', '18:15']));
        $this->assertSame(ColumnRole::Payment, ColumnValues::guess(['Կանխիկ', 'Քարտ', 'Կանխիկ']));
        $this->assertSame(ColumnRole::City, ColumnValues::guess(['Երևան', 'Գյումրի', 'Երևան']));
        $this->assertSame(ColumnRole::Category, ColumnValues::guess(['Налоги', 'Аренда', 'Зарплата'], DataTableSection::Expenses));
        // Product names with one colour among them are no colour column.
        $this->assertNull(ColumnValues::guess(['Чехол силиконовый', 'Стекло защитное', 'Красный']));
        // A role another column has isn't given twice.
        $this->assertNull(ColumnValues::guess(['Apple', 'Samsung'], null, ['brand' => true]));
    }

    public function test_a_table_made_from_a_file_gets_the_roles_of_headers_and_values(): void
    {
        $columns = ColumnMatcher::guessColumns(
            ['Дата', 'Колонка 2', 'Наставник', 'Страна', 'Цена', 'Скидка', 'Итоговая цена'],
            [
                ['17.10.2026', 'iPhone 15', 'Арам', 'Китай', '5000', '500', '4500'],
                ['16.10.2026', 'Galaxy S24', 'Ани', 'Турция', '4000', '0', '4000'],
                ['15.10.2026', 'iPhone 13', 'Арам', 'Китай', '3000', '300', '2700'],
            ],
            DataTableSection::Sales,
        );

        $this->assertSame(['date', 'model', 'intermediary', 'country', 'unit_price', 'discount', 'amount'], array_column($columns, 'role'));
        $this->assertSame(['date', 'text', 'text', 'text', 'money', 'money', 'money'], array_column($columns, 'type'));
    }

    public function test_the_final_price_comes_from_price_quantity_discount_and_delivery(): void
    {
        $table = $this->table('sales', [
            ['label' => 'Ապրանք', 'type' => 'text'],
            ['label' => 'Ամսաթիվ', 'type' => 'date'],
            ['label' => 'Ժամ', 'type' => 'text'],
            ['label' => 'Գին', 'type' => 'money'],
            ['label' => 'Քանակ', 'type' => 'number'],
            ['label' => 'Զեղչ', 'type' => 'money'],
            ['label' => 'Զեղչ, %', 'type' => 'number'],
            ['label' => 'Առաքման վճար', 'type' => 'money'],
        ]);

        $this->assertSame(['item', 'date', 'time', 'unit_price', 'quantity', 'discount', 'discount_percent', 'delivery_fee'], array_column($table['columns'], 'role'));

        $this->row($table, ['Պատյան', '2026-10-17', '14:30', 1000, 2, 200, null, 500]);
        $this->row($table, ['Պատյան', '2026-10-16', null, 1000, 3, null, '10%', null]);

        $rows = DataTableRow::withoutTenancy()->orderBy('id')->get();
        // 1000 × 2 − 200 + 500, at the time of its own column.
        $this->assertEquals(2300, $rows[0]->amount);
        $this->assertSame('2026-10-17 14:30:00', $rows[0]->getRawOriginal('occurred_at'));
        // 1000 × 3 less 10%.
        $this->assertEquals(2700, $rows[1]->amount);
        $this->assertSame('2026-10-16 00:00:00', $rows[1]->getRawOriginal('occurred_at'));
    }

    public function test_a_commission_comes_off_the_profit_and_discounts_are_summed(): void
    {
        $table = $this->table('sales', [
            ['label' => 'Մոդել', 'type' => 'text'],
            ['label' => 'Ամսաթիվ', 'type' => 'date'],
            ['label' => 'Վերջնական գին', 'type' => 'money'],
            ['label' => 'Զեղչ', 'type' => 'money'],
            ['label' => 'Միջնորդ', 'type' => 'text'],
            ['label' => 'Միջնորդավճար', 'type' => 'money'],
        ]);
        $this->row($table, ['iPhone 15', '2026-10-16', 4500, 500, 'Արամ', 450]);
        $this->row($table, ['iPhone 15', '2026-10-15', 4000, null, 'Անի', 400]);
        $this->row($table, ['Galaxy S24', '2026-10-14', 3000, 300, 'Արամ', null]);

        $summary = $this->getJson('/api/v1/admin/analytics/statistics?period=30')->assertOk()->json('data.0');
        $metric = fn (string $key) => collect($summary['metrics'])->firstWhere('key', $key)['value'] ?? null;

        $this->assertSame(11500, $metric('revenue'));
        // Profit of the two rows with a commission: 8500 − 850.
        $this->assertSame(7650, $metric('profit'));
        $this->assertSame(800, $metric('discounts'));
        $this->assertSame(850, $metric('commission'));

        $byModel = collect($summary['breakdowns'])->firstWhere('role', 'model');
        $this->assertSame(['iPhone 15', 'Galaxy S24'], array_column($byModel['values'], 'value'));
        $this->assertNotNull(collect($summary['breakdowns'])->firstWhere('role', 'intermediary'));

        // The AI groups by the meaning and knows what it means.
        $this->asTenant($this->shop, function () {
            $tools = app(BusinessTools::class);
            $this->assertSame('intermediary', array_key_first(array_filter($tools->run('list_tables', [])['meanings'], fn ($m, $k) => $k === 'intermediary', ARRAY_FILTER_USE_BOTH)));
            $groups = $tools->run('group_rows', ['from' => '2026-10-01', 'to' => '2026-10-17', 'by' => 'intermediary', 'section' => 'sales'])['groups'];
            $this->assertSame(['Արամ', 'Անի'], array_column($groups, 'group'));
            $this->assertSame(450, $groups[0]['commission']);
            $this->assertSame(4050, $groups[0]['profit']);
        });
    }

    public function test_the_designer_gets_roles_for_columns_that_have_none(): void
    {
        $table = $this->table('sales', [
            ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
            ['label' => 'Колонка 2', 'type' => 'text', 'role' => null],
            ['label' => 'Наставник', 'type' => 'text', 'role' => null],
            ['label' => 'Գին', 'type' => 'money', 'role' => 'unit_price'],
        ]);
        $this->row($table, ['2026-10-16', 'iPhone 15', 'Արամ', 1000]);
        $this->row($table, ['2026-10-15', 'Galaxy S24', 'Անի', 1000]);

        $suggestions = $this->getJson("/api/v1/admin/data-tables/{$table['id']}/role-suggestions")->assertOk()->json('data');

        $this->assertSame([
            ['key' => $table['columns'][1]['key'], 'role' => 'model'],
            ['key' => $table['columns'][2]['key'], 'role' => 'intermediary'],
        ], $suggestions);
    }

    private function table(string $section, array $columns): array
    {
        return $this->postJson('/api/v1/admin/data-tables', ['section' => $section, 'name' => 'Վաճառքներ', 'columns' => $columns])->assertCreated()->json('data');
    }

    private function row(array $table, array $row): void
    {
        $values = array_combine(array_column($table['columns'], 'key'), $row);
        $this->postJson("/api/v1/admin/data-tables/{$table['id']}/rows", ['values' => $values])->assertCreated();
    }
}

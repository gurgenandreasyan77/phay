<?php

namespace Tests\Feature\Admin;

use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;
use ZipArchive;

/**
 * Files the business's admin downloads: tables and the owner's reports as
 * XLSX or CSV, made on the server from this business's data only.
 */
class ExportsTest extends TestCase
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
        app(TenantContext::class)->set($this->shop);
        $this->travelTo('2026-10-20 12:00:00');
    }

    private function table(string $name = 'Վաճառքներ', string $section = 'sales'): DataTable
    {
        $id = $this->postJson('/api/v1/admin/data-tables', [
            'section' => $section,
            'name' => $name,
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'datetime', 'role' => 'date'],
                ['label' => 'Ապրանք', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
                ['label' => 'Վճարված', 'type' => 'boolean'],
            ],
        ])->assertCreated()->json('data.id');
        $table = DataTable::query()->findOrFail($id);
        [$at, $item, $amount, $paid] = array_column($table->columns, 'key');
        DataTableRow::create(['data_table_id' => $id, 'data' => [$at => '2026-10-17 14:30', $item => '=HYPERLINK("x")', $amount => 1500.5, $paid => true]]);
        DataTableRow::create(['data_table_id' => $id, 'data' => [$at => '2026-10-02', $item => 'Մոմ & <օճառ>', $amount => 9900, $paid => false]]);

        return $table;
    }

    /** The file a download response sends. */
    private function file($response): string
    {
        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);

        return file_get_contents($base->getFile()->getPathname());
    }

    /** @return array<string, string> the workbook's parts */
    private function unzip(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $parts = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($path);

        foreach ($parts as $name => $xml) {
            if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                $this->assertNotFalse(simplexml_load_string($xml), "{$name} is well-formed XML");
            }
        }

        return $parts;
    }

    public function test_a_table_downloads_as_xlsx_with_real_numbers_and_dates(): void
    {
        $table = $this->table();

        $response = $this->get("/api/v1/admin/data-tables/{$table->id}/export")->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("filename*=utf-8''".rawurlencode('Վաճառքներ.xlsx'), $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $parts = $this->unzip($this->file($response));
        $sheet = $parts['xl/worksheets/sheet1.xml'];
        $this->assertStringContainsString('Վաճառքներ', $parts['xl/workbook.xml']);
        // Rows by date: 2 October first; dates as serial numbers, money as numbers, text escaped and never a formula.
        $this->assertMatchesRegularExpression('/<c r="A2" s="2"><v>46297<\/v><\/c>/', $sheet);
        $this->assertMatchesRegularExpression('/<c r="A3" s="3"><v>46312\.604166/', $sheet);
        $this->assertStringContainsString('<c r="C3" s="5"><v>1500.5</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C2" s="4"><v>9900</v></c>', $sheet);
        $this->assertStringContainsString('Մոմ &amp; &lt;օճառ&gt;', $sheet);
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=HYPERLINK', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);
        $this->assertStringContainsString('Այո', $sheet);
    }

    public function test_a_table_downloads_as_csv_that_imports_back(): void
    {
        $table = $this->table();

        $response = $this->get("/api/v1/admin/data-tables/{$table->id}/export?format=csv")->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $csv = $this->file($response);

        $this->assertStringStartsWith("\xEF\xBB\xBFԱմսաթիվ,Ապրանք,Գումար,Վճարված\n", $csv);
        $this->assertStringContainsString("2026-10-02,\"Մոմ & <օճառ>\",9900,Ոչ\n", $csv);
        // A formula is written as text.
        $this->assertStringContainsString("\"2026-10-17 14:30\",\"'=HYPERLINK(\"\"x\"\")\",1500.5,Այո", $csv);
    }

    public function test_several_tables_make_one_workbook(): void
    {
        $sales = $this->table();
        $orders = $this->table('Պատվերներ', 'orders');

        $parts = $this->unzip($this->file($this->get("/api/v1/admin/data-tables/export?ids={$sales->id},{$orders->id}")->assertOk()));
        $this->assertArrayHasKey('xl/worksheets/sheet2.xml', $parts);
        $this->assertStringContainsString('name="Պատվերներ"', $parts['xl/workbook.xml']);

        // A CSV holds one table.
        $this->get("/api/v1/admin/data-tables/export?ids={$sales->id},{$orders->id}&format=csv")->assertStatus(422);
    }

    public function test_only_the_business_admin_downloads_its_own_tables(): void
    {
        $table = $this->table();
        $other = $this->createTenant('other-shop');
        $foreign = $this->asTenant($other, fn () => DataTable::create(['section' => 'sales', 'name' => 'X', 'columns' => []]));

        $this->getJson("/api/v1/admin/data-tables/{$foreign->id}/export")->assertNotFound();
        $this->getJson("/api/v1/admin/data-tables/export?ids={$table->id},{$foreign->id}")->assertNotFound();

        // A moderator fills tables but doesn't take them away.
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'moderator')->create());
        $this->getJson("/api/v1/admin/data-tables/{$table->id}/export")->assertForbidden();

        // An admin downloads orders and sales, not the expenses or the reports.
        Sanctum::actingAs($this->owner);
        $costs = $this->table('Ծախսեր', 'expenses');
        Sanctum::actingAs(User::factory()->forTenant($this->shop, 'admin')->create());
        $this->get("/api/v1/admin/data-tables/{$table->id}/export")->assertOk();
        $this->getJson("/api/v1/admin/data-tables/{$costs->id}/export")->assertNotFound();
        $this->getJson('/api/v1/admin/analytics/finance/export')->assertForbidden();
        $this->getJson('/api/v1/admin/expenses/statistics/export')->assertForbidden();
    }

    public function test_the_owner_downloads_the_profit_and_expenses_reports(): void
    {
        $this->table();
        $this->table('Ծախսեր', 'expenses');

        $finance = $this->get('/api/v1/admin/analytics/finance/export?by=quarter')->assertOk();
        $parts = $this->unzip($this->file($finance));
        $this->assertStringContainsString('Շահույթ', $parts['xl/workbook.xml']);
        $this->assertStringContainsString('Ըստ տեսակի', $parts['xl/workbook.xml']);
        $this->assertStringContainsString('Ընդամենը', $parts['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('11400.5', $parts['xl/worksheets/sheet1.xml']);

        $expenses = $this->get('/api/v1/admin/expenses/statistics/export?by=month&date=2026-10-01&format=csv')->assertOk();
        $csv = $this->file($expenses);
        $this->assertStringContainsString('Ամսաթիվ,Տեսակ', $csv);
        $this->assertStringContainsString('2026-10-02', $csv);
        $this->assertStringContainsString('Ընդամենը', $csv);
        $this->assertStringContainsString(rawurlencode('Ծախսեր · Հոկտեմբեր 2026.csv'), $expenses->headers->get('Content-Disposition'));
    }
}

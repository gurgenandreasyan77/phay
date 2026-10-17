<?php

namespace Tests\Feature\Admin;

use App\Models\DataImport;
use App\Models\DataTableRow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;
use ZipArchive;

/**
 * Filling a business's own table from CSV, XLSX and XML files.
 */
class AdminDataImportTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private array $table;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->shop = $this->createTenant('test-shop');
        Feature::for($this->shop)->activate('sales');
        Sanctum::actingAs(User::factory()->forTenant($this->shop)->create());

        $this->table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales',
            'name' => 'Վաճառքներ',
            'columns' => [
                ['label' => 'Ապրանք', 'type' => 'text', 'required' => true],
                ['label' => 'Քանակ', 'type' => 'number'],
                ['label' => 'Գին', 'type' => 'money'],
                ['label' => 'Ամսաթիվ', 'type' => 'date'],
            ],
        ])->assertCreated()->json('data');
    }

    private function key(int $index): string
    {
        return $this->table['columns'][$index]['key'];
    }

    private function upload(UploadedFile $file, bool $forTable = true): array
    {
        return $this->post('/api/v1/admin/data-imports', array_filter([
            'file' => $file,
            'data_table_id' => $forTable ? $this->table['id'] : null,
        ]), ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    private function csv(string $content, string $name = 'sales.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** A minimal .xlsx: shared strings, a number, a date-formatted cell, an inline string. */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $strings = [];
        $sheetRows = '';
        foreach ($rows as $r => $row) {
            $cells = '';
            foreach ($row as $c => $value) {
                $ref = chr(65 + $c).($r + 1);
                if (is_array($value)) { // ['date' => serial]
                    $cells .= "<c r=\"{$ref}\" s=\"1\"><v>{$value['date']}</v></c>";
                } elseif (is_int($value) || is_float($value)) {
                    $cells .= "<c r=\"{$ref}\"><v>{$value}</v></c>";
                } else {
                    $strings[] = htmlspecialchars($value);
                    $cells .= "<c r=\"{$ref}\" t=\"s\"><v>".(count($strings) - 1).'</v></c>';
                }
            }
            $sheetRows .= '<row r="'.($r + 1)."\">{$cells}</row>";
        }

        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $zip->addFromString('xl/workbook.xml', "<workbook {$ns} xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheets><sheet name=\"Sheet1\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>");
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', "<worksheet {$ns}><sheetData>{$sheetRows}</sheetData></worksheet>");
        $zip->addFromString('xl/sharedStrings.xml', "<sst {$ns}>".implode('', array_map(fn ($s) => "<si><t>{$s}</t></si>", $strings)).'</sst>');
        $zip->addFromString('xl/styles.xml', "<styleSheet {$ns}><cellXfs count=\"2\"><xf numFmtId=\"0\"/><xf numFmtId=\"14\"/></cellXfs></styleSheet>");
        $zip->close();

        return UploadedFile::fake()->createWithContent('sales.xlsx', file_get_contents($path));
    }

    public function test_a_csv_is_read_and_its_columns_are_matched_by_name(): void
    {
        $preview = $this->upload($this->csv("\xEF\xBB\xBFամսաթիվ;Ապրանք;Գին (֏);Նշում\n17.10.2026;Մոմ;4 500;\n\n18.10.2026;Օճառ;1200;շտապ\n"));

        $this->assertSame(['ամսաթիվ', 'Ապրանք', 'Գին (֏)', 'Նշում'], $preview['headers']);
        $this->assertSame(2, $preview['totalRows']);
        $this->assertSame(['17.10.2026', 'Մոմ', '4 500', ''], $preview['sampleRows'][0]);
        $this->assertSame([
            $this->key(0) => 1,
            $this->key(1) => null,
            $this->key(2) => 2,
            $this->key(3) => 0,
        ], $preview['mapping']);
    }

    public function test_an_import_adds_valid_rows_and_reports_the_rest(): void
    {
        $preview = $this->upload($this->csv("Ապրանք,Քանակ,Գին,Ամսաթիվ\nՄոմ,2,\"4,500\",2026-10-17\n,1,100,2026-10-17\nՕճառ,շատ,1200,2026-10-18\nԲաժակ,1,900,45947\n"));

        $report = $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/run", [
            'data_table_id' => $this->table['id'],
            'mapping' => $preview['mapping'],
        ])->assertOk()->json('data');

        $this->assertSame('done', $report['status']);
        $this->assertSame(2, $report['importedRows']);
        $this->assertSame(2, $report['skippedRows']);
        $this->assertSame(['line' => 3, 'errors' => ['Ապրանք' => 'Պարտադիր դաշտ է։']], $report['errors'][0]);
        $this->assertSame(4, $report['errors'][1]['line']);
        $this->assertArrayHasKey('Քանակ', $report['errors'][1]['errors']);

        $rows = $this->getJson("/api/v1/admin/data-tables/{$this->table['id']}/rows")->assertOk()->json('data');
        $this->assertSame('import', $rows[0]['source']);
        $this->assertSame([$this->key(0) => 'Բաժակ', $this->key(1) => 1, $this->key(2) => 900, $this->key(3) => '2025-10-17'], $rows[0]['values']);
        $this->assertSame(4500, $rows[1]['values'][$this->key(2)]);

        // The file is gone after the run, and it can't be run twice.
        Storage::disk('local')->assertDirectoryEmpty('imports/'.$this->shop->id);
        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/run", [
            'data_table_id' => $this->table['id'], 'mapping' => $preview['mapping'],
        ])->assertStatus(409);
    }

    public function test_an_import_can_be_taken_back(): void
    {
        $preview = $this->upload($this->csv("Ապրանք\nՄոմ\nՕճառ\n"));
        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/run", [
            'data_table_id' => $this->table['id'], 'mapping' => $preview['mapping'],
        ])->assertOk();
        $this->postJson("/api/v1/admin/data-tables/{$this->table['id']}/rows", ['values' => [$this->key(0) => 'Ձեռքով']])->assertCreated();

        $this->getJson("/api/v1/admin/data-tables/{$this->table['id']}/imports")
            ->assertOk()
            ->assertJsonPath('data.0.importedRows', 2)
            ->assertJsonPath('data.0.fileName', 'sales.csv');

        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/undo")->assertOk()->assertJsonPath('data.status', 'undone');

        $this->assertSame(['Ձեռքով'], DataTableRow::withoutTenancy()->get()->pluck('data.'.$this->key(0))->all());
        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/undo")->assertStatus(409);
    }

    public function test_an_xlsx_is_read_with_its_dates(): void
    {
        $preview = $this->upload($this->xlsx([
            ['Ապրանք', 'Քանակ', 'Գին', 'Ամսաթիվ'],
            ['Մոմ', 2, 4500.0000000001, ['date' => 46312]],
            ['Օճառ', 1, 1200, ['date' => 46313]],
        ]));

        $this->assertSame(['Մոմ', '2', '4500', '2026-10-17'], $preview['sampleRows'][0]);

        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/run", [
            'data_table_id' => $this->table['id'], 'mapping' => $preview['mapping'],
        ])->assertOk()->assertJsonPath('data.importedRows', 2);
    }

    public function test_an_xml_list_of_records_is_read(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <export><meta><source>shop</source></meta>
              <sales>
                <sale id="1"><product>Մոմ</product><qty>2</qty><price>4500</price></sale>
                <sale id="2"><product>Օճառ</product><qty>1</qty><price>1200</price></sale>
                <sale id="3"><product>Բաժակ</product><price>900</price></sale>
              </sales>
            </export>
            XML;

        $preview = $this->upload(UploadedFile::fake()->createWithContent('sales.xml', $xml));

        $this->assertSame(['id', 'product', 'qty', 'price'], $preview['headers']);
        $this->assertSame(3, $preview['totalRows']);
        $this->assertSame(['3', 'Բաժակ', '', '900'], $preview['sampleRows'][2]);
    }

    public function test_an_excel_2003_xml_spreadsheet_is_read(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
             <Worksheet ss:Name="Sheet1"><Table>
              <Row><Cell><Data ss:Type="String">Ապրանք</Data></Cell><Cell><Data ss:Type="String">Գին</Data></Cell></Row>
              <Row><Cell><Data ss:Type="String">Մոմ</Data></Cell><Cell><Data ss:Type="Number">4500</Data></Cell></Row>
             </Table></Worksheet>
            </Workbook>
            XML;

        $preview = $this->upload(UploadedFile::fake()->createWithContent('sales.xml', $xml));

        $this->assertSame(['Ապրանք', 'Գին'], $preview['headers']);
        $this->assertSame([['Մոմ', '4500']], $preview['sampleRows']);
    }

    public function test_column_types_are_guessed_for_a_table_made_from_a_file(): void
    {
        $lines = ['Date,Product,Price,Qty,Paid,Payment'];
        for ($i = 1; $i <= 12; $i++) {
            $lines[] = sprintf('%02d.10.2026,Item %d,%d,%d,%s,%s', $i, $i, 1000 * $i, $i, $i % 2 ? 'yes' : 'no', $i % 3 ? 'Cash' : 'Card');
        }

        $preview = $this->upload($this->csv(implode("\n", $lines)), forTable: false);

        $this->assertNull($preview['mapping']);
        $this->assertSame(
            ['date', 'text', 'money', 'number', 'boolean', 'select'],
            array_column($preview['guessedColumns'], 'type'),
        );
        $this->assertSame(['Card', 'Cash'], $preview['guessedColumns'][5]['options']);

        // Made from the file, the table maps every column back to it.
        $table = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'sales', 'name' => 'From file', 'columns' => $preview['guessedColumns'],
        ])->assertCreated()->json('data');

        $mapped = $this->getJson("/api/v1/admin/data-imports/{$preview['id']}?data_table_id={$table['id']}")
            ->assertOk()->json('data.mapping');
        $this->assertSame([0, 1, 2, 3, 4, 5], array_values($mapped));

        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/run", [
            'data_table_id' => $table['id'], 'mapping' => $mapped,
        ])->assertOk()->assertJsonPath('data.importedRows', 12)->assertJsonPath('data.skippedRows', 0);
    }

    public function test_unsupported_or_broken_files_are_refused(): void
    {
        $this->post('/api/v1/admin/data-imports', ['file' => UploadedFile::fake()->create('photo.jpg', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->post('/api/v1/admin/data-imports', ['file' => $this->csv('not a zip', 'broken.xlsx')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->post('/api/v1/admin/data-imports', ['file' => $this->csv('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>', 'evil.xml')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->post('/api/v1/admin/data-imports', ['file' => $this->csv('', 'empty.csv')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame(0, DataImport::query()->count());
    }

    public function test_another_business_cannot_see_or_run_an_import(): void
    {
        $preview = $this->upload($this->csv("Ապրանք\nՄոմ\n"));

        $other = $this->createTenant('other-shop');
        Feature::for($other)->activate('sales');
        Sanctum::actingAs(User::factory()->forTenant($other)->create());

        $this->getJson("/api/v1/admin/data-imports/{$preview['id']}")->assertNotFound();
        $this->postJson("/api/v1/admin/data-imports/{$preview['id']}/run", [
            'data_table_id' => $this->table['id'], 'mapping' => $preview['mapping'],
        ])->assertNotFound();
        $this->getJson("/api/v1/admin/data-tables/{$this->table['id']}/imports")->assertNotFound();
    }
}

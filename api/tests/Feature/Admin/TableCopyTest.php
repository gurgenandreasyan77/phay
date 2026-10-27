<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\TableEditTools;
use App\DataTables\Import\TableSource;
use App\Exports\Sheet;
use App\Exports\XlsxWriter;
use App\Media\HostResolver;
use App\Models\DataTable;
use App\Models\TableChange;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Fakes\FakeHostResolver;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Sona making a table like one the owner has elsewhere: a Google Sheets
 * link, a CSV or Excel link or file, a page with a table. The same columns
 * in the same order with what they mean, every row, as one card the owner
 * applies; a private sheet is explained, not guessed.
 */
class TableCopyTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private FakeAssistantClient $client;

    private const ORDERS_CSV = "Ամսաթիվ,Ժամ,Ծառայություն,Կատարող,Գին,Հասցե,Հեռախոս\n"
        ."05.10.2026,10:00,Բազմոցի մաքրում,Արամ,9900,Կոմիտաս 5,055337633\n"
        ."06.10.2026,14:30,Ներքնակի մաքրում,Գոռ,\"12 000\",Աբովյան 10,091112233\n"
        ."07.10.2026,11:00,Աթոռներ,Արամ,չգիտեմ,,\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->shop = $this->createTenant('test-shop');
        $owner = User::factory()->forTenant($this->shop, 'owner')->create();
        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);
        $this->app->instance(HostResolver::class, new FakeHostResolver([]));
        Http::preventStrayRequests();

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/tables", ['active' => true])->assertOk();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($this->shop);
    }

    /** Asks Sona with the given tool calls; returns the answer. */
    private function ask(array $calls, array $attachments = []): array
    {
        $this->client->toolCalls = $calls;
        $this->client->toolResults = [];

        return $this->postJson('/api/v1/admin/assistant', array_filter([
            'question' => 'Ստեղծիր նույնպիսի աղյուսակ',
            'agent' => 'tables',
            'attachments' => $attachments,
        ]))->assertOk()->json('data');
    }

    /** What the model is told when copy_table can't copy. */
    private function refusal(array $input): string
    {
        try {
            app(TableEditTools::class)->run('copy_table', $input);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        $this->fail('The table was copied.');
    }

    public function test_google_sheets_links_become_their_csv_export(): void
    {
        $this->assertSame(
            ['export' => 'https://docs.google.com/spreadsheets/d/1AbC_d-9/export?format=csv&gid=42'],
            TableSource::googleSheet('https://docs.google.com/spreadsheets/d/1AbC_d-9/edit#gid=42'),
        );
        $this->assertSame(
            ['export' => 'https://docs.google.com/spreadsheets/d/1AbC_d-9/export?format=csv'],
            TableSource::googleSheet('https://docs.google.com/spreadsheets/d/1AbC_d-9/edit?usp=sharing'),
        );
        $this->assertSame(
            ['export' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1x/pub?output=csv&gid=7'],
            TableSource::googleSheet('https://docs.google.com/spreadsheets/d/e/2PACX-1x/pubhtml?gid=7&single=true'),
        );
        $this->assertNull(TableSource::googleSheet('https://docs.google.com/document/d/1AbC/edit'));
        $this->assertNull(TableSource::googleSheet('https://example.com/spreadsheets/d/1AbC/edit'));
    }

    public function test_a_shared_google_sheet_is_copied_column_by_column_and_applied_from_the_card(): void
    {
        Http::fake([
            'https://docs.google.com/spreadsheets/d/1AbC/export?format=csv&gid=0' => Http::response(self::ORDERS_CSV, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="Պատվերներ - Հոկտեմբեր.csv"; filename*=UTF-8\'\'%D5%8A%D5%A1%D5%BF%D5%BE%D5%A5%D6%80%D5%B6%D5%A5%D6%80%20-%20%D5%80%D5%B8%D5%AF%D5%BF%D5%A5%D5%B4%D5%A2%D5%A5%D6%80.csv',
            ]),
        ]);

        $reply = $this->ask([['copy_table', ['url' => 'https://docs.google.com/spreadsheets/d/1AbC/edit#gid=0', 'section' => 'orders']]]);
        $result = $this->client->toolResults[0];

        // Nothing is made before the press.
        $this->assertSame('change', $reply['actions'][0]['type']);
        $this->assertSame(0, DataTable::query()->count());
        $this->assertSame(
            ['Ամսաթիվ', 'Ժամ', 'Ծառայություն', 'Կատարող', 'Գին', 'Հասցե', 'Հեռախոս'],
            array_column($result['copied']['columns'], 'label'),
        );
        $meanings = array_column($result['copied']['columns'], 'meaning', 'label');
        $this->assertSame('date', $meanings['Ամսաթիվ']);
        $this->assertSame('performer', $meanings['Կատարող']);
        // One word among the prices doesn't make them text.
        $this->assertSame('unit_price', $meanings['Գին']);
        $this->assertSame(3, $result['copied']['rows']);
        // «չգիտեմ» is no price: left empty and said, the row stays.
        $this->assertSame(['Գին' => 1], $result['copied']['cellsLeftEmpty']);
        $this->assertArrayNotHasKey('noDateColumn', $result['copied']);

        $this->postJson("/api/v1/admin/table-changes/{$reply['actions'][0]['id']}/apply")->assertOk()->assertJsonPath('data.status', 'applied');

        $table = DataTable::query()->sole();
        $this->assertSame('Պատվերներ · Հոկտեմբեր', $table->name);
        $this->assertSame('orders', $table->section->value);
        $this->assertSame(3, $table->rows()->count());
        $first = $table->rows()->orderBy('id')->first();
        $this->assertStringStartsWith('2026-10-05', (string) $first->occurred_at);
        $columns = array_column($table->columns, 'key', 'label');
        $this->assertSame('Կոմիտաս 5', $first->data[$columns['Հասցե']]);
        $this->assertSame('055337633', (string) $first->data[$columns['Հեռախոս']]);
        $this->assertSame(9900.0 + 12000, (float) $table->rows()->sum('amount'));
        // No date of today was made up for any row.
        $this->assertSame(0, $table->rows()->whereDate('occurred_at', now()->toDateString())->count());
    }

    public function test_a_private_google_sheet_is_explained(): void
    {
        Http::fake([
            'https://docs.google.com/*' => Http::response('<html><title>Sign in</title></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $this->assertStringContainsString('Anyone with the link', $this->refusal(['url' => 'https://docs.google.com/spreadsheets/d/1Private/edit', 'section' => 'orders']));
        $this->assertSame(0, TableChange::query()->count());
    }

    public function test_columns_can_be_corrected_or_left_out(): void
    {
        Http::fake([
            'https://files.example.com/orders.csv' => Http::response(self::ORDERS_CSV, 200, ['Content-Type' => 'application/octet-stream']),
        ]);

        $this->ask([['copy_table', [
            'url' => 'https://files.example.com/orders.csv',
            'section' => 'orders',
            'name' => 'Հոկտեմբեր 2026',
            'columns' => [['from' => 'Կատարող', 'label' => 'Վարպետ'], ['from' => 'Գին', 'type' => 'money', 'meaning' => 'amount']],
            'skip' => ['Հեռախոս'],
        ]]]);
        $result = $this->client->toolResults[0];

        $this->assertSame(['Ամսաթիվ', 'Ժամ', 'Ծառայություն', 'Վարպետ', 'Գին', 'Հասցե'], array_column($result['copied']['columns'], 'label'));
        $this->assertSame('Նոր աղյուսակ՝ «Հոկտեմբեր 2026», 3 տողով', $result['card']['title']);
    }

    public function test_a_page_with_a_table_is_read(): void
    {
        $html = '<html><body><table><tr><td>menu</td></tr></table>'
            .'<table><tr><th>Ապրանք</th><th>Քանակ</th><th>Գին</th></tr>'
            .'<tr><td>Մոմ</td><td>2</td><td>1500</td></tr><tr><td>Օճառ</td><td>1</td><td>700</td></tr></table></body></html>';
        Http::fake(['https://shop.example.com/prices' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        $this->ask([['copy_table', ['url' => 'https://shop.example.com/prices', 'section' => 'sales']]]);
        $result = $this->client->toolResults[0];

        $this->assertSame(['Ապրանք', 'Քանակ', 'Գին'], array_column($result['copied']['columns'], 'label'));
        $this->assertSame(2, $result['copied']['rows']);
        $this->assertTrue($result['copied']['noDateColumn']);
    }

    public function test_an_attached_excel_file_is_copied(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        (new XlsxWriter)->write([new Sheet('Ծախսեր', [
            Sheet::column('Ամսաթիվ', Sheet::DATE),
            Sheet::column('Ծախս'),
            Sheet::column('Գումար', Sheet::MONEY),
        ], [
            ['2026-10-01', 'Վարձ', 150000],
            ['2026-10-03', 'Քիմիա', 25000],
        ])], $path);

        $file = $this->post('/api/v1/admin/assistant/attachments', [
            'file' => UploadedFile::fake()->createWithContent('Ծախսեր.xlsx', file_get_contents($path)),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.kind', 'sheet')->json('data.id');
        @unlink($path);

        $reply = $this->ask([['copy_table', ['file' => $file, 'section' => 'expenses']]], [$file]);
        $result = $this->client->toolResults[0];

        $this->assertSame(['Ամսաթիվ', 'Ծախս', 'Գումար'], array_column($result['copied']['columns'], 'label'));
        $this->postJson("/api/v1/admin/table-changes/{$reply['actions'][0]['id']}/apply")->assertOk();
        $table = DataTable::query()->sole();
        $this->assertSame('Ծախսեր', $table->name);
        $this->assertSame(175000.0, (float) $table->rows()->sum('amount'));

        // The model reads the sheet as text.
        $this->assertStringContainsString('Վարձ', json_encode($this->client->messages, JSON_UNESCAPED_UNICODE));
    }

    public function test_a_link_that_is_no_table_is_refused(): void
    {
        Http::fake(['https://example.com/photo.jpg' => Http::response('jpeg', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->assertStringContainsString('not a table', $this->refusal(['url' => 'https://example.com/photo.jpg', 'section' => 'orders']));
        $this->assertSame(0, TableChange::query()->count());
    }
}

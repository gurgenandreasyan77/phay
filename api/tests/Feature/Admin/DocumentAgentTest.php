<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\Attachments;
use App\Documents\DocumentReader;
use App\Documents\DocumentStudio;
use App\Documents\DocxWriter;
use App\Documents\TemplateFiller;
use App\Media\DocumentConverter;
use App\Models\AssistantAttachment;
use App\Models\AssistantConversation;
use App\Models\DataTable;
use App\Models\DataTableRow;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Fakes\FakeDocumentConverter;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;
use ZipArchive;

/**
 * Anna, the documents agent: every agent reads Word, ODT, RTF, PowerPoint
 * and web pages; Anna fills the owner's templates without changing their
 * look (one copy, or one for every order or employee as a ZIP and a file
 * to print), and writes new Word documents. The files come under her
 * answer for the person who asked.
 */
class DocumentAgentTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private Tenant $shop;

    private User $owner;

    private FakeAssistantClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 10:00:00');
        Storage::fake(Attachments::DISK);
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop, 'owner')->create();
        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);

        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/documents", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
        app(TenantContext::class)->set($this->shop);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * A Word file as Word writes one: a phrase split over runs (bold in the
     * middle, a spell-check mark), a tab, blanks, a table and a header.
     */
    private static function template(): string
    {
        $w = DocumentReader::W;
        $body = '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>ՀԱՄԱՁԱՅՆԱԳԻՐ № 15</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t xml:space="preserve">Աշխատող՝ </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>Արմ</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:rPr><w:b/></w:rPr><w:t>ան Պետրոսյան</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:t>,</w:t></w:r><w:r><w:tab/><w:t>պաշտոն՝ ________</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Ազատման օր՝ ________</w:t></w:r></w:p>'
            .'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Հաճախորդ</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Արման Պետրոսյան</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
            .'<w:sectPr><w:headerReference w:type="default" r:id="rId9"/></w:sectPr>';

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="'.$w.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'</w:body></w:document>',
            'word/header1.xml' => '<?xml version="1.0" encoding="UTF-8"?><w:hdr xmlns:w="'.$w.'"><w:p><w:r><w:t>ՍՊԸ «Հին անուն»</w:t></w:r></w:p></w:hdr>',
        ]);
    }

    private static function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    /** @return array<string, string> a ZIP's files by name */
    private static function unzip(string $content): array
    {
        return DocumentReader::withZip($content, function (ZipArchive $zip) {
            $files = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
            }

            return $files;
        });
    }

    private function upload(string $name, string $content): array
    {
        return $this->postJson('/api/v1/admin/assistant/attachments', ['file' => UploadedFile::fake()->createWithContent($name, $content)])
            ->assertCreated()->json('data');
    }

    private function ask(array $calls, array $attachments = [], ?int $conversation = null): array
    {
        $this->client->toolCalls = $calls;
        $this->client->toolResults = [];

        return $this->postJson('/api/v1/admin/assistant', array_filter([
            'question' => 'Արա',
            'agent' => $conversation ? null : 'documents',
            'attachments' => $attachments,
            'conversation_id' => $conversation,
        ]))->assertOk()->json('data');
    }

    private function stored(int $id): string
    {
        return Storage::disk(Attachments::DISK)->get(AssistantAttachment::withoutTenancy()->findOrFail($id)->path);
    }

    private function text(string $docx): string
    {
        return (new DocumentReader)->text($docx, 'docx');
    }

    public function test_office_documents_are_attached_and_read_by_every_agent(): void
    {
        $docx = $this->upload('contract.docx', self::template());
        $this->assertSame('document', $docx['kind']);
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $docx['mime']);

        $odt = $this->upload('act.odt', self::zip([
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'content.xml' => '<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="'.DocumentReader::TEXT.'"><office:body><office:text><text:h>Ակտ</text:h><text:p>Գումար՝<text:s text:c="2"/>5900 դր</text:p></office:text></office:body></office:document-content>',
        ]));
        $rtf = $this->upload('letter.rtf', <<<'RTF'
        {\rtf1\ansi\ansicpg1251{\fonttbl{\f0 Arial;}}{\*\generator Word;}\f0 \'cf\'f0\'e8\'e2\'e5\'f2\par \u1330?\u1377?\u1408?\u1415? \tab end}
        RTF);
        $pptx = $this->upload('deck.pptx', self::zip([
            'ppt/slides/slide2.xml' => '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:p><a:r><a:t>Second</a:t></a:r></a:p></p:sld>',
            'ppt/slides/slide1.xml' => '<p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:p><a:r><a:t>First </a:t></a:r><a:r><a:t>slide</a:t></a:r></a:p></p:sld>',
        ]));

        // The model reads their text, as the business assistant too.
        $this->client->toolCalls = [];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Ի՞նչ է սա', 'attachments' => [$docx['id'], $odt['id'], $rtf['id'], $pptx['id']]])->assertOk();
        $blocks = $this->client->messages[0]['content'];
        $texts = array_column(array_column(array_filter($blocks, fn ($b) => $b['type'] === 'document'), 'source'), 'data');
        $this->assertStringContainsString("Աշխատող՝ Արման Պետրոսյան,\tպաշտոն՝ ________", $texts[0]);
        $this->assertStringContainsString('| Հաճախորդ | Արման Պետրոսյան |', $texts[0]);
        $this->assertStringContainsString("[header]\nՍՊԸ «Հին անուն»", $texts[0]);
        $this->assertSame("Ակտ\nԳումար՝  5900 դր", $texts[1]);
        $this->assertSame("Привет\nԲարև \tend", $texts[2]);
        $this->assertSame("[slide 1]\nFirst slide\n[slide 2]\nSecond", $texts[3]);
        $this->assertStringContainsString('[file #'.$docx['id'].': contract.docx, document]', end($blocks)['text']);

        // Old binary formats and a document with no text are refused with the way out.
        $this->postJson('/api/v1/admin/assistant/attachments', ['file' => UploadedFile::fake()->createWithContent('old.doc', "\xD0\xCF\x11\xE0binary")])
            ->assertUnprocessable()->assertJsonPath('errors.file.0', fn (string $m) => str_contains($m, 'DOCX'));
        $this->postJson('/api/v1/admin/assistant/attachments', ['file' => UploadedFile::fake()->createWithContent('broken.docx', 'not a zip')])
            ->assertUnprocessable();
    }

    public function test_a_template_is_filled_phrase_by_phrase_keeping_its_look(): void
    {
        $filler = new TemplateFiller;
        [$filled, $missing] = $filler->fill(self::template(), 'docx', [
            // Over three runs and a spell-check mark: it takes the bold of the run it starts in.
            ['find' => 'Արման Պետրոսյան', 'replace' => 'Գուրգեն Անդրեասյան'],
            // Blanks in reading order.
            ['find' => '________', 'replace' => 'վարպետ'],
            ['find' => '________', 'replace' => "01.11.2026\n(վերջին աշխատանքային օր)"],
            ['find' => 'Արման Պետրոսյան', 'replace' => 'Գուրգեն Անդրեասյան', 'all' => true],
            ['find' => '«Հին անուն»', 'replace' => '«Սերվիո»'],
            ['find' => 'չկա այսպիսի բան', 'replace' => 'x'],
        ]);

        $this->assertSame(['չկա այսպիսի բան'], $missing);
        $text = $this->text($filled);
        $this->assertStringContainsString("Աշխատող՝ Գուրգեն Անդրեասյան,\tպաշտոն՝ վարպետ", $text);
        $this->assertStringContainsString("Ազատման օր՝ 01.11.2026\n(վերջին աշխատանքային օր)", $text);
        $this->assertStringContainsString('| Հաճախորդ | Գուրգեն Անդրեասյան |', $text);
        $this->assertStringContainsString('ՍՊԸ «Սերվիո»', $text);

        $xml = self::unzip($filled)['word/document.xml'];
        // The name sits in the bold run, the runs it spanned keep only the rest; the tab and the centred title stay.
        $this->assertStringContainsString('<w:rPr><w:b/></w:rPr><w:t xml:space="preserve">Գուրգեն Անդրեասյան</w:t>', $xml);
        $this->assertStringContainsString('<w:tab/>', $xml);
        $this->assertStringContainsString('<w:jc w:val="center"/>', $xml);
        $this->assertStringContainsString('<w:br/>', $xml);
        // The other parts of the file are copied as they were.
        $this->assertSame(self::unzip(self::template())['[Content_Types].xml'], self::unzip($filled)['[Content_Types].xml']);

        // Several copies as one file to print, each from a new page.
        $all = $filler->combine([$filled, self::template()]);
        $this->assertSame(2, substr_count($this->text($all), 'ՀԱՄԱՁԱՅՆԱԳԻՐ'));
        $this->assertSame(1, substr_count(self::unzip($all)['word/document.xml'], 'w:type="page"'));
    }

    public function test_anna_fills_a_sample_for_an_employee_and_the_owner_downloads_it(): void
    {
        $template = $this->upload('Ազատման համաձայնագիր.docx', self::template());

        $reply = $this->ask([[DocumentStudio::FILL, ['template' => $template['id'], 'documents' => [[
            'title' => 'Ազատման համաձայնագիր - Գուրգեն',
            'replacements' => [['find' => 'Արման Պետրոսյան', 'replace' => 'Գուրգեն Անդրեասյան', 'all' => true], ['find' => '________', 'replace' => 'վարպետ']],
        ]]]]], [$template['id']]);

        // Anna knows how and has the tools; other agents don't.
        $this->assertStringContainsString('Making documents', $this->client->system);
        $tools = array_column($this->client->toolDefinitions, 'name');
        $this->assertContains(DocumentStudio::FILL, $tools);
        $this->assertContains('team_overview', $tools);
        $this->assertNotContains('add_rows', $tools);

        $this->assertCount(1, $reply['documents']);
        $document = $reply['documents'][0];
        $this->assertSame('document', $document['origin']);
        $this->assertSame('Ազատման համաձայնագիր - Գուրգեն.docx', $document['name']);
        $this->assertSame('docx', $document['format']);
        $this->assertSame([], $reply['designs']);
        $this->assertStringContainsString('Գուրգեն Անդրեասյան', $this->text($this->stored($document['id'])));
        $this->assertSame(['made' => [['file' => "[file #{$document['id']}]", 'name' => 'Ազատման համաձայնագիր - Գուրգեն.docx']]], $this->client->toolResults[0]);

        $this->get("/api/v1/admin/assistant/attachments/{$document['id']}/file?download=1")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertDownload();

        // Kept with the answer; next time the model knows what it made, by number.
        $conversation = $reply['conversation']['id'];
        $this->getJson("/api/v1/admin/assistant/conversations/{$conversation}")
            ->assertJsonPath('data.messages.1.attachments.0.origin', 'document');
        $this->ask([], [], $conversation);
        $this->assertStringContainsString("(Documents made in this answer: [file #{$document['id']}] Ազատման համաձայնագիր - Գուրգեն.docx)", $this->client->messages[1]['content']);

        // A phrase not in the template: nothing is made, the model is told which.
        $this->ask([[DocumentStudio::FILL, ['template' => $template['id'], 'documents' => [['title' => 'x', 'replacements' => [['find' => 'Ոչ մի տեղ', 'replace' => 'y']]]]]]]);
        $this->assertFalse($this->client->toolResults[0]['made']);
        $this->assertSame([['find' => 'Ոչ մի տեղ', 'documents' => [1]]], $this->client->toolResults[0]['not_found']);

        // Another person's file is not a template here.
        $other = User::factory()->forTenant($this->shop, 'owner')->create();
        $this->expectExceptionMessage("There is no [file #{$template['id']}] in this chat.");
        app(DocumentStudio::class)->run(DocumentStudio::FILL, ['template' => $template['id'], 'documents' => [['title' => 'x', 'replacements' => [['find' => 'a', 'replace' => 'b']]]]], $other);
    }

    public function test_anna_fills_one_paper_for_every_order_without_reading_the_buyers(): void
    {
        $id = $this->postJson('/api/v1/admin/data-tables', [
            'section' => 'orders',
            'name' => 'Պատվերներ',
            'columns' => [
                ['label' => 'Ամսաթիվ', 'type' => 'date', 'role' => 'date'],
                ['label' => 'Ծառայություն', 'type' => 'text', 'role' => 'item'],
                ['label' => 'Գումար', 'type' => 'money', 'role' => 'amount'],
                ['label' => 'Հաճախորդ', 'type' => 'text', 'role' => 'customer'],
            ],
        ])->assertCreated()->json('data.id');
        $table = DataTable::query()->findOrFail($id);
        [$date, $item, $amount, $customer] = array_column($table->columns, 'key');
        foreach ([['2026-10-02', 'Բազմոց', 12500, 'Լիլիթ'], ['2026-10-01', 'Ներքնակ', 5900, 'Գոռ'], ['2026-10-03', 'Աթոռ', 500, null], ['2026-09-30', 'Հին', 1, 'Չկա']] as [$at, $what, $money, $who]) {
            DataTableRow::create(['data_table_id' => $table->id, 'data' => array_filter([$date => $at, $item => $what, $amount => $money, $customer => $who])]);
        }
        $template = $this->upload('act.docx', self::template());

        $reply = $this->ask([[DocumentStudio::BATCH, [
            'template' => $template['id'],
            'source' => ['table' => $table->id, 'from' => '2026-10-01', 'to' => '2026-10-31'],
            'fields' => [
                ['find' => '№ 15', 'value' => '№ {#} ({Ծառայություն})'],
                ['find' => 'Արման Պետրոսյան', 'value' => '{Հաճախորդ}', 'all' => true],
                ['find' => '________', 'value' => '{Գումար} դր'],
                ['find' => '________', 'value' => '{Ամսաթիվ}'],
            ],
            'file_name' => 'Ակտ {#} {Հաճախորդ}',
            'title' => 'Ակտեր, հոկտեմբեր',
        ]]]);

        // A ZIP of every order of the month (chronological) and one Word file with all to print.
        $this->assertSame(['Ակտեր, հոկտեմբեր.zip', 'Ակտեր, հոկտեմբեր - բոլորը մեկ ֆայլում.docx'], array_column($reply['documents'], 'name'));
        $this->assertSame([3, 3], array_column($reply['documents'], 'documents'));
        $files = self::unzip($this->stored($reply['documents'][0]['id']));
        $this->assertSame(['Ակտ 1 Գոռ.docx', 'Ակտ 2 Լիլիթ.docx', 'Ակտ 3.docx'], array_keys($files));
        $first = $this->text($files['Ակտ 1 Գոռ.docx']);
        $this->assertStringContainsString('ՀԱՄԱՁԱՅՆԱԳԻՐ № 1 (Ներքնակ)', $first);
        $this->assertStringContainsString('| Հաճախորդ | Գոռ |', $first);
        $this->assertStringContainsString("պաշտոն՝ 5 900 դր\nԱզատման օր՝ 01.10.2026", $first);
        $this->assertStringContainsString('12 500 դր', $this->text($files['Ակտ 2 Լիլիթ.docx']));
        // A missing value is a blank to fill by hand.
        $this->assertStringContainsString('Աշխատող՝ ____________,', $this->text($files['Ակտ 3.docx']));
        $this->assertSame(3, substr_count($this->text($this->stored($reply['documents'][1]['id'])), 'ՀԱՄԱՁԱՅՆԱԳԻՐ'));

        // The model hears how many and what was empty, never the buyers' names.
        $result = $this->client->toolResults[0];
        $this->assertSame(3, $result['documents']);
        $this->assertArrayNotHasKey('file_names', $result);
        $this->assertSame(['{Հաճախորդ}' => 1], $result['empty_values']);
        $this->assertStringNotContainsString('Լիլիթ', json_encode($result, JSON_UNESCAPED_UNICODE));

        // An unknown column is named back with the real ones.
        try {
            app(DocumentStudio::class)->run(DocumentStudio::BATCH, ['template' => $template['id'], 'source' => ['table' => $table->id], 'fields' => [['find' => '№ 15', 'value' => '{Անուն}']]], $this->owner);
            $this->fail('An unknown column was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('No such columns: {Անուն}', $e->getMessage());
            $this->assertStringContainsString('{Ծառայություն}', $e->getMessage());
        }
    }

    public function test_anna_writes_new_documents_and_fills_papers_for_the_team(): void
    {
        Employee::create(['name' => 'Գուրգեն', 'position' => 'Վարպետ', 'hired_on' => '2025-03-01']);
        Employee::create(['name' => 'Արամ', 'position' => 'Օգնական']);
        Employee::create(['name' => 'Հին', 'is_active' => false]);

        $reply = $this->ask([[DocumentStudio::WRITE, [
            'title' => 'Լիազորագիր',
            'content' => "# ԼԻԱԶՈՐԱԳԻՐ\n[right] Երևան, 28.10.2026\n\nԵս՝ **________**, լիազորում եմ\n- կետ մեկ\n| Անուն | Պաշտոն |\n|---|---|\n| ________ | ________ |\n\n\nՍտորագրություն ________",
        ]]]);
        $this->assertSame(['Լիազորագիր.docx'], array_column($reply['documents'], 'name'));
        $made = $this->stored($reply['documents'][0]['id']);
        $text = $this->text($made);
        $this->assertStringContainsString("ԼԻԱԶՈՐԱԳԻՐ\nԵրևան, 28.10.2026\nԵս՝ ________, լիազորում եմ\n•\tկետ մեկ\n| Անուն | Պաշտոն |", $text);
        $this->assertStringContainsString('<w:jc w:val="right"/>', self::unzip($made)['word/document.xml']);
        $this->assertStringContainsString('<w:b/>', self::unzip($made)['word/document.xml']);

        // What she wrote is a template too: one for every working employee.
        $reply = $this->ask([[DocumentStudio::BATCH, [
            'template' => $reply['documents'][0]['id'],
            'source' => ['employees' => true],
            'fields' => [['find' => '________', 'value' => '{name}'], ['find' => '________', 'value' => '{name}'], ['find' => '________', 'value' => '{position}, {hired_on}']],
        ]]]);
        $files = self::unzip($this->stored($reply['documents'][0]['id']));
        $this->assertSame(['Լիազորագիր 1.docx', 'Լիազորագիր 2.docx'], array_keys($files));
        $this->assertStringContainsString('| Գուրգեն | Վարպետ, 01.03.2025 |', $this->text($files['Լիազորագիր 2.docx']));
        $this->assertSame(['Լիազորագիր 1.docx', 'Լիազորագիր 2.docx'], $this->client->toolResults[0]['file_names']);
    }

    public function test_only_anna_makes_documents(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/tables", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v1/admin/assistant', ['question' => 'Գրիր պայմանագիր', 'agent' => 'tables'])->assertOk();
        $this->assertNotContains(DocumentStudio::WRITE, array_column($this->client->toolDefinitions, 'name'));
        $this->assertStringNotContainsString('Making documents', $this->client->system);
        // Her work is named to the others, so they hand it to her.
        $this->assertStringContainsString('Anna · Documents specialist', $this->client->system);

        $this->getJson('/api/v1/admin/agents')->assertOk()
            ->assertJsonPath('data.agents.4.key', 'documents')
            ->assertJsonPath('data.agents.4.persona.hy', 'Աննա');
    }

    private function converter(): FakeDocumentConverter
    {
        $converter = new FakeDocumentConverter;
        $this->app->instance(DocumentConverter::class, $converter);

        return $converter;
    }

    public function test_old_office_files_become_modern_ones_anna_fills_in_place(): void
    {
        $converter = $this->converter();
        $converter->converted = self::template();

        // contract.doc is kept as contract.docx: read and filled like any Word file.
        $doc = $this->upload('contract.doc', "\xD0\xCF\x11\xE0old word");
        $this->assertSame(['document', 'contract.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], [$doc['kind'], $doc['name'], $doc['mime']]);
        $this->assertSame([['convert', 'doc']], $converter->calls);

        $reply = $this->ask([[DocumentStudio::FILL, ['template' => $doc['id'], 'documents' => [['title' => 'Գուրգեն', 'replacements' => [['find' => 'Արման Պետրոսյան', 'replace' => 'Գուրգեն Անդրեասյան']]]]]]], [$doc['id']]);
        $this->assertStringContainsString("[file #{$doc['id']}: contract.docx, document, converted from DOC]", end($this->client->messages[0]['content'])['text']);
        $this->assertStringContainsString('Գուրգեն Անդրեասյան', $this->text($this->stored($reply['documents'][0]['id'])));

        // An old Excel becomes a sheet.
        $converter->converted = self::zip([
            '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'xl/worksheets/sheet1.xml' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Ապրանք</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Մոմ</t></is></c></row></sheetData></worksheet>',
        ]);
        $xls = $this->upload('sales.xls', "\xD0\xCF\x11\xE0old excel");
        $this->assertSame(['sheet', 'sales.xlsx'], [$xls['kind'], $xls['name']]);

        // A file the service can't open is refused with the way out.
        $converter->converted = null;
        $this->postJson('/api/v1/admin/assistant/attachments', ['file' => UploadedFile::fake()->createWithContent('broken.ppt', 'junk')])
            ->assertUnprocessable()->assertJsonPath('errors.file.0', fn (string $m) => str_contains($m, 'PPTX'));
        // The .doc, the .xls and the contract she made; nothing of the broken one.
        $this->assertSame(3, AssistantAttachment::withoutTenancy()->count());
    }

    /** A PDF the page counter sees as $pages pages. */
    private static function pdf(int $pages): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Pages /Count {$pages} >> endobj\n".str_repeat("2 0 obj << /Type /Page >> endobj\n", $pages).'%%EOF';
    }

    public function test_long_pdfs_are_read_as_text_and_scans_as_pictures_of_their_pages(): void
    {
        $converter = $this->converter();

        // Short ones go to the model whole, as before.
        $short = $this->upload('short.pdf', self::pdf(3));
        $this->assertSame([], $converter->calls);
        $this->assertNull($short['pages']);

        // A long one with text: its text.
        $converter->pdf = ['pages' => 120, 'text' => str_repeat("Պայմանագրի կետ\n", 400), 'images' => []];
        $long = $this->upload('long.pdf', self::pdf(120));
        $this->assertSame(120, $long['pages']);
        $this->assertSame([['pdf', 0]], $converter->calls);

        // A scan (no text): pictures of its first pages.
        $jpg = base64_encode(UploadedFile::fake()->image('p.jpg', 20, 30)->getContent());
        $converter->pdf = ['pages' => 45, 'text' => '', 'images' => [['page' => 1, 'jpg' => $jpg], ['page' => 2, 'jpg' => $jpg]]];
        $scan = $this->upload('scan.pdf', self::pdf(45));

        $this->ask([], [$short['id'], $long['id'], $scan['id']]);
        $blocks = $this->client->messages[0]['content'];
        $this->assertSame('document', $blocks[0]['type']);
        $this->assertSame('application/pdf', $blocks[0]['source']['media_type']);
        $this->assertSame('text', $blocks[1]['source']['type']);
        $this->assertStringStartsWith('Պայմանագրի կետ', $blocks[1]['source']['data']);
        $this->assertSame("[file #{$scan['id']}] page 1:", $blocks[2]['text']);
        $this->assertSame('image', $blocks[3]['type']);
        $this->assertSame("[file #{$scan['id']}]: a scan of 45 pages, only the first 2 are shown.", $blocks[6]['text']);
        $label = end($blocks)['text'];
        $this->assertStringContainsString('long.pdf, pdf, 120 pages, read as text]', $label);
        $this->assertStringContainsString('scan.pdf, pdf, 45 pages, a scan: pictures of its first pages]', $label);

        // Too many pages, or the service down: refused, nothing kept.
        $converter->pdf = ['pages' => 900, 'text' => 'x', 'images' => []];
        $this->postJson('/api/v1/admin/assistant/attachments', ['file' => UploadedFile::fake()->createWithContent('huge.pdf', self::pdf(40))])
            ->assertUnprocessable()->assertJsonPath('errors.file.0', 'PDF-ը պետք է լինի մինչև 500 էջ։');
        $this->assertCount(3, AssistantAttachment::withoutTenancy()->get());

        // Deleting the chat removes the text and pictures kept with them.
        $conversation = AssistantConversation::query()->latest('id')->firstOrFail();
        $this->deleteJson("/api/v1/admin/assistant/conversations/{$conversation->id}")->assertNoContent();
        $this->assertSame([], Storage::disk(Attachments::DISK)->allFiles());
    }

    public function test_the_writer_and_reader_agree_on_plain_text(): void
    {
        $docx = (new DocxWriter)->write("Առաջին\n1. Կետ\n**Թավ** և *շեղ*\n[pagebreak]\nՎերջ");
        $this->assertSame("Առաջին\n1.\tԿետ\nԹավ և շեղ\n\nՎերջ", (new DocumentReader)->text($docx, 'docx'));
    }
}

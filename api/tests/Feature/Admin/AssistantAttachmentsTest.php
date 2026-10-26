<?php

namespace Tests\Feature\Admin;

use App\Assistant\AssistantClient;
use App\Assistant\Attachments;
use App\Assistant\ToolOutput;
use App\Models\AssistantAttachment;
use App\Models\AssistantUsage;
use App\Models\BrandAsset;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeAssistantClient;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

/**
 * Files in the AI chat: checked by content, kept privately per business
 * and person, sent to the model as image and document blocks, shown in the
 * history, and an attached logo or example kept for the AI agents.
 */
class AssistantAttachmentsTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private FakeAssistantClient $client;

    private Tenant $shop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Attachments::DISK);
        $this->client = new FakeAssistantClient;
        $this->app->instance(AssistantClient::class, $this->client);
        $this->shop = $this->createTenant('test-shop');
        $this->owner = User::factory()->forTenant($this->shop)->create();
        Sanctum::actingAs($this->owner);
    }

    private function upload(UploadedFile $file): TestResponse
    {
        return $this->post('/api/v1/admin/assistant/attachments', ['file' => $file], ['Accept' => 'application/json']);
    }

    private function turnOn(string $agent): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/tenants/{$this->shop->id}/agents/{$agent}", ['active' => true])->assertOk();
        Sanctum::actingAs($this->owner);
    }

    public function test_a_picture_goes_to_the_model_with_the_question_and_stays_in_the_history(): void
    {
        $file = $this->upload(UploadedFile::fake()->image('logo.png', 320, 200))
            ->assertCreated()
            ->assertJsonPath('data.kind', 'image')
            ->assertJsonPath('data.mime', 'image/png')
            ->assertJsonPath('data.width', 320)
            ->json('data');

        // Private disk, a folder per business.
        $row = AssistantAttachment::withoutTenancy()->findOrFail($file['id']);
        $this->assertStringStartsWith("assistant/{$this->shop->id}/", $row->path);
        Storage::disk(Attachments::DISK)->assertExists($row->path);

        $reply = $this->postJson('/api/v1/admin/assistant', ['question' => 'Ի՞նչ գույներ կան լոգոյում', 'attachments' => [$file['id']]])
            ->assertOk()
            ->assertJsonPath('data.attachments.0.id', $file['id'])
            ->json('data');

        $content = $this->client->messages[0]['content'];
        $this->assertSame('image', $content[0]['type']);
        $this->assertSame('image/png', $content[0]['source']['media_type']);
        $this->assertSame(base64_encode(Storage::disk(Attachments::DISK)->get($row->path)), $content[0]['source']['data']);
        $this->assertSame('text', $content[1]['type']);
        $this->assertStringContainsString("[file #{$file['id']}: logo.png, image]", $content[1]['text']);
        $this->assertStringContainsString('Ի՞նչ գույներ կան լոգոյում', $content[1]['text']);

        // One request of the month, files or not.
        $this->assertSame(1, (int) AssistantUsage::withoutTenancy()->where('tenant_id', $this->shop->id)->sum('requests'));

        $this->getJson("/api/v1/admin/assistant/conversations/{$reply['conversation']['id']}")->assertOk()
            ->assertJsonPath('data.messages.0.attachments.0.name', 'logo.png')
            ->assertJsonCount(0, 'data.messages.1.attachments');

        // The file comes back through the API only, inline and not sniffable.
        $this->get("/api/v1/admin/assistant/attachments/{$file['id']}/file")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // The next question sends the picture again (it is still in the chat).
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Իսկ տառատեսակը', 'conversation_id' => $reply['conversation']['id']])->assertOk();
        $this->assertSame('image', $this->client->messages[0]['content'][0]['type']);
        $this->assertSame('Իսկ տառատեսակը', $this->client->messages[2]['content']);
    }

    public function test_pdf_and_text_files_are_documents_and_files_alone_are_a_message(): void
    {
        $pdf = $this->upload(UploadedFile::fake()->createWithContent('price.pdf', "%PDF-1.4\n1 0 obj << /Type /Pages /Count 1 >> endobj\n2 0 obj << /Type /Page >> endobj\n%%EOF"))
            ->assertCreated()->assertJsonPath('data.kind', 'pdf')->json('data.id');
        $csv = $this->upload(UploadedFile::fake()->createWithContent('sales.csv', "item,price\nԲազմոց,5900\n"))
            ->assertCreated()->assertJsonPath('data.kind', 'text')->json('data.id');

        $this->postJson('/api/v1/admin/assistant', ['attachments' => [$pdf, $csv]])
            ->assertOk()
            ->assertJsonPath('data.conversation.title', 'price.pdf');

        [$document, $text, $question] = $this->client->messages[0]['content'];
        $this->assertSame(['document', 'base64', 'application/pdf', 'price.pdf'], [$document['type'], $document['source']['type'], $document['source']['media_type'], $document['title']]);
        $this->assertSame(['document', 'text', "item,price\nԲազմոց,5900\n"], [$text['type'], $text['source']['type'], $text['source']['data']]);
        $this->assertStringContainsString('(No text: the files are the message.)', $question['text']);

        // Nothing at all is not a question.
        $this->postJson('/api/v1/admin/assistant', ['question' => ''])->assertUnprocessable();
    }

    public function test_only_real_files_of_the_allowed_kinds_and_sizes_are_kept(): void
    {
        // A program named as a picture.
        $this->upload(UploadedFile::fake()->createWithContent('logo.png', "MZ\x90\x00\x03\x00\x00\x00binary"))
            ->assertUnprocessable()->assertJsonPath('errors.file.0', Attachments::UNSUPPORTED);
        // HTML under a text name is refused by its content.
        $this->upload(UploadedFile::fake()->createWithContent('notes.html', '<html><script>alert(1)</script></html>'))
            ->assertUnprocessable();
        $this->upload(UploadedFile::fake()->image('big.jpg')->size(6000))->assertUnprocessable();
        $this->upload(UploadedFile::fake()->createWithContent('cp1251.txt', "\xCF\xF0\xE8\xE2\xE5\xF2"))->assertUnprocessable();

        $this->assertSame(0, AssistantAttachment::withoutTenancy()->count());
        $this->assertSame([], Storage::disk(Attachments::DISK)->allFiles());
    }

    public function test_files_belong_to_their_uploader_and_business(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.png'))->assertCreated()->json('data.id');

        // Another person in the same business.
        $colleague = User::factory()->forTenant($this->shop)->create();
        Sanctum::actingAs($colleague);
        $this->get("/api/v1/admin/assistant/attachments/{$id}/file")->assertNotFound();
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Սա ի՞նչ է', 'attachments' => [$id]])->assertUnprocessable();

        // Another business.
        $other = $this->createTenant('other-shop');
        Sanctum::actingAs(User::factory()->forTenant($other)->create());
        $this->get("/api/v1/admin/assistant/attachments/{$id}/file")->assertNotFound();

        // The uploader takes it back before sending.
        Sanctum::actingAs($this->owner);
        $path = AssistantAttachment::withoutTenancy()->findOrFail($id)->path;
        $this->deleteJson("/api/v1/admin/assistant/attachments/{$id}")->assertNoContent();
        Storage::disk(Attachments::DISK)->assertMissing($path);
    }

    public function test_deleting_a_conversation_removes_its_files_and_unsent_ones_are_pruned(): void
    {
        $sent = $this->upload(UploadedFile::fake()->image('a.png'))->json('data.id');
        $conversation = $this->postJson('/api/v1/admin/assistant', ['question' => 'Նայիր', 'attachments' => [$sent]])->json('data.conversation.id');
        $sentPath = AssistantAttachment::withoutTenancy()->findOrFail($sent)->path;

        $this->deleteJson("/api/v1/admin/assistant/attachments/{$sent}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/assistant/conversations/{$conversation}")->assertNoContent();
        Storage::disk(Attachments::DISK)->assertMissing($sentPath);
        $this->assertSame(0, AssistantAttachment::withoutTenancy()->count());

        $this->travelTo(now()->subDays(2));
        $old = $this->upload(UploadedFile::fake()->image('old.png'))->json('data.id');
        $this->travelBack();
        $fresh = $this->upload(UploadedFile::fake()->image('new.png'))->json('data.id');

        $this->artisan('platform:assistant:prune')->assertSuccessful();
        $this->assertSame([$fresh], AssistantAttachment::withoutTenancy()->pluck('id')->all());
        $this->assertNotSame($old, $fresh);
    }

    public function test_no_uploads_once_the_months_requests_are_used_up(): void
    {
        Plan::query()->update(['assistant_requests' => 0]);

        $this->upload(UploadedFile::fake()->image('a.png'))->assertStatus(429)->assertJsonPath('code', 'limit_reached');
    }

    public function test_an_agent_keeps_an_attached_logo_and_looks_at_it_later(): void
    {
        $this->turnOn('content');
        $id = $this->upload(UploadedFile::fake()->image('logo.png', 100, 100))->json('data.id');

        $this->client->toolCalls = [['save_brand_asset', ['file' => $id, 'kind' => 'logo', 'note' => 'Navy house with hands']]];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Սա մեր լոգոն է', 'agent' => 'content', 'attachments' => [$id]])->assertOk();

        $logo = BrandAsset::withoutTenancy()->sole();
        $this->assertSame(['logo', 'Navy house with hands'], [$logo->kind, $logo->note]);
        $this->assertStringStartsWith("brand/{$this->shop->id}/", $logo->path);

        // A new conversation: the agent can look at it.
        $this->client->toolCalls = [['view_brand_assets', []]];
        $this->client->toolResults = [];
        $this->postJson('/api/v1/admin/assistant', ['question' => 'Գրառում պատրաստիր', 'agent' => 'content'])->assertOk();
        $this->assertContains('view_brand_assets', array_column($this->client->toolDefinitions, 'name'));
        $this->assertStringContainsString('Saved pictures: the logo', $this->client->system ?? '');
        $output = $this->client->toolResults[0];
        $this->assertInstanceOf(ToolOutput::class, $output);
        $this->assertSame('image', $output->blocks[1]['type']);

        // The team page lists it; the owner removes it.
        $this->getJson('/api/v1/admin/agents')->assertOk()->assertJsonPath('data.assets.0.kind', 'logo');
        $this->get("/api/v1/admin/agents/brand-assets/{$logo->id}/file")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->deleteJson("/api/v1/admin/agents/brand-assets/{$logo->id}")->assertNoContent();
        Storage::disk(Attachments::DISK)->assertMissing($logo->path);
    }

    public function test_the_owner_saves_examples_from_the_chat_or_the_team_page_up_to_the_limit(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('post.jpg'))->json('data.id');
        $this->postJson('/api/v1/admin/agents/brand-assets', ['attachment_id' => $id, 'kind' => 'example'])
            ->assertCreated()
            ->assertJsonPath('data.asset.kind', 'example')
            ->assertJsonPath('data.assets.0.name', 'post.jpg');

        for ($i = 1; $i < BrandAsset::MAX_EXAMPLES; $i++) {
            $this->post('/api/v1/admin/agents/brand-assets', ['kind' => 'example', 'file' => UploadedFile::fake()->image("p{$i}.png")], ['Accept' => 'application/json'])
                ->assertCreated();
        }
        $this->post('/api/v1/admin/agents/brand-assets', ['kind' => 'example', 'file' => UploadedFile::fake()->image('one-more.png')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        // Only pictures.
        $this->post('/api/v1/admin/agents/brand-assets', ['kind' => 'logo', 'file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF")], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertSame(BrandAsset::MAX_EXAMPLES, BrandAsset::withoutTenancy()->count());
        $this->assertCount(BrandAsset::MAX_EXAMPLES + 1, Storage::disk(Attachments::DISK)->allFiles()); // + the chat's own file

        // Another business sees none of them.
        Sanctum::actingAs(User::factory()->forTenant($this->createTenant('other-shop'))->create());
        $this->getJson('/api/v1/admin/agents')->assertOk()->assertJsonCount(0, 'data.assets');
        $this->get('/api/v1/admin/agents/brand-assets/'.BrandAsset::withoutTenancy()->value('id').'/file')->assertNotFound();
    }
}

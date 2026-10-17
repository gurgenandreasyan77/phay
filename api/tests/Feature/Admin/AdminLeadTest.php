<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    private function createLead(array $overrides = []): Lead
    {
        // created_at/updated_at aren't in Lead's $fillable, so a mass-assigned
        // override would be silently dropped — set it separately via forceFill.
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $lead = Lead::create(array_merge([
            'name' => 'Test Client',
            'phone' => '+37491000000',
            'preferred_locale' => 'hy',
            'consent' => true,
        ], $overrides));

        if ($createdAt) {
            $lead->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $lead;
    }

    public function test_index_lists_leads_newest_first(): void
    {
        $this->createLead(['name' => 'Older', 'created_at' => now()->subDay()]);
        $this->createLead(['name' => 'Newer', 'created_at' => now()]);

        $response = $this->getJson('/api/v1/admin/leads')->assertOk();

        $this->assertSame('Newer', $response->json('data.0.name'));
        $this->assertSame('Older', $response->json('data.1.name'));
    }

    public function test_index_filters_by_status(): void
    {
        $this->createLead(['status' => 'new']);
        $this->createLead(['status' => 'completed']);

        $response = $this->getJson('/api/v1/admin/leads?status=completed')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('completed', $response->json('data.0.status'));
    }

    public function test_index_search_is_case_insensitive(): void
    {
        $this->createLead(['name' => 'Անի Հակոբյան', 'phone' => '+37491123456']);
        $this->createLead(['name' => 'Someone Else', 'phone' => '+37491000000']);

        $response = $this->getJson('/api/v1/admin/leads?search=SOME')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Someone Else', $response->json('data.0.name'));
    }

    public function test_store_creates_a_manual_lead_without_consent(): void
    {
        $response = $this->postJson('/api/v1/admin/leads', [
            'name' => 'Manual Client',
            'phone' => '+374 91 123456',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('leads', [
            'name' => 'Manual Client',
            'source' => 'manual',
            'status' => 'new',
            'consent' => true,
        ]);
    }

    public function test_update_transitions_status(): void
    {
        $lead = $this->createLead(['status' => 'new']);

        $response = $this->patchJson("/api/v1/admin/leads/{$lead->id}", ['status' => 'contacted']);

        $response->assertOk()->assertJsonPath('data.status', 'contacted');
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'contacted']);
    }

    public function test_show_returns_404_for_unknown_lead(): void
    {
        $this->getJson('/api/v1/admin/leads/999999')->assertStatus(404);
    }

    public function test_show_exposes_the_linked_product(): void
    {
        $product = Product::create([
            'slug' => 'floor-cleaner-concentrate',
            'category' => 'floors',
            'name' => ['hy' => 'Հատակի միջոց', 'ru' => 'Средство для полов', 'en' => 'Floor cleaner'],
            'description' => ['hy' => 'Նկարագրություն', 'ru' => 'Описание', 'en' => 'Description'],
            'alt' => ['hy' => 'Շիշ', 'ru' => 'Бутылка', 'en' => 'Bottle'],
            'image' => '/images/products/floor-cleaner-concentrate.webp',
            'price_amount' => 4500,
            'sort_order' => 1,
        ]);

        $lead = $this->createLead(['product_id' => $product->id, 'source' => 'shop']);

        $response = $this->getJson("/api/v1/admin/leads/{$lead->id}")->assertOk();

        $response->assertJsonPath('data.product.slug', 'floor-cleaner-concentrate')
            ->assertJsonPath('data.source', 'shop');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Անի Հակոբյան',
            'phone' => '+374 91 123456',
            'email' => 'ani@example.com',
            'property_type' => 'apartment',
            'area_sqm' => 60,
            'address' => 'Կենտրոն',
            'preferred_date' => now()->addDays(3)->toDateString(),
            'comment' => 'Please call in the evening.',
            'preferred_locale' => 'hy',
            'consent' => true,
        ], $overrides);
    }

    public function test_a_valid_lead_is_stored_and_returns_201(): void
    {
        $response = $this->postJson('/api/v1/leads', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonStructure(['data' => ['id', 'status', 'createdAt']]);

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('leads', [
            'name' => 'Անի Հակոբյան',
            'phone' => '+374 91 123456',
            'status' => 'new',
            'consent' => true,
        ]);
    }

    public function test_a_lead_is_linked_to_the_selected_service(): void
    {
        $category = ServiceCategory::create([
            'slug' => 'cleaning',
            'sort_order' => 1,
            'name' => ['hy' => 'Մաքրում', 'ru' => 'Уборка', 'en' => 'Cleaning'],
        ]);

        $service = Service::create([
            'category_id' => $category->id,
            'slug' => 'apartment-cleaning',
            'sort_order' => 1,
            'status' => 'active',
            'name' => ['hy' => 'Բնակարանների մաքրում', 'ru' => 'Уборка квартир', 'en' => 'Apartment Cleaning'],
        ]);

        $this->postJson('/api/v1/leads', $this->validPayload(['service_slug' => 'apartment-cleaning']))
            ->assertCreated();

        $this->assertSame($service->id, Lead::first()->service_id);
    }

    public function test_a_lead_is_linked_to_the_selected_product(): void
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

        $this->postJson('/api/v1/leads', $this->validPayload([
            'product_slug' => 'floor-cleaner-concentrate',
            'source' => 'shop',
            'fulfillment_type' => 'delivery',
        ]))->assertCreated();

        $lead = Lead::first();
        $this->assertSame($product->id, $lead->product_id);
        $this->assertSame('shop', $lead->source);
        $this->assertSame('delivery', $lead->fulfillment_type->value);
    }

    public function test_product_order_without_fulfillment_type_is_rejected(): void
    {
        Product::create([
            'slug' => 'floor-cleaner-concentrate',
            'category' => 'floors',
            'name' => ['hy' => 'Հատակի միջոց', 'ru' => 'Средство для полов', 'en' => 'Floor cleaner'],
            'description' => ['hy' => 'Նկարագրություն', 'ru' => 'Описание', 'en' => 'Description'],
            'alt' => ['hy' => 'Շիշ', 'ru' => 'Бутылка', 'en' => 'Bottle'],
            'image' => '/images/products/floor-cleaner-concentrate.webp',
            'price_amount' => 4500,
            'sort_order' => 1,
        ]);

        $this->postJson('/api/v1/leads', $this->validPayload(['product_slug' => 'floor-cleaner-concentrate']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fulfillment_type');
    }

    public function test_product_order_with_delivery_requires_an_address(): void
    {
        Product::create([
            'slug' => 'floor-cleaner-concentrate',
            'category' => 'floors',
            'name' => ['hy' => 'Հատակի միջոց', 'ru' => 'Средство для полов', 'en' => 'Floor cleaner'],
            'description' => ['hy' => 'Նկարագրություն', 'ru' => 'Описание', 'en' => 'Description'],
            'alt' => ['hy' => 'Շիշ', 'ru' => 'Бутылка', 'en' => 'Bottle'],
            'image' => '/images/products/floor-cleaner-concentrate.webp',
            'price_amount' => 4500,
            'sort_order' => 1,
        ]);

        $this->postJson('/api/v1/leads', $this->validPayload([
            'product_slug' => 'floor-cleaner-concentrate',
            'fulfillment_type' => 'delivery',
            'address' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors('address');

        $this->postJson('/api/v1/leads', $this->validPayload([
            'product_slug' => 'floor-cleaner-concentrate',
            'fulfillment_type' => 'pickup',
            'address' => null,
        ]))->assertCreated();
    }

    public function test_accepts_armenian_local_phone_format(): void
    {
        $this->postJson('/api/v1/leads', $this->validPayload(['phone' => '077123456']))
            ->assertCreated();
    }
}

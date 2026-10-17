<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCareLeadSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Անի Հակոբյան',
            'phone' => '+374 91 123456',
            'preferred_locale' => 'hy',
            'consent' => true,
            'car_brand' => 'Toyota',
            'car_model' => 'Camry',
            'car_year' => 2019,
            'car_body_type' => 'sedan',
            'seats_count' => 5,
            'interior_material' => 'fabric',
            'soiling_level' => 'moderate',
            'contamination_types' => ['pet_hair', 'food_drinks'],
            'has_odor' => true,
            'odor_source' => 'pets',
            'has_pet_hair' => true,
            'has_child_seat' => false,
            'can_bring_car' => true,
            'preferred_channel' => 'whatsapp',
            'photos' => ['/storage/leads/auto-care/one.webp'],
        ], $overrides);
    }

    public function test_a_lead_with_car_care_fields_is_stored(): void
    {
        $this->postJson('/api/v1/leads', $this->validPayload())->assertCreated();

        $lead = Lead::first();
        $this->assertSame('Toyota', $lead->car_brand);
        $this->assertSame('sedan', $lead->car_body_type->value);
        $this->assertSame('moderate', $lead->soiling_level->value);
        $this->assertSame(['pet_hair', 'food_drinks'], $lead->contamination_types);
        $this->assertTrue($lead->has_odor);
        $this->assertSame('whatsapp', $lead->preferred_channel->value);
        $this->assertSame(['/storage/leads/auto-care/one.webp'], $lead->photos);
    }

    public function test_a_lead_is_linked_to_the_selected_package(): void
    {
        $package = ServicePackage::create([
            'slug' => 'full-interior-cleaning',
            'status' => 'active',
            'name' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => 'N'],
        ]);

        $this->postJson('/api/v1/leads', $this->validPayload(['package_slug' => 'full-interior-cleaning']))
            ->assertCreated();

        $this->assertSame($package->id, Lead::first()->package_id);
    }

    public function test_an_unknown_package_slug_is_rejected(): void
    {
        $this->postJson('/api/v1/leads', $this->validPayload(['package_slug' => 'does-not-exist']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['package_slug']);
    }

    public function test_an_invalid_car_body_type_is_rejected(): void
    {
        $this->postJson('/api/v1/leads', $this->validPayload(['car_body_type' => 'spaceship']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['car_body_type']);
    }

    public function test_more_than_six_photos_are_rejected(): void
    {
        $this->postJson('/api/v1/leads', $this->validPayload([
            'photos' => array_fill(0, 7, '/storage/leads/auto-care/x.webp'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['photos']);
    }

    public function test_auto_care_fields_are_optional_for_a_cleaning_lead(): void
    {
        $category = ServiceCategory::create([
            'slug' => 'cleaning',
            'sort_order' => 1,
            'name' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => 'N'],
        ]);
        $service = Service::create([
            'category_id' => $category->id,
            'slug' => 'apartment-cleaning',
            'status' => 'active',
            'name' => ['hy' => 'Ն', 'ru' => 'Н', 'en' => 'N'],
        ]);

        $this->postJson('/api/v1/leads', [
            'name' => 'Test',
            'phone' => '+37491123456',
            'preferred_locale' => 'hy',
            'consent' => true,
            'service_slug' => $service->slug,
        ])->assertCreated();

        $this->assertDatabaseCount('leads', 1);
    }
}

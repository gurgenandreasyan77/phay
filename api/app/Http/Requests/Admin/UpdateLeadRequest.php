<?php

namespace App\Http\Requests\Admin;

use App\Enums\CarBodyType;
use App\Enums\ContactChannelType;
use App\Enums\ContaminationType;
use App\Enums\InteriorMaterial;
use App\Enums\LeadFulfillmentType;
use App\Enums\LeadStatus;
use App\Enums\PropertyType;
use App\Enums\SoilingLevel;
use App\Rules\Phone;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'phone' => ['sometimes', 'string', 'max:30', new Phone],
            'email' => ['nullable', 'email', 'max:255'],
            'service_slug' => ['nullable', 'string', TenantRule::exists('services', 'slug')],
            'product_slug' => ['nullable', 'string', TenantRule::exists('products', 'slug')],
            'package_slug' => ['nullable', 'string', TenantRule::exists('service_packages', 'slug')],
            'property_type' => ['nullable', Rule::enum(PropertyType::class)],
            'area_sqm' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'fulfillment_type' => ['nullable', Rule::enum(LeadFulfillmentType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'preferred_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string', 'max:1000'],

            'car_brand' => ['nullable', 'string', 'max:100'],
            'car_model' => ['nullable', 'string', 'max:100'],
            'car_year' => ['nullable', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'car_body_type' => ['nullable', Rule::enum(CarBodyType::class)],
            'seats_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'interior_material' => ['nullable', Rule::enum(InteriorMaterial::class)],
            'soiling_level' => ['nullable', Rule::enum(SoilingLevel::class)],
            'contamination_types' => ['nullable', 'array'],
            'contamination_types.*' => [Rule::enum(ContaminationType::class)],
            'has_odor' => ['nullable', 'boolean'],
            'odor_source' => ['nullable', 'string', 'max:255'],
            'has_pet_hair' => ['nullable', 'boolean'],
            'has_child_seat' => ['nullable', 'boolean'],
            'can_bring_car' => ['nullable', 'boolean'],
            'preferred_channel' => ['nullable', Rule::enum(ContactChannelType::class)],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['string', 'max:2048'],

            // Admin-only fields — internal note and a cost estimate to hand
            // back to the client, never exposed on the public LeadResource.
            'admin_comment' => ['nullable', 'string', 'max:2000'],
            'estimated_price_min' => ['nullable', 'integer', 'min:0'],
            'estimated_price_max' => ['nullable', 'integer', 'min:0', 'gte:estimated_price_min'],
        ];
    }
}

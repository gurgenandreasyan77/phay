<?php

namespace App\Http\Requests;

use App\Enums\CarBodyType;
use App\Enums\ContactChannelType;
use App\Enums\ContaminationType;
use App\Enums\InteriorMaterial;
use App\Enums\LeadFulfillmentType;
use App\Enums\Locale;
use App\Enums\PropertyType;
use App\Enums\SoilingLevel;
use App\Models\Product;
use App\Rules\Phone;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:30', new Phone],
            'email' => ['nullable', 'email', 'max:255'],
            'service_slug' => ['nullable', 'string', TenantRule::exists('services', 'slug')],
            'package_slug' => ['nullable', 'string', TenantRule::exists('service_packages', 'slug')],
            // Matched against slug OR any locale-specific slug column — the
            // frontend sends whichever slug is showing on the visitor's
            // current locale, which may differ from the legacy `slug` value
            // once an admin edits a per-locale slug (see market plan).
            'product_slug' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                $exists = Product::query()
                    ->where('slug', $value)
                    ->orWhere('slug_hy', $value)
                    ->orWhere('slug_ru', $value)
                    ->orWhere('slug_en', $value)
                    ->exists();

                if (! $exists) {
                    $fail('The selected product slug is invalid.');
                }
            }],
            'property_type' => ['nullable', Rule::enum(PropertyType::class)],
            'area_sqm' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // Required for product orders (see product_slug above); the
            // frontend's ProductOrderModal always sends one of the two.
            'fulfillment_type' => ['nullable', 'required_with:product_slug', Rule::enum(LeadFulfillmentType::class)],
            'address' => ['nullable', 'required_if:fulfillment_type,delivery', 'string', 'max:255'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'preferred_locale' => ['required', Rule::enum(Locale::class)],
            'consent' => ['required', 'accepted'],
            'source' => ['nullable', 'string', 'max:100'],
            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],

            // Auto Care fields — all optional so the same endpoint keeps
            // serving the cleaning/upholstery request forms unchanged.
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

            // Honeypot — real visitors never fill this in (hidden via CSS).
            // A filled value is checked in LeadController@store, which
            // returns a fake success without persisting.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}

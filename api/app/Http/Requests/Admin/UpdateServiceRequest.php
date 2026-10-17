<?php

namespace App\Http\Requests\Admin;

use App\Enums\CarBodyType;
use App\Enums\ContaminationType;
use App\Enums\InteriorMaterial;
use App\Enums\PriceType;
use App\Enums\SeoRobots;
use App\Enums\ServiceStatus;
use App\Rules\RequiredForPublishedProduct;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $service = $this->route('service');
        $status = (string) $this->input('status', $service?->status?->value ?? ServiceStatus::Draft->value);

        return [
            'category_id' => ['sometimes', 'integer', TenantRule::exists('service_categories', 'id')],
            'slug' => ['sometimes', 'string', 'max:255', TenantRule::unique('services', 'slug')->ignore($service?->id)],
            'icon' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(ServiceStatus::class)],

            'name' => ['sometimes', 'array'],
            'name.hy' => ['required_with:name', 'string', 'max:255'],
            'name.ru' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'short_description' => ['nullable', 'array', new RequiredForPublishedProduct($status, ServiceStatus::Active->value)],
            'short_description.hy' => ['nullable', 'string', 'max:500'],
            'short_description.ru' => ['nullable', 'string', 'max:500'],
            'short_description.en' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'array', new RequiredForPublishedProduct($status, ServiceStatus::Active->value)],
            'description.hy' => ['nullable', 'string', 'max:5000'],
            'description.ru' => ['nullable', 'string', 'max:5000'],
            'description.en' => ['nullable', 'string', 'max:5000'],

            'who_its_for' => ['nullable', 'array'],
            'included_items' => ['nullable', 'array'],
            'excluded_items' => ['nullable', 'array'],
            'process_steps' => ['nullable', 'array'],
            'equipment' => ['nullable', 'array'],
            'duration_note' => ['nullable', 'array'],
            'price_factors' => ['nullable', 'array'],
            'faq' => ['nullable', 'array'],
            'warnings' => ['nullable', 'array'],
            'drying_time_note' => ['nullable', 'array'],

            'car_types' => ['nullable', 'array'],
            'car_types.*' => [Rule::enum(CarBodyType::class)],
            'materials' => ['nullable', 'array'],
            'materials.*' => [Rule::enum(InteriorMaterial::class)],
            'contamination_types' => ['nullable', 'array'],
            'contamination_types.*' => [Rule::enum(ContaminationType::class)],

            'price_type' => ['nullable', Rule::enum(PriceType::class)],
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'price_by_car_type' => ['nullable', 'array'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],

            'seo_title' => ['nullable', 'array'],
            'seo_title.hy' => ['nullable', 'string', 'max:255'],
            'seo_title.ru' => ['nullable', 'string', 'max:255'],
            'seo_title.en' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'array'],
            'seo_description.hy' => ['nullable', 'string', 'max:320'],
            'seo_description.ru' => ['nullable', 'string', 'max:320'],
            'seo_description.en' => ['nullable', 'string', 'max:320'],
            'og_title' => ['nullable', 'array'],
            'og_description' => ['nullable', 'array'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'canonical_override' => ['nullable', 'string', 'max:2048'],
            'seo_robots' => ['nullable', Rule::enum(SeoRobots::class)],

            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', TenantRule::exists('products', 'id')],
        ];
    }
}

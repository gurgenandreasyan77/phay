<?php

namespace App\Http\Requests\Admin;

use App\Enums\AvailabilityStatus;
use App\Enums\CompositionType;
use App\Enums\ProductStatus;
use App\Enums\SeoRobots;
use App\Rules\RequiredForPublishedProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $status = (string) $this->input('status', ProductStatus::Draft->value);

        return [
            // `slug` (legacy, single global column) is derived server-side
            // from slug_en/slug_hy — see Admin\ProductController@store — and
            // kept only so existing lead correlation by product slug (see
            // LeadService) doesn't need a migration of its own.
            'slug_hy' => ['required', 'string', 'max:255', 'unique:products,slug_hy'],
            'slug_ru' => ['required', 'string', 'max:255', 'unique:products,slug_ru'],
            'slug_en' => ['required', 'string', 'max:255', 'unique:products,slug_en'],
            'status' => ['required', Rule::enum(ProductStatus::class)],

            'name' => ['required', 'array'],
            'name.hy' => ['required', 'string', 'max:255'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'array', new RequiredForPublishedProduct($status)],
            'short_description.hy' => ['nullable', 'string', 'max:500'],
            'short_description.ru' => ['nullable', 'string', 'max:500'],
            'short_description.en' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'array'],
            'description.hy' => ['required', 'string', 'max:5000'],
            'description.ru' => ['required', 'string', 'max:5000'],
            'description.en' => ['required', 'string', 'max:5000'],
            'alt' => ['nullable', 'array'],
            'alt.hy' => ['nullable', 'string', 'max:255'],
            'alt.ru' => ['nullable', 'string', 'max:255'],
            'alt.en' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:255'],

            'brand' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'country_of_origin' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'gtin' => ['nullable', 'string', 'max:255'],
            'volume_value' => ['nullable', 'numeric', 'min:0'],
            'volume_unit' => ['nullable', 'string', 'max:32'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'old_price_amount' => ['nullable', 'integer', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'availability_status' => ['nullable', Rule::enum(AvailabilityStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'admin_note' => ['nullable', 'string', 'max:2000'],

            'stains' => ['nullable', 'array'],
            'stains.hy' => ['nullable', 'array'],
            'stains.ru' => ['nullable', 'array'],
            'stains.en' => ['nullable', 'array'],
            'surfaces' => ['nullable', 'array'],
            'surfaces.hy' => ['nullable', 'array'],
            'surfaces.ru' => ['nullable', 'array'],
            'surfaces.en' => ['nullable', 'array'],
            'forbidden_surfaces' => ['nullable', 'array'],
            'forbidden_surfaces.hy' => ['nullable', 'array'],
            'forbidden_surfaces.ru' => ['nullable', 'array'],
            'forbidden_surfaces.en' => ['nullable', 'array'],
            'ph_value' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'composition_type' => ['nullable', Rule::enum(CompositionType::class)],
            'dilution_table' => ['nullable', 'array'],
            'usage_steps' => ['nullable', 'array'],
            'requires_extractor' => ['nullable', 'boolean'],
            'consumption_note' => ['nullable', 'array'],
            'contact_time' => ['nullable', 'array'],
            'rinse_method' => ['nullable', 'array'],
            'safety_notes' => ['nullable', 'array'],
            'storage_conditions' => ['nullable', 'array'],
            'shelf_life' => ['nullable', 'array'],
            'batch_tracked' => ['nullable', 'boolean'],
            'sds_url' => ['nullable', 'string', 'max:2048'],
            'certificate_note' => ['nullable', 'array'],

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
            'primary_keyword' => ['nullable', 'string', 'max:255'],
            'secondary_keywords' => ['nullable', 'array'],
            'secondary_keywords.*' => ['string', 'max:255'],

            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:product_categories,id'],
            'primary_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'related_product_ids' => ['nullable', 'array'],
            'related_product_ids.*' => ['integer', 'exists:products,id'],
            'accessory_product_ids' => ['nullable', 'array'],
            'accessory_product_ids.*' => ['integer', 'exists:products,id'],

            'images' => ['nullable', 'array'],
            'images.*.path' => ['required_with:images', 'string', 'max:255'],
            'images.*.alt' => ['nullable', 'array'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

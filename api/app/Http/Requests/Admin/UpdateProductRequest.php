<?php

namespace App\Http\Requests\Admin;

use App\Enums\AvailabilityStatus;
use App\Enums\CompositionType;
use App\Enums\ProductStatus;
use App\Enums\SeoRobots;
use App\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'slug' => ['sometimes', 'string', 'max:255', TenantRule::unique('products', 'slug')->ignore($product)],
            'slug_hy' => ['sometimes', 'string', 'max:255', TenantRule::unique('products', 'slug_hy')->ignore($product)],
            'slug_ru' => ['sometimes', 'string', 'max:255', TenantRule::unique('products', 'slug_ru')->ignore($product)],
            'slug_en' => ['sometimes', 'string', 'max:255', TenantRule::unique('products', 'slug_en')->ignore($product)],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],

            'name' => ['sometimes', 'array'],
            'name.hy' => ['required_with:name', 'string', 'max:255'],
            'name.ru' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'array'],
            'short_description.hy' => ['nullable', 'string', 'max:500'],
            'short_description.ru' => ['nullable', 'string', 'max:500'],
            'short_description.en' => ['nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'array'],
            'description.hy' => ['required_with:description', 'string', 'max:5000'],
            'description.ru' => ['required_with:description', 'string', 'max:5000'],
            'description.en' => ['required_with:description', 'string', 'max:5000'],
            'alt' => ['sometimes', 'nullable', 'array'],
            'alt.hy' => ['nullable', 'string', 'max:255'],
            'alt.ru' => ['nullable', 'string', 'max:255'],
            'alt.en' => ['nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],

            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_of_origin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gtin' => ['sometimes', 'nullable', 'string', 'max:255'],
            'volume_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'volume_unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'price_amount' => ['sometimes', 'integer', 'min:0'],
            'old_price_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'availability_status' => ['sometimes', 'nullable', Rule::enum(AvailabilityStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'stains' => ['sometimes', 'nullable', 'array'],
            'surfaces' => ['sometimes', 'nullable', 'array'],
            'forbidden_surfaces' => ['sometimes', 'nullable', 'array'],
            'ph_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:14'],
            'composition_type' => ['sometimes', 'nullable', Rule::enum(CompositionType::class)],
            'dilution_table' => ['sometimes', 'nullable', 'array'],
            'usage_steps' => ['sometimes', 'nullable', 'array'],
            'requires_extractor' => ['sometimes', 'nullable', 'boolean'],
            'consumption_note' => ['sometimes', 'nullable', 'array'],
            'contact_time' => ['sometimes', 'nullable', 'array'],
            'rinse_method' => ['sometimes', 'nullable', 'array'],
            'safety_notes' => ['sometimes', 'nullable', 'array'],
            'storage_conditions' => ['sometimes', 'nullable', 'array'],
            'shelf_life' => ['sometimes', 'nullable', 'array'],
            'batch_tracked' => ['sometimes', 'nullable', 'boolean'],
            'sds_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'certificate_note' => ['sometimes', 'nullable', 'array'],

            'seo_title' => ['sometimes', 'nullable', 'array'],
            'seo_title.hy' => ['nullable', 'string', 'max:255'],
            'seo_title.ru' => ['nullable', 'string', 'max:255'],
            'seo_title.en' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'array'],
            'seo_description.hy' => ['nullable', 'string', 'max:320'],
            'seo_description.ru' => ['nullable', 'string', 'max:320'],
            'seo_description.en' => ['nullable', 'string', 'max:320'],
            'og_title' => ['sometimes', 'nullable', 'array'],
            'og_description' => ['sometimes', 'nullable', 'array'],
            'og_image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_override' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'seo_robots' => ['sometimes', 'nullable', Rule::enum(SeoRobots::class)],
            'primary_keyword' => ['sometimes', 'nullable', 'string', 'max:255'],
            'secondary_keywords' => ['sometimes', 'nullable', 'array'],
            'secondary_keywords.*' => ['string', 'max:255'],

            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', TenantRule::exists('product_categories', 'id')],
            'primary_category_id' => ['sometimes', 'nullable', 'integer', TenantRule::exists('product_categories', 'id')],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', TenantRule::exists('services', 'id')],
            'related_product_ids' => ['sometimes', 'array'],
            'related_product_ids.*' => ['integer', TenantRule::exists('products', 'id')],
            'accessory_product_ids' => ['sometimes', 'array'],
            'accessory_product_ids.*' => ['integer', TenantRule::exists('products', 'id')],

            'images' => ['sometimes', 'array'],
            'images.*.path' => ['required_with:images', 'string', 'max:255'],
            'images.*.alt' => ['nullable', 'array'],
            'images.*.is_primary' => ['nullable', 'boolean'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * The publish gate ("don't publish half-translated products") has to
     * look at the *effective* value of each field — request input if
     * present, otherwise whatever is already stored — since a PATCH that
     * only flips `status` to `published` doesn't resend name/description.
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $product = $this->route('product');
            $effectiveStatus = $this->input('status', $product?->status?->value);

            if ($effectiveStatus !== ProductStatus::Published->value) {
                return;
            }

            foreach (['name', 'short_description', 'description'] as $field) {
                $effective = $this->has($field) ? $this->input($field) : $product?->{$field};

                foreach (['hy', 'ru', 'en'] as $locale) {
                    if (trim((string) ($effective[$locale] ?? '')) === '') {
                        $validator->errors()->add(
                            "{$field}.{$locale}",
                            "The {$field}.{$locale} field is required to publish a product."
                        );
                    }
                }
            }
        });
    }
}

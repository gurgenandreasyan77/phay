<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdditionalServiceCategory;
use App\Enums\PriceUnit;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdditionalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['sometimes', 'string', 'max:255', TenantRule::unique('additional_services', 'slug')->ignore($this->route('additionalService'))],
            'category' => ['sometimes', Rule::enum(AdditionalServiceCategory::class)],
            'name' => ['sometimes', 'array'],
            'name.hy' => ['required_with:name', 'string', 'max:255'],
            'name.ru' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'note' => ['nullable', 'array'],
            'note.hy' => ['nullable', 'string', 'max:255'],
            'note.ru' => ['nullable', 'string', 'max:255'],
            'note.en' => ['nullable', 'string', 'max:255'],
            'alt' => ['sometimes', 'array'],
            'alt.hy' => ['required_with:alt', 'string', 'max:255'],
            'alt.ru' => ['required_with:alt', 'string', 'max:255'],
            'alt.en' => ['required_with:alt', 'string', 'max:255'],
            'image' => ['sometimes', 'string', 'max:255'],
            'price_amount' => ['sometimes', 'integer', 'min:0'],
            'price_unit' => ['sometimes', Rule::enum(PriceUnit::class)],
            'discount_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'service_slug' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

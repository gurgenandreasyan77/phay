<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdditionalServiceCategory;
use App\Enums\PriceUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdditionalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255', 'unique:additional_services,slug'],
            'category' => ['required', Rule::enum(AdditionalServiceCategory::class)],
            'name' => ['required', 'array'],
            'name.hy' => ['required', 'string', 'max:255'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'array'],
            'note.hy' => ['nullable', 'string', 'max:255'],
            'note.ru' => ['nullable', 'string', 'max:255'],
            'note.en' => ['nullable', 'string', 'max:255'],
            'alt' => ['required', 'array'],
            'alt.hy' => ['required', 'string', 'max:255'],
            'alt.ru' => ['required', 'string', 'max:255'],
            'alt.en' => ['required', 'string', 'max:255'],
            'image' => ['required', 'string', 'max:255'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'price_unit' => ['nullable', Rule::enum(PriceUnit::class)],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'service_slug' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

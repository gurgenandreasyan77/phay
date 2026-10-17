<?php

namespace App\Http\Requests\Admin;

use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug_hy' => ['required', 'string', 'max:255', TenantRule::unique('product_categories', 'slug_hy')],
            'slug_ru' => ['required', 'string', 'max:255', TenantRule::unique('product_categories', 'slug_ru')],
            'slug_en' => ['required', 'string', 'max:255', TenantRule::unique('product_categories', 'slug_en')],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'name' => ['required', 'array'],
            'name.hy' => ['required', 'string', 'max:255'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'array'],
            'short_description.hy' => ['nullable', 'string', 'max:2000'],
            'short_description.ru' => ['nullable', 'string', 'max:2000'],
            'short_description.en' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('productCategory');

        return [
            'slug_hy' => ['sometimes', 'string', 'max:255', Rule::unique('product_categories', 'slug_hy')->ignore($category)],
            'slug_ru' => ['sometimes', 'string', 'max:255', Rule::unique('product_categories', 'slug_ru')->ignore($category)],
            'slug_en' => ['sometimes', 'string', 'max:255', Rule::unique('product_categories', 'slug_en')->ignore($category)],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'name' => ['sometimes', 'array'],
            'name.hy' => ['required_with:name', 'string', 'max:255'],
            'name.ru' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'array'],
            'short_description.hy' => ['nullable', 'string', 'max:2000'],
            'short_description.ru' => ['nullable', 'string', 'max:2000'],
            'short_description.en' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

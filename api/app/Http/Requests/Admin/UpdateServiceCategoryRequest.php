<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('serviceCategory')?->id;

        return [
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('service_categories', 'slug')->ignore($categoryId)],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'name' => ['sometimes', 'array'],
            'name.hy' => ['required_with:name', 'string', 'max:255'],
            'name.ru' => ['required_with:name', 'string', 'max:255'],
            'name.en' => ['required_with:name', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.hy' => ['nullable', 'string', 'max:1000'],
            'description.ru' => ['nullable', 'string', 'max:1000'],
            'description.en' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

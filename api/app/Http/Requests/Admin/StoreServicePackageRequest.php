<?php

namespace App\Http\Requests\Admin;

use App\Enums\PriceType;
use App\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServicePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'slug' => ['required', 'string', 'max:255', 'unique:service_packages,slug'],
            'status' => ['required', Rule::enum(ServiceStatus::class)],

            'name' => ['required', 'array'],
            'name.hy' => ['required', 'string', 'max:255'],
            'name.ru' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'included_items' => ['nullable', 'array'],

            'price_type' => ['nullable', Rule::enum(PriceType::class)],
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0', 'gte:price_min'],
            'price_by_car_type' => ['nullable', 'array'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

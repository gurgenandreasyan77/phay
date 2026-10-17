<?php

namespace App\Http\Requests\Admin;

use App\Enums\CarBodyType;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceGalleryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', TenantRule::exists('services', 'id')],
            'car_type' => ['nullable', Rule::enum(CarBodyType::class)],
            'label' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'before_image' => ['sometimes', 'string', 'max:255'],
            'after_image' => ['sometimes', 'string', 'max:255'],
            'alt_before' => ['nullable', 'array'],
            'alt_after' => ['nullable', 'array'],
            'performed_at' => ['nullable', 'date'],
            'client_consent' => ['sometimes', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

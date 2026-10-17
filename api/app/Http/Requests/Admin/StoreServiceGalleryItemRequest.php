<?php

namespace App\Http\Requests\Admin;

use App\Enums\CarBodyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceGalleryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'car_type' => ['nullable', Rule::enum(CarBodyType::class)],
            'label' => ['nullable', 'array'],
            'description' => ['nullable', 'array'],
            'before_image' => ['required', 'string', 'max:255'],
            'after_image' => ['required', 'string', 'max:255'],
            'alt_before' => ['nullable', 'array'],
            'alt_after' => ['nullable', 'array'],
            'performed_at' => ['nullable', 'date'],
            // Consent must be recorded explicitly by whoever uploads the
            // pair — the item stays hidden from the public API until both
            // this and `is_published` are true, see
            // ServiceGalleryItem::scopePubliclyVisible().
            'client_consent' => ['required', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

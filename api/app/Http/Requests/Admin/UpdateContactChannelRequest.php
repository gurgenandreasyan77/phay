<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(ContactChannelType::class)],
            'value' => ['sometimes', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

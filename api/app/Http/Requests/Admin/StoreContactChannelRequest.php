<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ContactChannelType::class)],
            'value' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

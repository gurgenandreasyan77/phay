<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['nullable', 'array'],
            'address.hy' => ['nullable', 'string', 'max:500'],
            'address.ru' => ['nullable', 'string', 'max:500'],
            'address.en' => ['nullable', 'string', 'max:500'],
        ];
    }
}

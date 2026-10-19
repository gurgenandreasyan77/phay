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
        // Each form sends its own part: the profile its name, the address form
        // the address. What isn't sent stays as it is.
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.hy' => ['nullable', 'string', 'max:500'],
            'address.ru' => ['nullable', 'string', 'max:500'],
            'address.en' => ['nullable', 'string', 'max:500'],
        ];
    }
}

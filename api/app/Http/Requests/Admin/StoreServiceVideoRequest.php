<?php

namespace App\Http\Requests\Admin;

use App\Enums\SiteVideoKind;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'integer', TenantRule::exists('services', 'id')],
            'kind' => ['sometimes', Rule::enum(SiteVideoKind::class)],
            'title' => ['nullable', 'array'],
            'video_path' => ['required', 'string', 'max:255'],
            'poster_image' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}

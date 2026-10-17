<?php

namespace App\Http\Requests\Admin;

use App\Enums\PageStatus;
use App\Rules\RequiredForPublishedProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $status = (string) $this->input('status', PageStatus::Draft->value);

        return [
            'slug_hy' => ['required', 'string', 'max:255', 'unique:market_task_pages,slug_hy'],
            'slug_ru' => ['required', 'string', 'max:255', 'unique:market_task_pages,slug_ru'],
            'slug_en' => ['required', 'string', 'max:255', 'unique:market_task_pages,slug_en'],
            'status' => ['required', Rule::enum(PageStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'title' => ['required', 'array'],
            'title.hy' => ['required', 'string', 'max:255'],
            'title.ru' => ['required', 'string', 'max:255'],
            'title.en' => ['required', 'string', 'max:255'],
            'intro' => ['nullable', 'array', new RequiredForPublishedProduct($status, PageStatus::Published->value)],
            'intro.hy' => ['nullable', 'string', 'max:4000'],
            'intro.ru' => ['nullable', 'string', 'max:4000'],
            'intro.en' => ['nullable', 'string', 'max:4000'],
            'selection_rules' => ['nullable', 'array'],
            'precautions' => ['nullable', 'array'],
            'seo_title' => ['nullable', 'array'],
            'seo_description' => ['nullable', 'array'],

            'related_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}

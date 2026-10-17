<?php

namespace App\Http\Requests\Admin;

use App\Enums\PageStatus;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $page = $this->route('taskPage');

        return [
            'slug_hy' => ['sometimes', 'string', 'max:255', Rule::unique('market_task_pages', 'slug_hy')->ignore($page)],
            'slug_ru' => ['sometimes', 'string', 'max:255', Rule::unique('market_task_pages', 'slug_ru')->ignore($page)],
            'slug_en' => ['sometimes', 'string', 'max:255', Rule::unique('market_task_pages', 'slug_en')->ignore($page)],
            'status' => ['sometimes', Rule::enum(PageStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],

            'title' => ['sometimes', 'array'],
            'title.hy' => ['required_with:title', 'string', 'max:255'],
            'title.ru' => ['required_with:title', 'string', 'max:255'],
            'title.en' => ['required_with:title', 'string', 'max:255'],
            'intro' => ['sometimes', 'nullable', 'array'],
            'selection_rules' => ['sometimes', 'nullable', 'array'],
            'precautions' => ['sometimes', 'nullable', 'array'],
            'seo_title' => ['sometimes', 'nullable', 'array'],
            'seo_description' => ['sometimes', 'nullable', 'array'],

            'related_service_id' => ['sometimes', 'nullable', 'integer', 'exists:services,id'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator) {
            $page = $this->route('taskPage');
            $effectiveStatus = $this->input('status', $page?->status?->value);

            if ($effectiveStatus !== PageStatus::Published->value) {
                return;
            }

            $effectiveIntro = $this->has('intro') ? $this->input('intro') : $page?->intro;

            foreach (['hy', 'ru', 'en'] as $locale) {
                if (trim((string) ($effectiveIntro[$locale] ?? '')) === '') {
                    $validator->errors()->add("intro.{$locale}", "The intro.{$locale} field is required to publish.");
                }
            }
        });
    }
}

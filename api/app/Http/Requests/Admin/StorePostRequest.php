<?php

namespace App\Http\Requests\Admin;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A blog post of the business's site. Armenian is required; Russian and
 * English fall back to it on the site when left empty.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $postId = $this->route('post')?->id;
        $required = $postId ? 'sometimes' : 'required';
        $slug = TenantRule::unique('posts', 'slug');

        return [
            'slug' => [$required, 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $postId ? $slug->ignore($postId) : $slug],
            'category' => [$required, Rule::enum(PostCategory::class)],
            'status' => [$required, Rule::enum(PostStatus::class)],
            'published_at' => ['nullable', 'date'],
            'author_name' => ['nullable', 'string', 'max:100'],
            'featured_image_path' => ['nullable', 'string', 'max:255'],
            'title' => [$required, 'array'],
            'title.hy' => [$required, 'string', 'max:255'],
            'title.ru' => ['nullable', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'array'],
            'excerpt.*' => ['nullable', 'string', 'max:500'],
            'content' => [$required, 'array'],
            'content.hy' => [$required, 'string', 'max:100000'],
            'content.ru' => ['nullable', 'string', 'max:100000'],
            'content.en' => ['nullable', 'string', 'max:100000'],
            'seo_title' => ['nullable', 'array'],
            'seo_title.*' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'array'],
            'seo_description.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Հասցեն՝ միայն լատինատառ փոքրատառեր, թվեր և գծիկներ (օր.՝ sofa-care-tips)։',
        ];
    }
}

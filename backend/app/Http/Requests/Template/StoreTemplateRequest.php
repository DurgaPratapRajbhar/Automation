<?php

namespace App\Http\Requests\Template;

use App\Enums\TemplateCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Meta's rule: lowercase letters, numbers and underscores
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', Rule::enum(TemplateCategory::class)],
            // e.g. en, en_US, hi
            'language' => ['required', 'string', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'body' => ['required', 'string', 'max:1024'],
            // One sample value per {{n}}, in order (Meta needs them to review the template)
            'examples' => ['nullable', 'array', 'max:20'],
            'examples.*' => ['required', 'string', 'max:200'],
        ];
    }
}

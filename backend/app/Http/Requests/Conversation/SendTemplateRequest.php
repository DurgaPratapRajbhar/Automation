<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;

class SendTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            // Values for {{1}}, {{2}}... in order
            'parameters' => ['nullable', 'array', 'max:20'],
            'parameters.*' => ['required', 'string', 'max:500'],
        ];
    }
}

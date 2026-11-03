<?php

namespace App\Http\Requests\Translation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TranslateReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:4096'],
            // Leave out for the language the customer writes in
            'to' => ['nullable', 'string', Rule::in(array_keys(config('translation.languages')))],
        ];
    }
}

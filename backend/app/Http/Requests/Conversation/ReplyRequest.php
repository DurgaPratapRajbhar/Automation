<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // WhatsApp text message ki limit
            'body' => ['required', 'string', 'max:4096'],
            // Sent in the customer's language: what the owner wrote, and the language it was translated into
            'original' => ['nullable', 'string', 'max:4096'],
            'language' => ['nullable', 'required_with:original', Rule::in(array_keys(config('translation.languages')))],
        ];
    }
}

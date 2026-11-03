<?php

namespace App\Http\Requests\Contacts;

// The preview rules (the file) plus which columns are the phone and the name
class ContactImportRequest extends ContactImportPreviewRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'phone_column' => ['required', 'string', 'max:255'],
            'name_column' => ['nullable', 'string', 'max:255'],
            // "I have these people's permission to message them on WhatsApp"
            'consent' => ['sometimes', 'boolean'],
        ];
    }
}

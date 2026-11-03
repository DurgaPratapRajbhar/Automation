<?php

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;

class ContactImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The type (.xlsx / .csv) is checked when the file is read, with a clearer message
            'file' => ['required', 'file', 'max:'.config('campaigns.max_import_file_kb')],
        ];
    }
}

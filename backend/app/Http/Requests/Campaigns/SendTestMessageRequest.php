<?php

namespace App\Http\Requests\Campaigns;

use Illuminate\Foundation\Http\FormRequest;

class SendTestMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Checked and tidied by the service; "+91 98765 43210" and "9876543210" are both fine
            'phone' => ['required', 'string', 'max:25'],
        ];
    }
}

<?php

namespace App\Http\Requests\Channel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'in:connected,disconnected'],
            'credentials' => ['sometimes', 'array'],
            'credentials.access_token' => ['sometimes', 'string', 'max:2000'],
            'credentials.phone_number_id' => ['sometimes', 'string', 'regex:/^\d{5,30}$/'],
            'credentials.waba_id' => ['sometimes', 'nullable', 'string', 'regex:/^\d{5,30}$/'],
        ];
    }
}

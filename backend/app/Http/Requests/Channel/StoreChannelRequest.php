<?php

namespace App\Http\Requests\Channel;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Abhi sirf meta.whatsapp hai; naya provider aaye to uske credential rules yahan
        return [
            'type' => ['required', 'string', 'max:50'],
            'provider' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'credentials' => ['required', 'array'],
            'credentials.access_token' => ['required', 'string', 'max:2000'],
            'credentials.phone_number_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'credentials.waba_id' => ['nullable', 'string', 'regex:/^\d{5,30}$/'],
        ];
    }
}

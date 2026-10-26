<?php

namespace App\Http\Requests\Channel;

use Illuminate\Foundation\Http\FormRequest;

// What the Meta Embedded Signup popup gives the browser
class ConnectWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:2000'],
            'waba_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'phone_number_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
        ];
    }
}

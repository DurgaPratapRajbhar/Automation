<?php

namespace App\Http\Requests\Customer;

use App\Rules\Gstin;
use App\Rules\Phone;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', new Phone],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gstin' => ['sometimes', 'nullable', new Gstin],
            'state_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}$/'],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
        ];
    }
}

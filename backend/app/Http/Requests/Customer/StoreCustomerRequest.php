<?php

namespace App\Http\Requests\Customer;

use App\Rules\Gstin;
use App\Rules\Phone;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', new Phone],
            'address' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', new Gstin],
            'state_code' => ['nullable', 'string', 'regex:/^\d{2}$/'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\Promises;

use App\Enums\PromiseKind;
use App\Rules\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromiseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(PromiseKind::class)],
            'quote' => ['required', 'string', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            // From a chat (name and number come from it), or typed in
            'conversation_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', new Phone],
        ];
    }
}

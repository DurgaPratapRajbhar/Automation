<?php

namespace App\Http\Requests\Orders;

use App\Enums\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// One change at a time: the delivery step, paid / not paid, or cancel
class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_status' => ['required_without_all:paid,status', Rule::enum(DeliveryStatus::class)],
            'paid' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['cancelled'])],
        ];
    }
}

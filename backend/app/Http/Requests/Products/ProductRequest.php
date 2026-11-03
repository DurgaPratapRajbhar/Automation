<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

// Add and edit use the same rules; an edit may send only the fields it changes
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimes = $this->isMethod('PUT') ? ['sometimes'] : [];

        return [
            'name' => [...$sometimes, 'required', 'string', 'max:255'],
            'price' => [...$sometimes, 'required', 'numeric', 'min:0', 'max:99999999'],
            'unit' => ['nullable', 'string', 'max:30'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            // A service booked for a time: how long it takes
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

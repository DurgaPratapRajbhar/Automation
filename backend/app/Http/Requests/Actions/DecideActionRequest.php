<?php

namespace App\Http\Requests\Actions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approve', 'reject'])]];
    }
}

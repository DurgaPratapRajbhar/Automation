<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Checked by the service: a Google-only user has none yet
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:10', 'max:255', 'confirmed'],
        ];
    }
}

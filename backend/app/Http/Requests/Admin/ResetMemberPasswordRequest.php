<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResetMemberPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Typed twice, so a typo cannot lock the user out
        return [
            'password' => ['required', 'string', 'min:10', 'max:255', 'confirmed'],
        ];
    }
}

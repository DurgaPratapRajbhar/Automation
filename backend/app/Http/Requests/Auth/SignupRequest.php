<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'shop_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:10', 'max:255', 'confirmed'],
            'accepted_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['accepted_terms.accepted' => __('auth.terms_required')];
    }
}

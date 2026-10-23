<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_name' => ['required', 'string', 'max:255'],
            'workspace_type' => ['nullable', 'string', 'max:50'],
            'owner_email' => ['required', 'email', 'max:255'],
            // Needed only when the owner is a new user
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_password' => ['nullable', 'string', 'min:8'],
        ];
    }
}

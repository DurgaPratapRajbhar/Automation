<?php

namespace App\Http\Requests\Admin;

use App\Enums\WorkspaceModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Leave it out for every module
            'modules' => ['sometimes', 'array'],
            'modules.*' => [Rule::enum(WorkspaceModule::class)],
        ];
    }
}

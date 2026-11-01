<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace_name' => ['required', 'string', 'max:255'],
            // Empty = not a trial (paid); a date moves the end of the trial
            'trial_ends_at' => ['nullable', 'date'],
        ];
    }
}

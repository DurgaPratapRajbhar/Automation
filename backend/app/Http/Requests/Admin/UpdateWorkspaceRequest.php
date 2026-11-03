<?php

namespace App\Http\Requests\Admin;

use App\Enums\WorkspaceModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Modules switched on for the shop; leave it out to keep them as they are
            'modules' => ['sometimes', 'array'],
            'modules.*' => [Rule::enum(WorkspaceModule::class)],
        ];
    }
}

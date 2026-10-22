<?php

namespace App\Http\Requests\Workspace;

use App\Enums\GstStatus;
use App\Rules\Gstin;
use App\Rules\Phone;
use App\Rules\UpiId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', new Phone],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}$/'],
            'gst_status' => ['sometimes', Rule::enum(GstStatus::class)],
            'gstin' => ['sometimes', 'nullable', new Gstin],
            'upi_id' => ['sometimes', 'nullable', new UpiId],
        ];
    }
}

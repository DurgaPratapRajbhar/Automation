<?php

namespace App\Http\Requests\Campaigns;

use App\Services\Campaigns\CampaignParameters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'channel_id' => ['required', 'integer'],
            'template_name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['required', 'string', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            // What goes into {{1}}, {{2}}... in order
            'parameters' => ['present', 'array', 'max:20'],
            'parameters.*.source' => ['required', Rule::in(CampaignParameters::SOURCES)],
            'parameters.*.value' => ['required_if:parameters.*.source,column,text', 'nullable', 'string', 'max:500'],
            // Leave out for everyone saved with WhatsApp permission
            'contact_ids' => ['sometimes', 'array', 'min:1', 'max:5000'],
            'contact_ids.*' => ['integer'],
        ];
    }
}

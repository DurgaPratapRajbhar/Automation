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
            // Language of the website chatbot's replies (auto = same as the customer's)
            'reply_style' => ['sometimes', 'nullable', Rule::in(['auto', 'hinglish'])],
            // The language the owner reads and writes in; chats are translated into it
            'inbox_language' => ['sometimes', Rule::in(array_keys(config('translation.languages')))],
            // The Sales agent: switch and the questions it asks a new person (blank ones are dropped)
            'ask_delivery_address' => ['sometimes', 'boolean'],
            'owner_alerts' => ['sometimes', 'boolean'],
            'booking_open_time' => ['sometimes', 'date_format:H:i'],
            'booking_close_time' => ['sometimes', 'date_format:H:i', fn ($attribute, $value, $fail) => $value > $this->input('booking_open_time', $this->attributes->get('workspace')->booking_open_time) || $fail(__('bookings.close_after_open'))],
            'booking_closed_days' => ['sometimes', 'nullable', 'array'],
            'booking_closed_days.*' => ['string', Rule::in(config('bookings.days'))],
            'booking_capacity' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'auto_booking_followups' => ['sometimes', 'boolean'],
            'lead_flow_enabled' => ['sometimes', 'boolean'],
            'lead_questions' => ['sometimes', 'nullable', 'array', 'max:'.config('leads.max_questions')],
            'lead_questions.*' => ['nullable', 'string', 'max:'.config('leads.question_length')],
        ];
    }
}

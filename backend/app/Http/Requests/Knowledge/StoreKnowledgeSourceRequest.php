<?php

namespace App\Http\Requests\Knowledge;

use App\Enums\KnowledgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(KnowledgeType::class)],
            'title' => ['required', 'string', 'max:150'],

            // text source: body zaroori
            'content' => ['required_if:type,text', 'nullable', 'string', 'max:20000'],

            // file source: document zaroori
            'file' => [
                'required_if:type,file',
                'file',
                'mimes:pdf,txt,doc,docx,csv,md',
                'max:10240',
            ],
        ];
    }
}

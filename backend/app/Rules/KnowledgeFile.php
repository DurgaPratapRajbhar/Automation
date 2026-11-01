<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

// A knowledge file must really be what its name says: a PDF starts with %PDF-, a Word (docx) file is
// a zip, and txt/csv/md must be readable text. Renaming some other file does not get it through.
class KnowledgeFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('rules.knowledge_file')->translate();

            return;
        }

        $head = (string) file_get_contents($value->getRealPath(), false, null, 0, 8);

        $genuine = match (strtolower($value->getClientOriginalExtension())) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'docx' => str_starts_with($head, "PK\x03\x04"),
            'txt', 'csv', 'md' => ReadableText::isReadable((string) file_get_contents($value->getRealPath())),
            default => false,
        };

        if (! $genuine) {
            $fail('rules.knowledge_file')->translate();
        }
    }
}

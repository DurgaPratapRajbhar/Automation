<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Text the AI can safely learn from: valid UTF-8, no control codes (NUL etc.), mostly real letters
class ReadableText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isReadable($value)) {
            $fail('rules.readable_text')->translate();
        }
    }

    public static function isReadable(string $text): bool
    {
        if (! mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            return false;
        }

        $length = mb_strlen(trim($text));

        // At least a few real letters, and letters/digits should be a fair part of the text
        return $length > 0 && preg_match_all('/\p{L}/u', $text) >= 3 && preg_match_all('/[\p{L}\p{N}]/u', $text) >= $length * 0.3;
    }
}

<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Digits only, 10 to 15 long (country code allowed, no "+" or spaces)
class Phone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{10,15}$/', $value)) {
            $fail('rules.phone')->translate();
        }
    }
}

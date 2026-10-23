<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// UPI ID (VPA): "name@bank", e.g. "sharma.store@okaxis"
class UpiId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/', $value)) {
            $fail('rules.upi_id')->translate();
        }
    }
}

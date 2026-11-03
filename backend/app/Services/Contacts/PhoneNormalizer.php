<?php

namespace App\Services\Contacts;

/**
 * "+91 98765-43210", "098765 43210", "9876543210" -> "919876543210". null when it cannot be a phone number.
 * A 10-digit number gets the default country code; a longer one is taken to have its country code already.
 */
class PhoneNormalizer
{
    public function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        $country = (string) config('campaigns.default_country_code');

        if (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1); // local style with a leading zero
        }

        if (strlen($digits) === 10) {
            $digits = $country.$digits;
        }

        if (! preg_match('/^\d{10,15}$/', $digits)) {
            return null;
        }

        // Indian mobile numbers start with 6-9 after the country code
        if ($country === '91' && str_starts_with($digits, '91') && strlen($digits) === 12 && ! preg_match('/^91[6-9]/', $digits)) {
            return null;
        }

        return $digits;
    }
}

<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Checks the ID token the "Sign in with Google" button gives the browser, and returns who it is.
class GoogleIdentity
{
    private const TOKEN_INFO = 'https://oauth2.googleapis.com/tokeninfo';

    // ['sub' => Google's id for the person, 'email' => verified email, 'name' => full name]
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');

        if (! $clientId) {
            throw new BusinessRuleException(__('auth.google_not_configured'), 503);
        }

        $response = Http::timeout(10)->get(self::TOKEN_INFO, ['id_token' => $idToken]);
        $claims = $response->json() ?? [];

        // Made for our app, issued by Google, and Google itself verified the email
        $valid = $response->successful()
            && ($claims['aud'] ?? null) === $clientId
            && in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! empty($claims['email'])
            && ! empty($claims['sub']);

        if (! $valid) {
            throw new BusinessRuleException(__('auth.google_invalid'), 401);
        }

        return [
            'sub' => (string) $claims['sub'],
            'email' => Str::lower($claims['email']),
            'name' => $claims['name'] ?? Str::before($claims['email'], '@'),
        ];
    }
}

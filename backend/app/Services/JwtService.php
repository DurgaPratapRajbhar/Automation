<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

class JwtService
{
    public function generateAccessToken(int|string $userId): string
    {
        $privateKey = file_get_contents(config('jwt.private_key'));

        if ($privateKey === false) {
            throw new RuntimeException('JWT private key not found.');
        }

        $now = time();

        $payload = [
            'iss' => config('jwt.issuer'),
            'aud' => config('jwt.audience'),

            // Subject = logged-in user
            'sub' => (string) $userId,

            // Issued at
            'iat' => $now,

            // Not valid before
            'nbf' => $now,

            // Expiration
            'exp' => $now + config('jwt.ttl'),

            // Unique token ID
            'jti' => (string) Str::uuid(),
        ];

        return JWT::encode(
            $payload,
            $privateKey,
            config('jwt.algorithm')
        );
    }

    public function verifyAccessToken(string $token): stdClass
    {
        $publicKey = file_get_contents(config('jwt.public_key'));

        if ($publicKey === false) {
            throw new RuntimeException('JWT public key not found.');
        }

        $payload = JWT::decode(
            $token,
            new Key(
                $publicKey,
                config('jwt.algorithm')
            )
        );

        if (($payload->iss ?? null) !== config('jwt.issuer')) {
            throw new RuntimeException('Invalid JWT issuer.');
        }

        if (($payload->aud ?? null) !== config('jwt.audience')) {
            throw new RuntimeException('Invalid JWT audience.');
        }

        return $payload;
    }
}
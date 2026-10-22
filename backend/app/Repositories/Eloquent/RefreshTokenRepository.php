<?php

namespace App\Repositories\Eloquent;

use App\Models\RefreshToken;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function create(array $data): RefreshToken
    {
        return RefreshToken::create($data);
    }

    public function findByTokenHash(string $tokenHash): ?RefreshToken
    {
        return RefreshToken::where('token_hash', $tokenHash)->first();
    }

    public function revoke(RefreshToken $refreshToken): void
    {
        $refreshToken->update([
            'revoked_at' => now(),
        ]);
    }
}
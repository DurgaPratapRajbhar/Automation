<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use RuntimeException;

class RefreshTokenService
{
    private const TOKEN_TTL_DAYS = 30;

    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepository
    ) {
    }

    public function create(int $userId): string
    {
        // Cryptographically secure random token
        $plainToken = bin2hex(random_bytes(64));

        $tokenHash = hash('sha256', $plainToken);

        $this->refreshTokenRepository->create([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
        ]);
        return $plainToken;
    }

    public function validate(string $plainToken): RefreshToken
    {
        $tokenHash = hash('sha256', $plainToken);

        $refreshToken = $this->refreshTokenRepository
            ->findByTokenHash($tokenHash);

        if (!$refreshToken) {
            throw new RuntimeException('Invalid refresh token.');
        }

        if ($refreshToken->revoked_at !== null) {
            throw new RuntimeException('Refresh token has been revoked.');
        }

        if ($refreshToken->expires_at->isPast()) {
            throw new RuntimeException('Refresh token has expired.');
        }

        return $refreshToken;
    }

    public function revoke(RefreshToken $refreshToken): void
    {
        $this->refreshTokenRepository->revoke($refreshToken);
    }
}
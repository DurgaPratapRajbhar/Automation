<?php

namespace App\Repositories\Contracts;

use App\Models\RefreshToken;

interface RefreshTokenRepositoryInterface
{
    public function create(array $data): RefreshToken;

    public function findByTokenHash(string $tokenHash): ?RefreshToken;

    public function revoke(RefreshToken $refreshToken): void;
}

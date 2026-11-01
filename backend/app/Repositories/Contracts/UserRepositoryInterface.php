<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    public function create(array $data): User;

    public function updatePassword(User $user, string $password): void;

    public function findByGoogleId(string $googleId): ?User;

    // Links a Google login. An account whose email was never verified loses its password: it may have been
    // registered by someone else with this email before the real owner came with Google.
    public function linkGoogle(User $user, string $googleId): void;
}

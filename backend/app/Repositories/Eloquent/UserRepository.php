<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    // The model hashes the password
    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password, 'has_password' => true]);
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    public function linkGoogle(User $user, string $googleId): void
    {
        $wasVerified = $user->email_verified_at !== null;

        $user->forceFill(['google_id' => $googleId, 'email_verified_at' => $user->email_verified_at ?? now()]);

        if (! $wasVerified) {
            $user->forceFill(['password' => Str::random(40), 'has_password' => false]);
        }

        $user->save();
    }
}

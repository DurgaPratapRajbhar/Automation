<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

// Login, token refresh and logout. Shops (and their owners) are created by the super admin: WorkspaceSetupService.
class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JwtService $jwtService,
        private RefreshTokenService $refreshTokenService,
        private WorkspaceRepositoryInterface $workspaceRepository
    ) {}

    // Shop login and super admin login are separate doors: each accepts only its own kind of account
    public function login(string $email, string $password, bool $asSuperAdmin = false): array
    {
        $user = $this->userRepository->findByEmail($email);

        // Same message for every failure, so nobody can probe which kind an account is
        if (! $user || ! Hash::check($password, $user->password) || $user->isSuperAdmin() !== $asSuperAdmin) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $accessToken = $this->jwtService
            ->generateAccessToken($user->id);

        $refreshToken = $this->refreshTokenService
            ->create($user->id);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'workspaces' => $this->workspacesFor($user->id),
        ];
    }

    public function refresh(string $plainRefreshToken): array
    {
        // Token validate karo
        $storedRefreshToken = $this->refreshTokenService
            ->validate($plainRefreshToken);

        // Old refresh token revoke
        $this->refreshTokenService
            ->revoke($storedRefreshToken);

        // New access token
        $accessToken = $this->jwtService
            ->generateAccessToken($storedRefreshToken->user_id);

        // New refresh token
        $newRefreshToken = $this->refreshTokenService
            ->create($storedRefreshToken->user_id);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl'),
        ];
    }

    public function logout(string $plainRefreshToken): void
    {
        $refreshToken = $this->refreshTokenService
            ->validate($plainRefreshToken);

        $this->refreshTokenService
            ->revoke($refreshToken);
    }

    public function workspacesFor(int $userId): array
    {
        return $this->workspaceRepository
            ->getForUser($userId)
            ->map(fn (object $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'type' => $workspace->type,
                'role' => [
                    'id' => $workspace->role_id,
                    'name' => $workspace->role_name,
                    'slug' => $workspace->role_slug,
                    'is_owner' => (bool) $workspace->role_is_system
                        && $workspace->role_slug === Role::OWNER_SLUG,
                ],
            ])
            ->all();
    }
}

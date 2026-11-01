<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
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

        return $this->issueSession($user);
    }

    // Tokens + the user's shops; used after a password login, a signup and a Google login
    public function issueSession(User $user): array
    {
        return [
            'access_token' => $this->jwtService->generateAccessToken($user->id),
            'refresh_token' => $this->refreshTokenService->create($user->id),
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

    // A user with a password must give the current one; a Google-only user has none yet
    public function changePassword(User $user, ?string $currentPassword, string $newPassword): void
    {
        if ($user->has_password && ! Hash::check((string) $currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => [__('auth.current_password_wrong')]]);
        }

        $this->userRepository->updatePassword($user, $newPassword);
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

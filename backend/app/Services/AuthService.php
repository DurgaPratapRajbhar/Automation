<?php

namespace App\Services;

use App\Enums\WorkspaceStatus;
use App\Models\Role;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JwtService $jwtService,
        private RefreshTokenService $refreshTokenService,
        private WorkspaceRepositoryInterface $workspaceRepository,
        private MemberRepositoryInterface $memberRepository,
        private RoleRepositoryInterface $roleRepository,
        private RoleService $roleService
    ) {
    }

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
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

    public function register(
        string $name,
        string $email,
        string $password,
        string $workspaceName,
        string $workspaceType = 'business'
            ): array {
                return DB::transaction(function () use (
                    $name,
                    $email,
                    $password,
                    $workspaceName,
                    $workspaceType
                ) {

                    $user = $this->userRepository->create([
                        'name' => $name,
                        'email' => $email,
                        'password' => $password,
                    ]);

                    $workspace = $this->workspaceRepository->create([
                        'name' => $workspaceName,
                        'slug' => Str::slug($workspaceName) . '-' . Str::lower(Str::random(6)),
                        'type' => $workspaceType,
                        'status' => WorkspaceStatus::Active->value,
                    ]);

                    // Owner + default roles; the new user becomes the owner
                    $ownerRole = $this->roleService->createSystemRoles($workspace->id);

                    $this->memberRepository->add($workspace->id, $user->id);
                    $this->roleRepository->assignToUser($user, $ownerRole);

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

                        'workspace' => [
                            'id' => $workspace->id,
                            'name' => $workspace->name,
                            'slug' => $workspace->slug,
                            'type' => $workspace->type,
                            'role' => Role::OWNER_SLUG,
                        ],
                    ];
                });
    }

    public function workspacesFor(int $userId): array
    {
        return $this->workspaceRepository
            ->getForUser($userId)
            ->map(fn (object $workspace) => [
                'id'   => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'type' => $workspace->type,
                'role' => [
                    'id'       => $workspace->role_id,
                    'name'     => $workspace->role_name,
                    'slug'     => $workspace->role_slug,
                    'is_owner' => (bool) $workspace->role_is_system
                        && $workspace->role_slug === Role::OWNER_SLUG,
                ],
            ])
            ->all();
    }


}
<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Repositories\Contracts\PermissionRepositoryInterface;



class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JwtService $jwtService,
        private RefreshTokenService $refreshTokenService,
        private WorkspaceRepositoryInterface $workspaceRepository,
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository
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
                        'status' => 'active',
                    ]);

                    $ownerRole = $this->roleRepository->create([
                        'workspace_id' => $workspace->id,
                        'name' => 'Owner',
                        'slug' => 'owner',
                        'is_system' => true,
                    ]);

                  

                    $permissionIds = $this->permissionRepository->getAllIds();

                    $ownerRole->permissions()->sync($permissionIds);

                    $user->workspaces()->attach($workspace->id, [
                        'role_id' => $ownerRole->id,
                    ]);

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
                            'role' => 'owner',
                        ],
                    ];
                });
    }


}
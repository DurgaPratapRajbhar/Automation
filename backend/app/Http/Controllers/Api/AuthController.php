<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString()
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => $result,
        ]);
    }

    // Super admin panel login: only super admin accounts get in
    public function adminLogin(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            asSuperAdmin: true
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => $result,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => $user->isSuperAdmin(),
                ],
                'workspaces' => $this->authService->workspacesFor($user->id),
            ],
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->refresh(
                $request->string('refresh_token')->toString()
            );

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully.',
                'data' => $result,
            ]);

        } catch (RuntimeException $exception) {

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 401);
        }
    }

    public function logout(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $this->authService->logout(
                $request->string('refresh_token')->toString()
            );

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        } catch (RuntimeException $exception) {

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 401);
        }
    }

}

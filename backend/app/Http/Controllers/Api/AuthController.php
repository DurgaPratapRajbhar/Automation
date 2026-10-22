<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\RefreshTokenRequest;
use RuntimeException;
use App\Http\Requests\Auth\RegisterRequest;


class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {
    }

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
                ],
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
    public function register(RegisterRequest $request): JsonResponse
    {
            $result = $this->authService->register(
                $request->string('name')->toString(),
                $request->string('email')->toString(),
                $request->string('password')->toString(),
                $request->string('workspace_name')->toString(),
                $request->input('workspace_type', 'business')
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration successful.',
                'data' => $result,
            ], 201);
    }
}
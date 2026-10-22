<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtAuthMiddleware
{
    public function __construct(
        private JwtService $jwtService,
        private UserRepositoryInterface $userRepository
    ) {
  }

    public function handle(Request $request, Closure $next): Response
    {
         $token = $request->bearerToken();
          if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is required.',
            ], 401);
        }

        try {
            $payload = $this->jwtService->verifyAccessToken($token);

            $userId = (int) $payload->sub;

            $user = $this->userRepository->findById($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 401);
            }

            $request->setUserResolver(
                fn () => $user
            );

            return $next($request);

        } catch (Throwable $exception) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired authentication token.',
            ], 401);
        }

        
    }
}

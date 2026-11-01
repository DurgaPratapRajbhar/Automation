<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For the shop's own website: X-Api-Key decides the workspace (no user login).
 * Sets request attribute: workspace.
 */
class AuthenticateApiKey
{
    public function __construct(
        private WorkspaceApiKeyService $apiKeyService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->apiKeyService->findActiveWorkspace($request->header('X-Api-Key'));

        if (! $workspace) {
            return response()->json(['success' => false, 'message' => __('api_key.invalid')], 401);
        }

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}

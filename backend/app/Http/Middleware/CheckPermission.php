<?php

namespace App\Http\Middleware;

use App\Services\WorkspacePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        private WorkspacePermissionService $permissionService
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {

        $role = $request->attributes->get('workspace_role');

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace not resolved.',
            ], 400);
        }

        if (!$this->permissionService->can($role, $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}

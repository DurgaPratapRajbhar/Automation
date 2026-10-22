<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceAccessController extends Controller
{
    public function __construct(
        private WorkspacePermissionService $permissionService
    ) {}

    // What the logged-in user can do here; the frontend shows pages/buttons from this list
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspace = $request->attributes->get('workspace');
        $role = $request->attributes->get('workspace_role');

        return response()->json([
            'success' => true,
            'data' => [
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'type' => $workspace->type,
                ],
                'role' => $role ? [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'is_owner' => $role->isOwner(),
                ] : null,
                'is_super_admin' => $user->isSuperAdmin(),
                'permissions' => $this->permissionService->permissionsOf($user, $role),
            ],
        ]);
    }
}

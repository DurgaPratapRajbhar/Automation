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

    /**
     * Logged-in user is workspace me kya kar sakta hai.
     * Frontend isi list se decide karega kaunsa page / button dikhana hai.
     */
    public function show(Request $request): JsonResponse
    {
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
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'is_owner' => $role->isOwner(),
                ],
                'permissions' => $this->permissionService->permissionsOf($role),
            ],
        ]);
    }
}

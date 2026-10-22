<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->roleService->getRoles($workspace->id),
        ]);
    }

    public function show(Request $request, int $roleId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->roleService->getRole($workspace->id, $roleId),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $role = $this->roleService->create(
            $workspace->id,
            $request->attributes->get('workspace_role'),
            $request->validated('name'),
            $request->validated('permissions')
        );

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'data' => $role,
        ], 201);
    }

    public function update(UpdateRoleRequest $request, int $roleId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $role = $this->roleService->update(
            $workspace->id,
            $request->attributes->get('workspace_role'),
            $roleId,
            $request->validated('name'),
            $request->validated('permissions')
        );

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'data' => $role,
        ]);
    }

    public function destroy(Request $request, int $roleId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $this->roleService->delete(
            $workspace->id,
            $request->attributes->get('workspace_role'),
            $roleId
        );

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }
}

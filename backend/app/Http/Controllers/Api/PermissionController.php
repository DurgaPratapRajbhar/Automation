<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkspacePermissionService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(
        private WorkspacePermissionService $permissionService
    ) {}

    // Tree for the role form checkboxes
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->permissionService->permissionTree(),
        ]);
    }
}

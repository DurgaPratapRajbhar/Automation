<?php

namespace App\Http\Controllers;

use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
 
class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $roles = $this->roleService->getRoles(
            $workspace->id
        );

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }
}
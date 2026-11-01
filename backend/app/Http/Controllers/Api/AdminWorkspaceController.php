<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetMemberPasswordRequest;
use App\Http\Requests\Admin\StoreWorkspaceRequest;
use App\Http\Requests\Admin\UpdateWorkspaceRequest;
use App\Services\AdminWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminWorkspaceController extends Controller
{
    public function __construct(
        private AdminWorkspaceService $adminWorkspaceService
    ) {}

    // New shop + its owner login
    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('workspace.created'),
            'data' => $this->adminWorkspaceService->create($request->validated()),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        return response()->json([
            'success' => true,
            ...$this->adminWorkspaceService->list(
                is_string($search) ? trim($search) : null,
                (int) $request->query('per_page', config('pagination.per_page'))
            )->toArray(),
        ]);
    }

    public function show(int $workspaceId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->adminWorkspaceService->show($workspaceId)]);
    }

    public function update(UpdateWorkspaceRequest $request, int $workspaceId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('workspace.shop_updated'),
            'data' => $this->adminWorkspaceService->update($workspaceId, $request->validated()),
        ]);
    }

    public function resetMemberPassword(ResetMemberPasswordRequest $request, int $workspaceId, int $userId): JsonResponse
    {
        $this->adminWorkspaceService->resetMemberPassword($workspaceId, $userId, $request->validated('password'));

        return response()->json(['success' => true, 'message' => __('workspace.member_password_reset')]);
    }

    public function updateStatus(Request $request, int $workspaceId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(WorkspaceStatus::class)],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->adminWorkspaceService->updateStatus($workspaceId, WorkspaceStatus::from($data['status'])),
        ]);
    }
}

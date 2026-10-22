<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Services\AdminWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminWorkspaceController extends Controller
{
    public function __construct(
        private AdminWorkspaceService $adminWorkspaceService
    ) {}

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

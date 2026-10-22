<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\UpdateWorkspaceProfileRequest;
use App\Services\WorkspaceProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceProfileController extends Controller
{
    public function __construct(
        private WorkspaceProfileService $profileService
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->profileService->get($request->attributes->get('workspace')),
        ]);
    }

    public function update(UpdateWorkspaceProfileRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('workspace.updated'),
            'data' => $this->profileService->update($request->attributes->get('workspace'), $request->validated()),
        ]);
    }
}

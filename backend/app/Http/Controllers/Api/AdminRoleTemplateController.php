<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Services\RoleTemplateService;
use Illuminate\Http\JsonResponse;

class AdminRoleTemplateController extends Controller
{
    public function __construct(
        private RoleTemplateService $templateService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->templateService->list()]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->templateService->create($request->validated()),
        ], 201);
    }

    public function update(UpdateRoleRequest $request, int $templateId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->templateService->update($templateId, $request->validated()),
        ]);
    }

    public function destroy(int $templateId): JsonResponse
    {
        $this->templateService->delete($templateId);

        return response()->json(['success' => true]);
    }
}

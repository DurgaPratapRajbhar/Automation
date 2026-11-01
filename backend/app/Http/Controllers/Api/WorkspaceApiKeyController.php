<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceApiKeyController extends Controller
{
    public function __construct(
        private WorkspaceApiKeyService $apiKeyService
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->apiKeyService->status($request->attributes->get('workspace')),
        ]);
    }

    // Makes a new key (the old one stops working); the full key is in this response only
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('api_key.created'),
            'data' => $this->apiKeyService->regenerate($request->attributes->get('workspace')),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('api_key.revoked'),
            'data' => $this->apiKeyService->revoke($request->attributes->get('workspace')),
        ]);
    }
}

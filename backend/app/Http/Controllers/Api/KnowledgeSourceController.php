<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreKnowledgeSourceRequest;
use App\Http\Requests\Knowledge\UpdateKnowledgeSourceRequest;
use App\Services\KnowledgeSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeSourceController extends Controller
{
    public function __construct(
        private KnowledgeSourceService $knowledgeSourceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $search = $request->query('search');

        return response()->json([
            'success' => true,
            ...$this->knowledgeSourceService->list(
                $workspace->id,
                is_string($search) ? trim($search) : null,
                (int) $request->query('per_page', config('pagination.per_page'))
            )->toArray(),
        ]);
    }

    public function show(Request $request, int $knowledgeSourceId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->knowledgeSourceService->getSource($workspace->id, $knowledgeSourceId),
        ]);
    }

    public function store(StoreKnowledgeSourceRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('knowledge.created'),
            'data' => $this->knowledgeSourceService->create(
                $workspace->id,
                $request->validated(),
                $request->file('file')
            ),
        ], 201);
    }

    public function update(UpdateKnowledgeSourceRequest $request, int $knowledgeSourceId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('knowledge.updated'),
            'data' => $this->knowledgeSourceService->update($workspace->id, $knowledgeSourceId, $request->validated()),
        ]);
    }

    public function destroy(Request $request, int $knowledgeSourceId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $this->knowledgeSourceService->delete($workspace->id, $knowledgeSourceId);

        return response()->json([
            'success' => true,
            'message' => __('knowledge.deleted'),
        ]);
    }
}

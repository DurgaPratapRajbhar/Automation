<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Template\StoreTemplateRequest;
use App\Services\WhatsAppTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppTemplateController extends Controller
{
    public function __construct(
        private WhatsAppTemplateService $templateService
    ) {}

    public function index(Request $request, int $channelId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->templateService->list($workspace->id, $channelId),
        ]);
    }

    public function store(StoreTemplateRequest $request, int $channelId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('templates.created'),
            'data' => $this->templateService->create($workspace->id, $channelId, $request->validated()),
        ], 201);
    }

    public function destroy(Request $request, int $channelId, string $templateName): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $this->templateService->delete($workspace->id, $channelId, $templateName);

        return response()->json([
            'success' => true,
            'message' => __('templates.deleted'),
        ]);
    }
}

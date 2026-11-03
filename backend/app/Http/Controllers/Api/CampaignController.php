<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Services\Campaigns\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(
        private CampaignService $campaignService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            ...$this->campaignService->list($workspace->id, (int) $request->query('per_page', config('pagination.per_page')))->toArray(),
        ]);
    }

    public function show(Request $request, int $campaignId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json(['success' => true, 'data' => $this->campaignService->show($workspace->id, $campaignId)]);
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('campaigns.created'),
            'data' => $this->campaignService->create($workspace->id, $request->user()->id, $request->validated()),
        ], 201);
    }

    public function start(Request $request, int $campaignId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('campaigns.started'),
            'data' => $this->campaignService->start($workspace->id, $campaignId),
        ]);
    }

    public function cancel(Request $request, int $campaignId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('campaigns.cancelled'),
            'data' => $this->campaignService->cancel($workspace->id, $campaignId),
        ]);
    }
}

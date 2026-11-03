<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaigns\DraftCampaignRequest;
use App\Services\Agents\MarketingAgent;
use Illuminate\Http\JsonResponse;

class CampaignDraftController extends Controller
{
    public function __construct(private MarketingAgent $marketing) {}

    // "Sunday grocery offer bhejna hai" -> a message to read, change and send to WhatsApp for approval. Nothing is saved or sent.
    public function store(DraftCampaignRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json(['success' => true, 'data' => $this->marketing->draft($workspace->id, $request->validated('brief'))]);
    }
}

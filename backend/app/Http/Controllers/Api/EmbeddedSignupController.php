<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\ConnectWhatsAppRequest;
use App\Services\EmbeddedSignupService;
use Illuminate\Http\JsonResponse;

class EmbeddedSignupController extends Controller
{
    public function __construct(
        private EmbeddedSignupService $embeddedSignupService
    ) {}

    public function connect(ConnectWhatsAppRequest $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'message' => __('channels.connected'),
            'data' => $this->embeddedSignupService->connect(
                $workspace->id,
                $request->validated('code'),
                $request->validated('waba_id'),
                $request->validated('phone_number_id'),
            ),
        ], 201);
    }
}

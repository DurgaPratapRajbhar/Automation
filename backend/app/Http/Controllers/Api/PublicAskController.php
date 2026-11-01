<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AskRequest;
use App\Services\Contracts\AiResponderInterface;
use Illuminate\Http\JsonResponse;

/**
 * The same AI that answers WhatsApp, for the shop's own website chatbot.
 * Answers from the workspace's Knowledge; the workspace comes from the API key.
 */
class PublicAskController extends Controller
{
    public function __construct(
        private AiResponderInterface $aiResponder
    ) {}

    public function __invoke(AskRequest $request): JsonResponse
    {
        $answer = $this->aiResponder->answer(
            $request->attributes->get('workspace')->id,
            $request->validated('question')
        );

        if ($answer === null) {
            return response()->json(['success' => false, 'message' => __('api_key.ai_unavailable')], 503);
        }

        return response()->json(['success' => true, 'data' => ['answer' => $answer]]);
    }
}

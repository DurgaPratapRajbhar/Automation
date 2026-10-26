<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\ReplyRequest;
use App\Http\Requests\Conversation\SendTemplateRequest;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $search = $request->query('search');

        return response()->json([
            'success' => true,
            ...$this->conversationService->list(
                $workspace->id,
                is_string($search) ? trim($search) : null,
                (int) $request->query('per_page', config('pagination.per_page'))
            )->toArray(),
        ]);
    }

    public function show(Request $request, int $conversationId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->conversationService->show($workspace->id, $conversationId),
        ]);
    }

    public function reply(ReplyRequest $request, int $conversationId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $message = $this->conversationService->reply(
            $workspace->id,
            $conversationId,
            $request->user()->id,
            $request->validated('body')
        );

        return response()->json([
            'success' => true,
            'message' => __($message['status'] === 'failed' ? 'conversations.send_failed' : 'conversations.sent'),
            'data' => $message,
        ], 201);
    }

    public function sendTemplate(SendTemplateRequest $request, int $conversationId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        $message = $this->conversationService->sendTemplate(
            $workspace->id,
            $conversationId,
            $request->user()->id,
            $request->validated('name'),
            $request->validated('language'),
            $request->validated('parameters') ?? []
        );

        return response()->json([
            'success' => true,
            'message' => __($message['status'] === 'failed' ? 'templates.send_failed' : 'templates.sent'),
            'data' => $message,
        ], 201);
    }
}

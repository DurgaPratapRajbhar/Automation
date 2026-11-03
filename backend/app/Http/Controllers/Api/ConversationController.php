<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\ReplyRequest;
use App\Http\Requests\Conversation\SendTemplateRequest;
use App\Http\Requests\Translation\TranslateReplyRequest;
use App\Services\ConversationService;
use App\Services\Translation\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService,
        private TranslationService $translation
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
            $request->validated('body'),
            $request->validated('original'),
            $request->validated('language')
        );

        return response()->json([
            'success' => true,
            'message' => __($message['status'] === 'failed' ? 'conversations.send_failed' : 'conversations.sent'),
            'data' => $message,
        ], 201);
    }

    // "Translate" on one message: it is shown in the Inbox language
    public function translateMessage(Request $request, int $conversationId, int $messageId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->translation->translateMessage($workspace->id, $conversationId, $messageId),
        ]);
    }

    // Look at the reply in the customer's language before sending it
    public function translate(TranslateReplyRequest $request, int $conversationId): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            'data' => $this->translation->previewReply($workspace->id, $conversationId, $request->validated('text'), $request->validated('to')),
        ]);
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

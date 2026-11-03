<?php

namespace App\Services;

use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Agents\BotMessageService;
use App\Services\Contracts\AiResponderInterface;

// Customer ke message par AI jawaab banao, outbound save karo, aur WhatsApp se bhejo.
// AI khud Python ai-service me; yahan sirf orchestration.
class AiReplyService
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        private AiResponderInterface $responder,
        private BotMessageService $bot,
        private AiUsageService $usage,
    ) {}

    public function replyTo(int $messageId): void
    {
        $message = $this->messages->find($messageId);

        // Sirf customer ke aaye text par jawaab
        if (! $message || $message->direction !== 'inbound' || ! $message->body) {
            return;
        }

        $conversation = $this->conversations->find($message->conversation_id);

        if (! $conversation) {
            return;
        }

        // Trial over, shop suspended or today's limit used: stay quiet instead of paying for an answer
        if (! $this->usage->allowed($message->workspace_id)) {
            return;
        }

        $answer = $this->responder->answer($message->workspace_id, $message->body);

        if (! $answer) {
            return;
        }

        $this->bot->send($conversation, $answer);
    }
}

<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Agents\BotMessageService;
use App\Services\Contracts\AiResponderInterface;

// Customer ke message par AI jawaab banao, outbound save karo, aur WhatsApp se bhejo.
// AI khud Python ai-service me; yahan sirf orchestration.
class AiReplyService
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        private WorkspaceRepositoryInterface $workspaces,
        private AiResponderInterface $responder,
        private BotMessageService $bot,
        private AiUsageService $usage,
        private OwnerNotifier $owner,
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

        $answer = $this->responder->answer(
            $message->workspace_id,
            $message->body,
            $this->workspaces->findById($message->workspace_id)?->reply_style ?? 'auto',
            $this->historyBefore($message),
        );

        if (! $answer) {
            return;
        }

        $this->bot->send($conversation, $answer->text);

        if ($answer->handoff) {
            $this->handToOwner($conversation, $message);
        }
    }

    // The chat before this message, oldest first, so the AI knows what "haan" or "karwao" answers
    private function historyBefore(Message $message): array
    {
        return $this->messages->before($message->conversation_id, $message->id, config('bot.history_messages'))
            ->filter(fn (Message $line) => $line->body)
            ->map(fn (Message $line) => [
                'from' => $line->direction === 'inbound' ? 'customer' : 'business',
                'text' => $line->body,
            ])
            ->values()
            ->all();
    }

    // The customer wants the owner: the bot goes quiet in this chat, and the owner is told on WhatsApp
    private function handToOwner(Conversation $conversation, Message $message): void
    {
        $hours = config('bot.human_pause_hours');

        $this->conversations->pauseBot($conversation, now()->addHours($hours));

        $this->owner->notify($conversation->workspace_id, __('alerts.handoff', [
            'customer' => $conversation->customer_name ?: $conversation->customer_phone,
            'message' => $message->body,
            'hours' => $hours,
        ]));
    }
}

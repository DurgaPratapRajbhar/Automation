<?php

namespace App\Services;

use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Contracts\AiResponderInterface;

// Customer ke message par AI jawaab banao, outbound save karo, aur WhatsApp se bhejo.
// AI khud Python ai-service me; yahan sirf orchestration.
class AiReplyService
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        private ChannelService $channelService,
        private AiResponderInterface $responder,
        private OutboundMessageService $outbound,
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

        $answer = $this->responder->answer($message->workspace_id, $message->body);

        if (! $answer) {
            return;
        }

        // Pehle inbox me save (sent_by_user_id null = bot)
        $outbound = $this->messages->create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $message->workspace_id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $answer,
            'status' => 'sent',
            'payload' => ['ai' => true],
        ]);

        // Phir WhatsApp se bhejo; fail hua to status failed + wajah
        $this->outbound->deliver($outbound, fn () => $this->channelService->sendMessage(
            $message->workspace_id,
            $conversation->channel_id,
            $conversation->customer_phone,
            $answer,
        ));

        $this->conversations->touchLastMessage($conversation);
    }
}

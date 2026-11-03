<?php

namespace App\Services\Agents;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiReplyService;

// Answers questions from the business's knowledge. It takes every chat nobody else wants.
class SupportAgent implements AgentInterface
{
    public function __construct(private AiReplyService $replies) {}

    public function handles(Conversation $conversation, Message $message): bool
    {
        return true;
    }

    public function reply(Conversation $conversation, Message $message): void
    {
        $this->replies->replyTo($message->id);
    }
}

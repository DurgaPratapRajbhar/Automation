<?php

namespace App\Services\Agents;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;

// The latest messages of a chat as the agents read them: one line per message
class ChatTranscript
{
    public function __construct(private MessageRepositoryInterface $messages) {}

    public function of(int $conversationId, int $limit): string
    {
        return $this->messages->forConversation($conversationId, $limit)
            ->filter(fn (Message $message) => $message->body)
            ->map(fn (Message $message) => ($message->direction === 'inbound' ? 'Customer' : 'Business').': '.$message->body)
            ->implode("\n");
    }
}

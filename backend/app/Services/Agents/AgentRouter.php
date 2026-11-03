<?php

namespace App\Services\Agents;

use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;

// Picks the agent for a customer's message: the first one that says the chat is its job
class AgentRouter
{
    /** @var AgentInterface[] in order; the last one must take everything */
    private array $agents;

    public function __construct(
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        SalesAgent $sales,
        OperationsAgent $operations,
        SupportAgent $support,
    ) {
        // Operations first: someone who writes about buying is answered, not asked the lead questions (it leaves a chat that is being qualified to Sales)
        $this->agents = [$operations, $sales, $support];
    }

    public function replyTo(int $messageId): void
    {
        $message = $this->messages->find($messageId);

        // Only the customer's text gets an answer
        if (! $message || $message->direction !== 'inbound' || ! $message->body) {
            return;
        }

        $conversation = $this->conversations->find($message->conversation_id);

        foreach ($this->agents as $agent) {
            if ($conversation && $agent->handles($conversation, $message)) {
                $agent->reply($conversation, $message);

                return;
            }
        }
    }
}

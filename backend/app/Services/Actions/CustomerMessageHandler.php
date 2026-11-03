<?php

namespace App\Services\Actions;

use App\Enums\MessageStatus;
use App\Models\AgentAction;
use App\Models\Conversation;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\CustomerReplyService;

// What every action that writes to a customer has in common: it is sent inside WhatsApp's 24-hour window,
// and the customer's message must not be rejected
abstract class CustomerMessageHandler implements ActionHandlerInterface
{
    public function __construct(
        protected MessageRepositoryInterface $messages,
        protected CustomerReplyService $replies,
    ) {}

    /** @return array{message_id: int} */
    protected function sendInWindow(Conversation $conversation, string $text): array
    {
        return ['message_id' => $this->replies->sendWithinWindow($conversation, $text)->id];
    }

    // Nothing was prepared before the Yes, so there is nothing to undo
    public function rejected(AgentAction $action): void {}

    // The customer's message was not rejected by WhatsApp
    public function verify(AgentAction $action, array $result): bool
    {
        return $this->messages->find($result['message_id'])?->status !== MessageStatus::Failed->value;
    }
}

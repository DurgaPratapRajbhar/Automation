<?php

namespace App\Services\Actions;

use App\Enums\ActionType;
use App\Enums\PromiseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AgentAction;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\PromiseRepositoryInterface;
use App\Services\CustomerReplyService;

// The owner said yes to a payment reminder: send it to the customer in the chat
class ReminderSendHandler extends CustomerMessageHandler
{
    public function __construct(
        MessageRepositoryInterface $messages,
        CustomerReplyService $replies,
        private PromiseRepositoryInterface $promises,
        private ConversationRepositoryInterface $conversations,
    ) {
        parent::__construct($messages, $replies);
    }

    public function type(): ActionType
    {
        return ActionType::ReminderSend;
    }

    public function execute(AgentAction $action): array
    {
        $promise = $this->promises->find($action->payload['promise_id']);

        // Paid or cancelled since the agent asked: no reminder
        if (! $promise || $promise->workspace_id !== $action->workspace_id || $promise->status !== PromiseStatus::Open) {
            throw new BusinessRuleException(__('promises.not_open'), 422);
        }

        $conversation = $this->conversations->find((int) $promise->conversation_id) ?? throw new BusinessRuleException(__('promises.not_open'), 422);

        return $this->sendInWindow($conversation, __('promises.reminder', [
            'name' => $promise->customer_name ?? '',
            'amount' => $promise->amount ? ' of ₹'.number_format((float) $promise->amount, 2) : '',
            'date' => $promise->due_on->format('d M'),
        ]));
    }
}

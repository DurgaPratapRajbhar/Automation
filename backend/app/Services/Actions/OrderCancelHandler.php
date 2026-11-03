<?php

namespace App\Services\Actions;

use App\Enums\ActionType;
use App\Enums\MessageStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AgentAction;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Agents\BotMessageService;
use App\Services\OrderService;

// The owner said yes to a customer's cancel request: cancel the order (stock comes back) and tell the customer
class OrderCancelHandler implements ActionHandlerInterface
{
    public function __construct(
        private OrderService $orders,
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private BotMessageService $bot,
    ) {}

    public function type(): ActionType
    {
        return ActionType::OrderCancel;
    }

    public function execute(AgentAction $action): array
    {
        $order = $this->orders->find($action->workspace_id, $action->payload['order_id']);
        // Checked first: a chat that is gone must not fail after the order was cancelled
        $conversation = $this->conversations->find((int) $order->conversation_id) ?? throw new BusinessRuleException(__('conversations.not_found'), 404);
        $this->orders->cancel($action->workspace_id, $order->id);

        $message = $this->bot->send($conversation, __('orders.cancelled', [
            'lines' => $order->lines()->map(fn ($line) => "• {$line}")->implode("\n"),
        ]));

        return ['order_id' => $order->id, 'message_id' => $message->id];
    }

    // No to a cancel request: the order simply stays as it is
    public function rejected(AgentAction $action): void {}

    public function verify(AgentAction $action, array $result): bool
    {
        return $this->orders->find($action->workspace_id, $result['order_id'])->status === OrderStatus::Cancelled
            && $this->messages->find($result['message_id'])?->status !== MessageStatus::Failed->value;
    }
}

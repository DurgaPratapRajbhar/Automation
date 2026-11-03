<?php

namespace App\Services\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AgentAction;
use App\Models\Order;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\CustomerReplyService;

// A message to the customer about a confirmed booking (a reminder before it, feedback after it).
// What differs is the text and which "done" mark is set.
abstract class BookingMessageHandler extends CustomerMessageHandler
{
    public function __construct(
        MessageRepositoryInterface $messages,
        CustomerReplyService $replies,
        private OrderRepositoryInterface $orders,
        private ConversationRepositoryInterface $conversations,
    ) {
        parent::__construct($messages, $replies);
    }

    abstract protected function text(Order $order): string;

    /** 'reminded_at' or 'feedback_asked_at' */
    abstract protected function mark(): string;

    public function execute(AgentAction $action): array
    {
        $order = $this->orders->findInWorkspace($action->workspace_id, $action->payload['order_id']);

        // Cancelled or gone since the agent asked: nothing to say
        if (! $order || $order->status !== OrderStatus::Confirmed || ! $order->conversation_id) {
            throw new BusinessRuleException(__('orders.not_found'), 422);
        }

        $result = $this->sendInWindow($this->conversations->find($order->conversation_id), $this->text($order));

        if ($this->verify($action, $result)) {
            $this->orders->stamp($order, $this->mark());
        }

        return $result;
    }
}

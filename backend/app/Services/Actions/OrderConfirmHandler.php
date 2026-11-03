<?php

namespace App\Services\Actions;

use App\Enums\ActionType;
use App\Enums\MessageStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AgentAction;
use App\Models\Order;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Agents\BotMessageService;
use App\Services\OrderService;

// The owner said yes to an order: take the stock, confirm it, tell the customer
class OrderConfirmHandler implements ActionHandlerInterface
{
    public function __construct(
        private OrderService $orders,
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private BotMessageService $bot,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function type(): ActionType
    {
        return ActionType::OrderConfirm;
    }

    public function execute(AgentAction $action): array
    {
        $order = $this->orders->find($action->workspace_id, $action->payload['order_id']);
        // Checked first: a chat that is gone must not fail after the stock was taken
        $conversation = $this->conversations->find((int) $order->conversation_id) ?? throw new BusinessRuleException(__('conversations.not_found'), 404);
        $this->orders->confirm($order);

        $message = $this->bot->send($conversation, $this->confirmation($order));

        return ['order_id' => $order->id, 'message_id' => $message->id];
    }

    // A draft nobody wants must not block the chat's next order or hold a booking time
    public function rejected(AgentAction $action): void
    {
        $this->orders->cancelIfDraft($action->workspace_id, $action->payload['order_id']);
    }

    // Confirmed in our books, and the customer's message was not rejected
    public function verify(AgentAction $action, array $result): bool
    {
        $order = $this->orders->find($action->workspace_id, $result['order_id']);

        return $order->status === OrderStatus::Confirmed
            && $this->messages->find($result['message_id'])?->status !== MessageStatus::Failed->value;
    }

    // How to pay by UPI: the id and a pay link with the amount filled in (the owner marks the order paid when the money arrives)
    private function payment(Order $order): string
    {
        $workspace = $this->workspaces->findById($order->workspace_id);

        if (! $workspace->upi_id) {
            return '';
        }

        return __('orders.confirmed_payment', [
            'total' => number_format((float) $order->total, 2),
            'upi' => $workspace->upi_id,
            'link' => 'upi://pay?'.http_build_query(['pa' => $workspace->upi_id, 'pn' => $workspace->name, 'am' => $order->total, 'cu' => 'INR', 'tn' => "Order {$order->id}"], '', '&', PHP_QUERY_RFC3986),
        ]);
    }

    private function confirmation(Order $order): string
    {
        return __('orders.confirmed', [
            'lines' => $order->lines()->map(fn ($line) => "• {$line}")->implode("\n"),
            'total' => number_format((float) $order->total, 2),
            'address' => $order->address ? __('orders.confirmed_address', ['address' => $order->address]) : '',
            'payment' => $this->payment($order),
            'when' => $order->booking_at ? __('orders.confirmed_booking', ['at' => $order->booking_at->format('d M Y, h:i A')]) : '',
        ]);
    }
}

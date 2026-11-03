<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Enums\MessageStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

// Orders and bookings taken in a chat. The total always comes from our own prices, never from the AI.
class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ProductRepositoryInterface $products,
        private ConversationRepositoryInterface $conversations,
        private CustomerReplyService $replies,
        private ActionRepositoryInterface $actions,
    ) {}

    /**
     * A draft for the owner to confirm.
     *
     * @param  array<int, array{product: Product, quantity: int}>  $lines
     */
    public function createDraft(Conversation $conversation, array $lines, ?string $bookingAt, ?string $address = null, ?int $durationMinutes = null): Order
    {
        $items = array_map(fn (array $line) => [
            'product_id' => $line['product']->id,
            'name' => $line['product']->name,
            'price' => $line['product']->price,
            'quantity' => $line['quantity'],
        ], $lines);

        return $this->orders->create([
            'workspace_id' => $conversation->workspace_id,
            'conversation_id' => $conversation->id,
            'customer_name' => $conversation->customer_name,
            'customer_phone' => $conversation->customer_phone,
            'address' => $address,
            'total' => array_sum(array_map(fn (array $item) => $item['price'] * $item['quantity'], $items)),
            'booking_at' => $bookingAt,
            'duration_minutes' => $bookingAt ? $durationMinutes : null,
        ], $items);
    }

    // $status null = every status
    public function list(int $workspaceId, ?OrderStatus $status, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->orders
            ->cursorForWorkspace($workspaceId, $status, $perPage)
            ->through(fn (Order $order) => $this->present($order));
    }

    public function find(int $workspaceId, int $orderId): Order
    {
        return $this->orders->findInWorkspace($workspaceId, $orderId)
            ?? throw new BusinessRuleException(__('orders.not_found'), 404);
    }

    /**
     * The owner marks a confirmed order on the way or delivered, and the customer is told. The mark is kept even when the
     * customer cannot be told (WhatsApp's 24-hour rule); the answer says so.
     *
     * @return array{order: array, told: bool, reason: string|null}
     */
    public function updateDelivery(int $workspaceId, int $orderId, string $deliveryStatus): array
    {
        $order = $this->find($workspaceId, $orderId);

        if ($order->status !== OrderStatus::Confirmed || $order->booking_at || ! $order->conversation_id) {
            throw new BusinessRuleException(__('orders.not_deliverable'), 422);
        }

        $this->orders->setDeliveryStatus($order, $deliveryStatus);

        try {
            $message = $this->replies->sendWithinWindow($this->conversations->find($order->conversation_id), __("orders.{$deliveryStatus}"));
            $told = $message->status !== MessageStatus::Failed->value;
            $reason = $told ? null : $message->error;
        } catch (BusinessRuleException $e) {
            $told = false;
            $reason = $e->getMessage();
        }

        return ['order' => $this->present($order->refresh()), 'told' => $told, 'reason' => $reason];
    }

    /** The owner marks a confirmed order paid (or not paid) */
    public function setPaid(int $workspaceId, int $orderId, bool $paid): array
    {
        $order = $this->find($workspaceId, $orderId);

        if ($order->status !== OrderStatus::Confirmed) {
            throw new BusinessRuleException(__('orders.not_payable'), 422);
        }

        $this->orders->setPaidAt($order, $paid ? now() : null);

        return $this->present($order->refresh());
    }

    // Cancel the order only if it is still a draft (the owner said no to it); nothing happens otherwise
    public function cancelIfDraft(int $workspaceId, int $orderId): void
    {
        $order = $this->orders->findInWorkspace($workspaceId, $orderId);

        if ($order?->status === OrderStatus::Draft) {
            $this->cancel($workspaceId, $orderId);
        }
    }

    // Cancel a draft or confirmed order; a confirmed one gives its stock back (and a booking frees its time)
    public function cancel(int $workspaceId, int $orderId): array
    {
        $order = $this->find($workspaceId, $orderId);

        if ($order->status === OrderStatus::Cancelled) {
            throw new BusinessRuleException(__('orders.not_cancellable'), 422);
        }

        DB::transaction(function () use ($order) {
            if ($order->status === OrderStatus::Confirmed) {
                foreach ($order->items->whereNotNull('product_id') as $item) {
                    $this->products->restock($item->product_id, $item->quantity);
                }
            }

            $this->orders->setStatus($order, OrderStatus::Cancelled);

            // A draft that was waiting for the owner's Yes does not wait any more
            $this->actions->rejectPending($order->workspace_id, ActionType::OrderConfirm, 'order_id', $order->id);
        });

        return $this->present($order->refresh());
    }

    // Confirm: take the stock (all or nothing), then the order is confirmed
    public function confirm(Order $order): void
    {
        if ($order->status !== OrderStatus::Draft) {
            throw new BusinessRuleException(__('orders.not_a_draft'), 422);
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product_id && ! $this->products->reserve($item->product_id, $item->quantity)) {
                    throw new BusinessRuleException(__('orders.not_enough_stock', ['name' => $item->name]), 422);
                }
            }

            $this->orders->setStatus($order, OrderStatus::Confirmed);
        });
    }

    private function present(Order $order): array
    {
        return [
            'id' => $order->id,
            'conversation_id' => $order->conversation_id,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'address' => $order->address,
            'status' => $order->status->value,
            'delivery_status' => $order->delivery_status,
            'paid' => $order->paid_at !== null,
            'total' => $order->total,
            // The business's own wall-clock time, with no time zone: the screen shows it as it is (as a UTC "Z" time it would shift by hours)
            'booking_at' => $order->booking_at?->format('Y-m-d\TH:i:s'),
            'created_at' => $order->created_at,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
            ])->all(),
        ];
    }
}

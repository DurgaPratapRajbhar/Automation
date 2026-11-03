<?php

namespace App\Repositories\Contracts;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    // $items = [['product_id' => .., 'name' => .., 'price' => .., 'quantity' => ..], ...]
    public function create(array $order, array $items): Order;

    // Newest first, with their items. $status null = every status
    public function cursorForWorkspace(int $workspaceId, ?OrderStatus $status, int $perPage): CursorPaginator;

    public function findInWorkspace(int $workspaceId, int $orderId): ?Order;

    public function setStatus(Order $order, OrderStatus $status): void;

    // The time the money was seen, or null
    public function setPaidAt(Order $order, ?CarbonInterface $paidAt): void;

    // on_the_way | delivered
    public function setDeliveryStatus(Order $order, string $deliveryStatus): void;

    // Draft and confirmed bookings that start between these two business-time strings (YYYY-MM-DD HH:MM:SS)
    public function bookingsStartingBetween(int $workspaceId, string $from, string $to): Collection;

    // Confirmed bookings of a chat, starting between these two business-time strings, not yet reminded
    public function toRemind(int $workspaceId, string $from, string $to): Collection;

    // Confirmed bookings of a chat, starting between these two business-time strings, feedback not yet asked
    public function toAskFeedback(int $workspaceId, string $from, string $to): Collection;

    // Remember that a follow-up was sent: 'reminded_at' or 'feedback_asked_at'
    public function stamp(Order $order, string $column): void;

    // Orders confirmed in this time: how many and how much
    public function confirmedBetween(int $workspaceId, CarbonInterface $from, CarbonInterface $to): array;

    // The drafts of this chat that wait for the owner, with their items
    public function draftsOfChat(int $conversationId): Collection;
}

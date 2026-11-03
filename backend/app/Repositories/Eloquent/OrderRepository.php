<?php

namespace App\Repositories\Eloquent;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderRepository implements OrderRepositoryInterface
{
    public function create(array $order, array $items): Order
    {
        return DB::transaction(function () use ($order, $items) {
            $created = Order::create($order)->refresh();
            $created->items()->createMany($items);

            return $created->load('items');
        });
    }

    public function cursorForWorkspace(int $workspaceId, ?OrderStatus $status, int $perPage): CursorPaginator
    {
        return Order::with('items')
            ->where('workspace_id', $workspaceId)
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findInWorkspace(int $workspaceId, int $orderId): ?Order
    {
        return Order::with('items')->where('workspace_id', $workspaceId)->find($orderId);
    }

    public function setStatus(Order $order, OrderStatus $status): void
    {
        $order->update(['status' => $status->value] + ($status === OrderStatus::Confirmed ? ['confirmed_at' => now()] : []));
    }

    public function bookingsStartingBetween(int $workspaceId, string $from, string $to): Collection
    {
        return Order::where('workspace_id', $workspaceId)
            ->whereIn('status', [OrderStatus::Draft->value, OrderStatus::Confirmed->value])
            ->whereNotNull('booking_at')
            ->whereBetween('booking_at', [$from, $to])
            ->get();
    }

    public function toRemind(int $workspaceId, string $from, string $to): Collection
    {
        return $this->confirmedBookings($workspaceId, $from, $to)->whereNull('reminded_at')->get();
    }

    public function toAskFeedback(int $workspaceId, string $from, string $to): Collection
    {
        return $this->confirmedBookings($workspaceId, $from, $to)->whereNull('feedback_asked_at')->get();
    }

    public function stamp(Order $order, string $column): void
    {
        if (! in_array($column, ['reminded_at', 'feedback_asked_at'], true)) {
            throw new InvalidArgumentException("Not a follow-up mark: {$column}");
        }

        $order->update([$column => now()]);
    }

    private function confirmedBookings(int $workspaceId, string $from, string $to)
    {
        return Order::with('items')
            ->where('workspace_id', $workspaceId)
            ->where('status', OrderStatus::Confirmed->value)
            ->whereNotNull('conversation_id')
            ->whereBetween('booking_at', [$from, $to]);
    }

    public function confirmedBetween(int $workspaceId, CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = Order::where('workspace_id', $workspaceId)
            ->where('status', OrderStatus::Confirmed->value)
            // The times come in the business's time zone; the database keeps the app's
            ->whereBetween('confirmed_at', [$from->copy()->setTimezone(config('app.timezone')), $to->copy()->setTimezone(config('app.timezone'))]);

        return ['count' => $orders->count(), 'total' => (float) $orders->sum('total')];
    }

    public function setPaidAt(Order $order, ?CarbonInterface $paidAt): void
    {
        $order->update(['paid_at' => $paidAt]);
    }

    public function setDeliveryStatus(Order $order, string $deliveryStatus): void
    {
        $order->update(['delivery_status' => $deliveryStatus]);
    }

    public function latestOpenOfChat(int $conversationId): ?Order
    {
        return Order::with('items')
            ->where('conversation_id', $conversationId)
            ->whereIn('status', [OrderStatus::Draft->value, OrderStatus::Confirmed->value])
            ->where(fn ($query) => $query->whereNull('delivery_status')->orWhere('delivery_status', '!=', 'delivered'))
            ->orderByDesc('id')
            ->first();
    }

    public function draftsOfChat(int $conversationId): Collection
    {
        return Order::with('items')->where('conversation_id', $conversationId)->where('status', OrderStatus::Draft->value)->get();
    }
}

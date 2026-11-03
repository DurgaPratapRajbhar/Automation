<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    // What the owner can change: on the way / delivered (the customer is told), paid, or cancel
    public function update(UpdateOrderRequest $request, int $orderId): JsonResponse
    {
        $workspaceId = $request->attributes->get('workspace')->id;
        $changes = $request->validated();

        if (isset($changes['delivery_status'])) {
            $result = $this->orders->updateDelivery($workspaceId, $orderId, $changes['delivery_status']);
            $message = $result['told'] ? __('orders.delivery_updated') : __('orders.delivery_updated_not_told', ['reason' => $result['reason']]);
            $order = $result['order'];
        } else {
            $order = isset($changes['paid'])
                ? $this->orders->setPaid($workspaceId, $orderId, (bool) $changes['paid'])
                : $this->orders->cancel($workspaceId, $orderId);
            $message = __('orders.updated');
        }

        return response()->json(['success' => true, 'message' => $message, 'data' => $order]);
    }

    // ?status=draft | confirmed | cancelled; anything else = every status
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        return response()->json([
            'success' => true,
            ...$this->orders->list(
                $workspace->id,
                OrderStatus::tryFrom((string) $request->query('status')),
                (int) $request->query('per_page', config('pagination.per_page')),
            )->toArray(),
        ]);
    }
}

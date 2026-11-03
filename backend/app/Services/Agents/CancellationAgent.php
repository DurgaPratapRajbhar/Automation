<?php

namespace App\Services\Agents;

use App\Enums\ActionType;
use App\Enums\AgentName;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Actions\ActionService;
use App\Services\OwnerNotifier;
use Illuminate\Support\Str;

// A customer who has an open order and writes "cancel": the customer is told the shop was asked, the owner decides (a Verified Action).
// No AI is used: the words are in config/operations.php.
class CancellationAgent implements AgentInterface
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ActionRepositoryInterface $actionRecords,
        private ActionService $actions,
        private BotMessageService $bot,
        private OwnerNotifier $owner,
    ) {}

    public function handles(Conversation $conversation, Message $message): bool
    {
        return $message->body
            && Str::contains(Str::lower($message->body), config('operations.cancel_words'))
            && $this->orders->latestOpenOfChat($conversation->id) !== null;
    }

    public function reply(Conversation $conversation, Message $message): void
    {
        $order = $this->orders->latestOpenOfChat($conversation->id);

        $this->bot->send($conversation, __('orders.cancel_asked'), AgentName::Operations);

        // Saying it twice does not ask the owner twice
        if ($this->actionRecords->existsRecent($order->workspace_id, ActionType::OrderCancel, 'order_id', $order->id, now()->subHours(config('operations.cancel_ask_hours')))) {
            return;
        }

        $customer = $order->customer_name ?? '+'.$order->customer_phone;
        $details = $order->lines()->implode(', ').' = ₹'.number_format((float) $order->total, 2);

        $this->actions->propose(
            $order->workspace_id,
            AgentName::Operations,
            ActionType::OrderCancel,
            "Cancel order: {$details} for {$customer}",
            ['order_id' => $order->id],
            $conversation->id,
        );

        $this->owner->notify($order->workspace_id, __('alerts.cancel_request', ['customer' => $customer, 'details' => $details]));
    }
}

<?php

namespace App\Services\Actions;

use App\Enums\ActionType;
use App\Models\Order;

class FeedbackRequestHandler extends BookingMessageHandler
{
    public function type(): ActionType
    {
        return ActionType::FeedbackRequest;
    }

    protected function text(Order $order): string
    {
        return __('bookings.feedback', ['name' => $order->customer_name ?? '', 'what' => $order->what()]);
    }

    protected function mark(): string
    {
        return 'feedback_asked_at';
    }
}

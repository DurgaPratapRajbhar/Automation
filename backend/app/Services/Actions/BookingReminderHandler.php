<?php

namespace App\Services\Actions;

use App\Enums\ActionType;
use App\Models\Order;

class BookingReminderHandler extends BookingMessageHandler
{
    public function type(): ActionType
    {
        return ActionType::BookingReminder;
    }

    protected function text(Order $order): string
    {
        return __('bookings.reminder', [
            'name' => $order->customer_name ?? '',
            'what' => $order->what(),
            'when' => $order->booking_at->format('D d M, h:i A'),
        ]);
    }

    protected function mark(): string
    {
        return 'reminded_at';
    }
}

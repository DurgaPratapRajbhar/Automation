<?php

namespace App\Enums;

// What an agent may ask to do. Each type has one handler class in app/Services/Actions.
enum ActionType: string
{
    case OrderConfirm = 'order.confirm';
    case OrderCancel = 'order.cancel';
    case ReminderSend = 'reminder.send';
    case ReportSend = 'report.send';
    case BookingReminder = 'booking.reminder';
    case FeedbackRequest = 'feedback.request';
}

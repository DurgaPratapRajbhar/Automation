<?php

namespace App\Services\Agents\Operations;

use App\Enums\ActionType;
use App\Enums\AgentName;
use App\Models\Order;
use App\Models\Workspace;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Actions\ActionService;
use App\Services\Bookings\BookingSlotService;
use App\Services\OwnerNotifier;
use Illuminate\Support\Carbon;

// What the Operations agent does on a schedule, not in answer to a customer: the daily report, booking reminders and feedback
class OperationsRoutines
{
    public function __construct(
        private OrderRepositoryInterface $orders,
        private ActionService $actions,
        private ActionRepositoryInterface $actionRepository,
        private OwnerNotifier $owner,
        private WorkspaceRepositoryInterface $workspaces,
        private BookingSlotService $slots,
    ) {}

    /**
     * Asks the owner to send today's report to their own WhatsApp, once a day. Returns false when there is no owner
     * number to send to, or it was already asked today.
     */
    public function proposeReport(int $workspaceId): bool
    {
        $phone = $this->owner->phone($workspaceId);
        $today = now(config('promises.timezone'))->toDateString();

        if (! $phone || $this->actionRepository->existsRecent($workspaceId, ActionType::ReportSend, 'date', $today, now()->subDay())) {
            return false;
        }

        $this->actions->propose($workspaceId, AgentName::Operations, ActionType::ReportSend, __('reports.summary', ['phone' => $phone]), ['date' => $today]);

        return true;
    }

    /**
     * Suggests a reminder for every confirmed booking that is close, and a feedback request for every one that
     * has just ended. When the owner switched on the standing yes, they go out now; otherwise they wait for a Yes.
     * Returns how many were suggested.
     */
    public function proposeFollowUps(int $workspaceId): int
    {
        $workspace = $this->workspaces->findById($workspaceId);
        $now = now($this->slots->timezone());
        $suggested = 0;

        $reminders = $this->orders->toRemind($workspaceId, $now->toDateTimeString(), $now->copy()->addHours(config('bookings.reminder_hours_before'))->toDateTimeString());
        foreach ($reminders as $order) {
            $suggested += (int) $this->suggest($workspace, $order, ActionType::BookingReminder, __('bookings.remind_summary', ['name' => $this->who($order), 'what' => $order->what(), 'when' => $order->booking_at->format('D d M, h:i A')]));
        }

        $delay = config('bookings.feedback_after_hours');
        $ended = $this->orders->toAskFeedback($workspaceId, $now->copy()->subDays(config('bookings.feedback_max_days'))->toDateTimeString(), $now->toDateTimeString())
            ->filter(fn (Order $order) => Carbon::parse($order->booking_at->toDateTimeString(), $this->slots->timezone())->addMinutes($order->duration_minutes ?? config('bookings.default_minutes'))->addHours($delay)->lte($now));
        foreach ($ended as $order) {
            $suggested += (int) $this->suggest($workspace, $order, ActionType::FeedbackRequest, __('bookings.feedback_summary', ['name' => $this->who($order), 'what' => $order->what()]));
        }

        return $suggested;
    }

    private function suggest(Workspace $workspace, Order $order, ActionType $type, string $summary): bool
    {
        if ($this->actionRepository->existsRecent($workspace->id, $type, 'order_id', $order->id, now()->subDays(config('bookings.quiet_days')))) {
            return false;
        }

        $action = $this->actions->propose($workspace->id, AgentName::Operations, $type, $summary, ['order_id' => $order->id], $order->conversation_id);

        if ($workspace->auto_booking_followups) {
            $this->actions->approve($workspace->id, $action->id, null);
        }

        return true;
    }

    private function who(Order $order): string
    {
        return $order->customer_name ?? '+'.$order->customer_phone;
    }
}

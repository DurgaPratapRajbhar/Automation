<?php

namespace App\Services\Agents;

use App\Enums\ActionType;
use App\Enums\AgentName;
use App\Enums\LeadStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Workspace;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Actions\ActionService;
use App\Services\Agents\Operations\OperationsInterface;
use App\Services\AiUsageService;
use App\Services\Bookings\BookingSlotService;
use App\Services\OrderService;
use App\Services\OwnerNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

// Answers price and availability from the real product list, and takes orders and bookings (for any kind of business).
// It never confirms anything: it writes a draft order and asks the owner (a Verified Action).
class OperationsAgent implements AgentInterface
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private OrderRepositoryInterface $orders,
        private OrderService $orderService,
        private OperationsInterface $operations,
        private BotMessageService $bot,
        private ActionService $actions,
        private AiUsageService $usage,
        private ChatTranscript $transcript,
        private OwnerNotifier $owner,
        private WorkspaceRepositoryInterface $workspaces,
        private BookingSlotService $slots,
        private MessageRepositoryInterface $messages,
    ) {}

    // The business sells something, and the message is about buying or asking about it, or answers the orders assistant
    public function handles(Conversation $conversation, Message $message): bool
    {
        $products = $this->products->activeForWorkspace($conversation->workspace_id);

        if ($products->isEmpty() || ! $message->body || $conversation->lead_status === LeadStatus::Qualifying->value) {
            return false;
        }

        if ($this->inOrderTalk($conversation)) {
            return true;
        }

        $words = $this->words($message->body);

        return count(array_intersect($words, config('operations.hints'))) > 0 || $products->contains(fn (Product $product) => $this->mentions($product, $words));
    }

    public function reply(Conversation $conversation, Message $message): void
    {
        if (! $this->usage->allowed($conversation->workspace_id)) {
            return;
        }

        $workspace = $this->workspaces->findById($conversation->workspace_id);
        $transcript = $this->transcript->of($conversation->id, config('operations.history'));
        $products = $this->shown($this->products->activeForWorkspace($workspace->id), $transcript);

        $result = $this->operations->respond(
            $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'unit' => $product->unit,
                'stock' => $product->stock,
            ])->all(),
            $transcript,
            now(config('promises.timezone'))->toDateString(),
            (bool) $workspace->ask_delivery_address,
            // Only a business that has timed services talks about bookings
            $products->contains(fn (Product $product) => $product->duration_minutes) ? $this->slots->rules($workspace, $this->slots->duration([])) : null,
        );

        if (! $result) {
            return;
        }

        $lines = $result['ready'] ? $this->lines($products, $result) : [];
        $bookingAt = $result['ready'] ? $this->bookingTime($result['booking_at']) : null;
        $minutes = $this->slots->duration(array_column($lines, 'product'));
        $notFree = $bookingAt ? $this->unavailable($workspace, $bookingAt, $minutes) : null;
        $wrongPrice = $this->quotesWrongPrice($result['reply'], $products, $lines);

        // A time that cannot work is never agreed, and a price that is not ours is never shown: the customer is told the truth instead
        $this->bot->send($conversation, $notFree ?? ($wrongPrice ? __('operations.price_unsure') : $result['reply']), AgentName::Operations);

        if (! $notFree && ! $wrongPrice && $result['ready'] && ! $this->orders->draftsOfChat($conversation->id)->contains(fn (Order $draft) => $draft->sameAs($lines, $bookingAt))) {
            $this->proposeOrder($conversation, $workspace, $lines, $bookingAt, $minutes, $result['address'] ?? null);
        }
    }

    // Our own prices and stock decide what the order holds; items the AI got wrong are dropped
    private function lines(Collection $products, array $result): array
    {
        return collect($result['items'])
            ->map(fn (array $item) => ['product' => $products->firstWhere('id', $item['product_id']), 'quantity' => $item['quantity']])
            ->filter(fn (array $line) => $line['product']?->available() && $line['quantity'] <= config('operations.max_quantity') && ($line['product']->stock === null || $line['product']->stock >= $line['quantity']))
            ->values()
            ->all();
    }

    // The message to send instead of the AI's, when the time asked for cannot be booked
    private function unavailable(Workspace $workspace, string $bookingAt, int $minutes): ?string
    {
        $start = Carbon::parse($bookingAt, $this->slots->timezone());
        $reason = $this->slots->check($workspace, $start, $minutes);

        if (! $reason) {
            return null;
        }

        $free = array_map(fn (Carbon $time) => $time->format('D d M, h:i A'), $this->slots->nextFree($workspace, $start, $minutes));

        return __('bookings.unavailable', [
            'when' => $start->format('D d M, h:i A'),
            'reason' => __("bookings.reason.{$reason}", ['open' => $workspace->booking_open_time, 'close' => $workspace->booking_close_time]),
            'options' => $free ? __('bookings.options', ['times' => implode(' | ', $free)]) : __('bookings.no_options'),
        ]);
    }

    private function proposeOrder(Conversation $conversation, Workspace $workspace, array $lines, ?string $bookingAt, int $minutes, ?string $address): void
    {
        if (! $lines && ! $bookingAt) {
            return;
        }

        // A business that delivers needs the address for an order of products (a booking has none)
        if ($lines && ! $bookingAt && ! $address && $workspace->ask_delivery_address) {
            return;
        }

        $order = $this->orderService->createDraft($conversation, $lines, $bookingAt, $address, $minutes);

        $this->actions->propose(
            $conversation->workspace_id,
            AgentName::Operations,
            ActionType::OrderConfirm,
            $this->summary($order),
            ['order_id' => $order->id],
            $conversation->id,
        );

        $this->owner->notify($conversation->workspace_id, __('alerts.order', [
            'customer' => $order->customer_name ?? '+'.$order->customer_phone,
            'details' => $order->lines()->implode(', ').($bookingAt ? ' on '.$order->booking_at->format('d M, h:i A') : ''),
            'total' => number_format((float) $order->total, 2),
            'address' => $order->address ? __('alerts.order_address', ['address' => $order->address]) : '',
        ]));
    }

    private function summary(Order $order): string
    {
        $items = $order->lines()->implode(', ');
        $when = $order->booking_at ? ' on '.$order->booking_at->format('d M, h:i A') : '';
        $where = $order->address ? ', deliver to '.$order->address : '';

        return trim(($order->booking_at ? 'Booking' : 'Order').': '.$items.$when).' = ₹'.number_format((float) $order->total, 2).' for '.($order->customer_name ?? '+'.$order->customer_phone).$where;
    }

    private function bookingTime(?string $text): ?string
    {
        try {
            return $text ? Carbon::parse($text, config('promises.timezone'))->toDateTimeString() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // The last thing the bot said in this chat came from this agent, a moment ago
    private function inOrderTalk(Conversation $conversation): bool
    {
        $last = $this->messages->forConversation($conversation->id, 6)->where('direction', 'outbound')->last();

        return $last
            && ($last->payload['agent'] ?? null) === AgentName::Operations->value
            && $last->created_at->gt(now()->subMinutes(config('operations.talk_minutes')));
    }

    // A shop with hundreds of products does not send them all: only the ones the customer talks about, up to a limit
    private function shown(Collection $products, string $transcript): Collection
    {
        $max = config('operations.max_products');

        if ($products->count() <= $max) {
            return $products;
        }

        $words = array_diff($this->words($transcript), ['customer', 'business']);
        $talked = $products->filter(fn (Product $product) => $this->mentions($product, $words));

        return ($talked->isNotEmpty() ? $talked : $products)->take($max)->values();
    }

    private function mentions(Product $product, array $words): bool
    {
        return collect($this->words($product->name))->filter(fn (string $word) => mb_strlen($word) >= 3)->intersect($words)->isNotEmpty();
    }

    // An amount in the reply that is not a price of ours, a whole number of it, or the total of this order
    private function quotesWrongPrice(string $reply, Collection $products, array $lines): bool
    {
        preg_match_all('/(?:₹|\b(?:rs\.?|inr)(?!\p{L}))\s*(\d[\d,]*(?:\.\d+)?)/iu', $reply, $found);
        $total = array_sum(array_map(fn (array $line) => (float) $line['product']->price * $line['quantity'], $lines));

        foreach ($found[1] as $amount) {
            $amount = (float) str_replace(',', '', $amount);

            $known = abs($amount - $total) < 0.01 || $products->contains(fn (Product $product) => (float) $product->price > 0 && abs(fmod($amount, (float) $product->price)) < 0.01);

            if (! $known) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    private function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{M}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY);
    }
}

<?php

namespace App\Services\Bookings;

use App\Models\Order;
use App\Models\Product;
use App\Models\Workspace;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * When can something be booked? The business sets its hours, closed days and how many bookings fit at the same time;
 * this works the same for a salon, a clinic, a class, a table or a property visit. All times are the business's own.
 */
class BookingSlotService
{
    public const PAST = 'past';

    public const CLOSED_DAY = 'closed_day';

    public const OUTSIDE_HOURS = 'outside_hours';

    public const FULL = 'full';

    public function __construct(private OrderRepositoryInterface $orders) {}

    public function timezone(): string
    {
        return config('promises.timezone');
    }

    /** How long a booking of these products takes: the longest of them, or the default */
    public function duration(iterable $products): int
    {
        $longest = 0;

        foreach ($products as $product) {
            $longest = max($longest, (int) ($product instanceof Product ? $product->duration_minutes : 0));
        }

        return $longest ?: config('bookings.default_minutes');
    }

    // null = the time is free; otherwise why not (one of the constants)
    // $bookings = the bookings around the time, when the caller already has them (so a search reads them once)
    public function check(Workspace $workspace, CarbonInterface $start, int $minutes, ?Collection $bookings = null): ?string
    {
        $start = Carbon::instance($start)->setTimezone($this->timezone());
        $end = $start->copy()->addMinutes($minutes);

        if ($start->lte(now($this->timezone()))) {
            return self::PAST;
        }

        if (in_array(config('bookings.days')[$start->dayOfWeek], $workspace->booking_closed_days ?? [], true)) {
            return self::CLOSED_DAY;
        }

        $open = Carbon::parse($start->toDateString().' '.$workspace->booking_open_time, $this->timezone());
        $close = Carbon::parse($start->toDateString().' '.$workspace->booking_close_time, $this->timezone());

        if ($start->lt($open) || $end->gt($close)) {
            return self::OUTSIDE_HOURS;
        }

        return $this->overlapping($bookings ?? $this->bookingsAround($workspace, $start, $end), $start, $end) >= $workspace->booking_capacity ? self::FULL : null;
    }

    /** @return Carbon[] the next free starting times for a booking of this length */
    public function nextFree(Workspace $workspace, CarbonInterface $from, int $minutes): array
    {
        $step = config('bookings.step_minutes');
        $candidate = Carbon::instance($from)->setTimezone($this->timezone())->second(0);
        $candidate->addMinutes($step - ($candidate->minute % $step))->second(0);
        $last = $candidate->copy()->addDays(config('bookings.look_ahead_days'));
        $found = [];
        $bookings = $this->bookingsAround($workspace, $candidate, $last);

        while ($candidate->lte($last) && count($found) < config('bookings.suggestions')) {
            if ($this->check($workspace, $candidate, $minutes, $bookings) === null) {
                $found[] = $candidate->copy();
            }
            $candidate->addMinutes($step);
        }

        return $found;
    }

    // What the assistant is told, so it only offers times that can work (the backend still checks every booking)
    public function rules(Workspace $workspace, int $minutes): string
    {
        $closed = $workspace->booking_closed_days ?? [];
        $now = now($this->timezone());
        $taken = $this->orders->bookingsStartingBetween($workspace->id, $now->toDateTimeString(), $now->copy()->addDays(config('bookings.look_ahead_days'))->toDateTimeString())
            ->take(40)
            ->map(fn (Order $order) => $order->booking_at->format('D d M H:i').'-'.$order->booking_at->copy()->addMinutes($order->duration_minutes ?? config('bookings.default_minutes'))->format('H:i'))
            ->implode(', ');

        return "Bookings: open {$workspace->booking_open_time} to {$workspace->booking_close_time}, "
            .($closed ? 'closed on '.implode(', ', $closed) : 'open every day')
            .". {$workspace->booking_capacity} booking(s) can be at the same time. A booking takes about {$minutes} minutes. "
            .($taken ? "Already taken: {$taken}. " : '')
            .'Never offer a time outside these rules. Ask the customer for a day and a time.';
    }

    private function bookingsAround(Workspace $workspace, Carbon $start, Carbon $end): Collection
    {
        $window = config('bookings.default_minutes') * 24; // a long booking that started earlier can still overlap

        return $this->orders->bookingsStartingBetween($workspace->id, $start->copy()->subMinutes($window)->toDateTimeString(), $end->toDateTimeString());
    }

    private function overlapping(Collection $bookings, Carbon $start, Carbon $end): int
    {
        return $bookings
            ->filter(function (Order $order) use ($start, $end) {
                $from = Carbon::parse($order->booking_at->toDateTimeString(), $this->timezone());
                $to = $from->copy()->addMinutes($order->duration_minutes ?? config('bookings.default_minutes'));

                return $from->lt($end) && $to->gt($start);
            })
            ->count();
    }
}

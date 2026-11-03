<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

// An order or a booking taken in a chat
class Order extends Model
{
    protected $fillable = ['workspace_id', 'conversation_id', 'customer_name', 'customer_phone', 'address', 'status', 'confirmed_at', 'delivery_status', 'paid_at', 'total', 'booking_at', 'duration_minutes', 'reminded_at', 'feedback_asked_at'];

    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'total' => 'decimal:2', 'booking_at' => 'datetime', 'confirmed_at' => 'datetime', 'paid_at' => 'datetime', 'reminded_at' => 'datetime', 'feedback_asked_at' => 'datetime'];
    }

    /** @return Collection<int, string> "2 × Haircut", one for each product or service */
    public function lines(): Collection
    {
        return $this->items->map(fn ($item) => "{$item->quantity} × {$item->name}");
    }

    // Is this the same order as these lines and time? (the customer saying "haan confirm" again is not a new order)
    public function sameAs(array $lines, ?string $bookingAt): bool
    {
        $wanted = collect($lines)->mapWithKeys(fn (array $line) => [$line['product']->id => $line['quantity']])->sortKeys()->all();

        return $this->items->pluck('quantity', 'product_id')->sortKeys()->all() === $wanted
            && $this->booking_at?->toDateTimeString() === $bookingAt;
    }

    // What it is for, in the customer's words: the names of the products or services
    public function what(): string
    {
        return $this->items->pluck('name')->implode(', ') ?: __('bookings.default_what');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

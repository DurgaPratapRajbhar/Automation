<?php

namespace App\Services\Agents\Operations;

// The way to ask the ai-service for the Operations agent's next message. The AI itself lives in Python.
interface OperationsInterface
{
    /**
     * @param  array<int, array{id: int, name: string, price: string, unit: string|null, stock: int|null}>  $products
     * @param  string  $transcript  the chat so far, one line per message
     * @param  string  $today  YYYY-MM-DD, in the business's time zone
     * @param  bool  $needsAddress  the business delivers: the agent must get the customer's address first
     * @param  string|null  $bookingRules  hours, closed days and taken times, when the business takes bookings
     * @return array{reply: string, items: array<int, array{product_id: int, quantity: int}>, booking_at: string|null, address: string|null, ready: bool}|null null = no answer
     */
    public function respond(array $products, string $transcript, string $today, bool $needsAddress = false, ?string $bookingRules = null): ?array;
}

<?php

namespace App\Services\Agents\Operations;

// For tests: no network, no answer
class NullOperations implements OperationsInterface
{
    public function respond(array $products, string $transcript, string $today, bool $needsAddress = false, ?string $bookingRules = null): ?array
    {
        return null;
    }
}

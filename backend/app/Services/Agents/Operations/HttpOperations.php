<?php

namespace App\Services\Agents\Operations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpOperations implements OperationsInterface
{
    public function respond(array $products, string $transcript, string $today, bool $needsAddress = false, ?string $bookingRules = null): ?array
    {
        try {
            return Http::timeout(60)
                ->post(rtrim(config('services.ai.url'), '/').'/operations', ['products' => $products, 'transcript' => $transcript, 'today' => $today, 'needs_address' => $needsAddress, 'booking_rules' => $bookingRules])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            Log::warning('Operations agent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

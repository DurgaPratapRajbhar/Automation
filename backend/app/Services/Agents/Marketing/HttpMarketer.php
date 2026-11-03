<?php

namespace App\Services\Agents\Marketing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpMarketer implements MarketerInterface
{
    public function draft(string $business, array $products, string $brief): ?array
    {
        try {
            return Http::timeout(60)
                ->post(rtrim(config('services.ai.url'), '/').'/campaign-draft', ['business' => $business, 'products' => $products, 'brief' => $brief])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            Log::warning('Marketing agent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

<?php

namespace App\Services\Agents\Finance;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpPromiseDetector implements PromiseDetectorInterface
{
    public function detect(string $transcript, string $today): ?array
    {
        try {
            return Http::timeout(60)
                ->post(rtrim(config('services.ai.url'), '/').'/promises', ['transcript' => $transcript, 'today' => $today])
                ->throw()
                ->json('promises');
        } catch (Throwable $e) {
            Log::warning('Finance agent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

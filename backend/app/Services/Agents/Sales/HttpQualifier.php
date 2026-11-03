<?php

namespace App\Services\Agents\Sales;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpQualifier implements QualifierInterface
{
    public function qualify(array $questions, string $transcript): ?array
    {
        try {
            return Http::timeout(60)
                ->post(rtrim(config('services.ai.url'), '/').'/qualify', ['questions' => array_values($questions), 'transcript' => $transcript])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            Log::warning('Sales agent failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

<?php

namespace App\Services\Agents\Finance;

// For tests: no network, no answer
class NullPromiseDetector implements PromiseDetectorInterface
{
    public function detect(string $transcript, string $today): ?array
    {
        return null;
    }
}

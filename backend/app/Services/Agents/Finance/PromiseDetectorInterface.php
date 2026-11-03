<?php

namespace App\Services\Agents\Finance;

// The way to ask the ai-service which promises a customer made. The AI itself lives in Python.
interface PromiseDetectorInterface
{
    /**
     * @param  string  $transcript  the last messages, one line per message
     * @param  string  $today  YYYY-MM-DD, in the business's time zone
     * @return array<int, array{kind: string, quote: string, amount: float|null, due_on: string}>|null [] = no promise; null = no answer
     */
    public function detect(string $transcript, string $today): ?array;
}
